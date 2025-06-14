<?php

namespace App\Http\Controllers\API;

use App\Events\MessageRead;
use App\Events\NewChatMessage;
use App\Http\Controllers\Controller;
use App\Models\Chat;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;

class ChatController extends Controller
{
    /**
     * Get all chats for the authenticated user
     */
    public function getChats(Request $request)
    {
        $userId = Auth::id();
        $showInactive = $request->input('show_inactive', false);

        $query = Chat::where(function ($query) use ($userId) {
            $query->where('user1_id', $userId)
                ->orWhere('user2_id', $userId);
        });

        // Only show active chats by default
        if (!$showInactive) {
            $query->where('is_active', true);
        }

        $chats = $query->with(['user1:id,name,image', 'user2:id,name,image', 'lastMessage'])
            ->orderBy('updated_at', 'desc')
            ->get()
            ->map(function ($chat) use ($userId) {
                // Determine the other user in the chat
                $otherUser = $chat->user1_id == $userId ? $chat->user2 : $chat->user1;

                // Count unread messages
                $unreadCount = Message::where('chat_id', $chat->id)
                    ->where('sender_id', '!=', $userId)
                    ->where('is_read', false)
                    ->count();

                return [
                    'id' => $chat->id,
                    'other_user' => $otherUser,
                    'last_message' => $chat->lastMessage,
                    'unread_count' => $unreadCount,
                    'is_active' => $chat->is_active,
                    'updated_at' => $chat->updated_at
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $chats
        ]);
    }

    /**
     * Get or create a chat with another user
     */
    public function getOrCreateChat(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()
            ], 422);
        }

        $userId = Auth::id();
        $otherUserId = $request->user_id;

        // Don't allow chat with self
        if ($userId == $otherUserId) {
            return response()->json([
                'error' => true,
                'message' => 'Cannot create chat with yourself'
            ], 400);
        }

        // Use a transaction to prevent race conditions
        return DB::transaction(function () use ($userId, $otherUserId) {
            // Check if chat already exists - lock the rows for update to prevent race conditions
            $chat = Chat::where(function ($query) use ($userId, $otherUserId) {
                $query->where('user1_id', $userId)
                    ->where('user2_id', $otherUserId);
            })
                ->orWhere(function ($query) use ($userId, $otherUserId) {
                    $query->where('user1_id', $otherUserId)
                        ->where('user2_id', $userId);
                })
                ->lockForUpdate()  // Lock the rows
                ->first();

            // If not, create a new chat
            if (!$chat) {
                $chat = Chat::create([
                    'user1_id' => $userId,
                    'user2_id' => $otherUserId,
                    'is_active' => true
                ]);
            } else if (!$chat->is_active) {
                // If chat exists but is inactive, reactivate it
                $chat->is_active = true;
                $chat->save();
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'chat_id' => $chat->id,
                    'is_active' => $chat->is_active
                ]
            ]);
        });
    }


    /**
     * Get messages for a specific chat
     */
    public function getMessages(Request $request, $chatId)
    {
        $userId = Auth::id();

        // Verify the user is part of this chat
        $chat = Chat::where('id', $chatId)
            ->where(function ($query) use ($userId) {
                $query->where('user1_id', $userId)
                    ->orWhere('user2_id', $userId);
            })
            ->first();

        if (!$chat) {
            return response()->json([
                'error' => true,
                'message' => 'Chat not found or you do not have access'
            ], 404);
        }

        // Check if chat is active
        if (!$chat->is_active) {
            return response()->json([
                'error' => true,
                'message' => 'This chat has been deactivated'
            ], 403);
        }

        // Get messages with pagination
        $perPage = $request->input('per_page', 20);
        $messages = Message::where('chat_id', $chatId)
            ->with('sender:id,first_name,image')
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        // Mark messages as read
        $unreadMessageIds = Message::where('chat_id', $chatId)
            ->where('sender_id', '!=', $userId)
            ->where('is_read', false)
            ->pluck('id')
            ->toArray();

        if (count($unreadMessageIds) > 0) {
            Message::whereIn('id', $unreadMessageIds)
                ->update(['is_read' => true]);

            // Broadcast that messages were read
            broadcast(new MessageRead($chatId, $unreadMessageIds, $userId))->toOthers();
        }

        return response()->json([
            'success' => true,
            'data' => $messages
        ]);
    }

    /**
     * Send a new message
     */

    public function sendMessage(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'chat_id' => 'required|exists:chats,id',
            'message' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()
            ], 422);
        }

        $userId = Auth::id();
        $chatId = $request->chat_id;

        // Verify the user is part of this chat
        $chat = Chat::where('id', $chatId)
            ->where(function ($query) use ($userId) {
                $query->where('user1_id', $userId)
                    ->orWhere('user2_id', $userId);
            })
            ->first();

        if (!$chat) {
            return response()->json([
                'error' => true,
                'message' => 'Chat not found or you do not have access'
            ], 404);
        }

        // Check if chat is active
        if (!$chat->is_active) {
            return response()->json([
                'error' => true,
                'message' => 'Cannot send message to inactive chat'
            ], 403);
        }

        // Determine the receiver ID (the other user in the chat)
        $receiverId = ($chat->user1_id == $userId) ? $chat->user2_id : $chat->user1_id;

        DB::beginTransaction();

        try {
            // Create the message
            $message = Message::create([
                'chat_id' => $chatId,
                'sender_id' => $userId,
                'receiver_id' => $receiverId, // Add the receiver ID
                'content' => $request->message,
                'is_read' => false
            ]);

            // Update the chat's last message and timestamp
            $chat->last_message_id = $message->id;
            $chat->updated_at = now(); // Update timestamp to bring chat to top
            $chat->save();

            // Load the sender for the broadcast
            $message->load(['receiver:id,image', 'sender:id,first_name,image']);

            // Broadcast the new message
            broadcast(new NewChatMessage($message))->toOthers();
            event(new MessageRead(1, [12, 13, 14], auth()->id()));

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $message
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'error' => true,
                'message' => 'Failed to send message: ' . $e->getMessage()
            ], 500);
        }
    }


    /**
     * Mark messages as read
     */
    public function markAsRead(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'chat_id' => 'required|exists:chats,id',
            'message_ids' => 'required|array',
            'message_ids.*' => 'exists:messages,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()
            ], 422);
        }

        $userId = Auth::id();
        $chatId = $request->chat_id;
        $messageIds = $request->message_ids;

        // Verify the user is part of this chat
        $chat = Chat::where('id', $chatId)
            ->where(function ($query) use ($userId) {
                $query->where('user1_id', $userId)
                    ->orWhere('user2_id', $userId);
            })
            ->first();

        if (!$chat) {
            return response()->json([
                'error' => true,
                'message' => 'Chat not found or you do not have access'
            ], 404);
        }

        // Mark messages as read
        Message::whereIn('id', $messageIds)
            ->where('chat_id', $chatId)
            ->where('sender_id', '!=', $userId)
            ->update(['is_read' => true]);

        // Broadcast that messages were read
        broadcast(new MessageRead($chatId, $messageIds, $userId))->toOthers();

        return response()->json([
            'success' => true,
            'message' => 'Messages marked as read'
        ]);
    }

    /**
     * Toggle chat active status
     */
    public function toggleChatStatus(Request $request, $chatId)
    {
        $userId = Auth::id();

        // Verify the user is part of this chat
        $chat = Chat::where('id', $chatId)
            ->where(function ($query) use ($userId) {
                $query->where('user1_id', $userId)
                    ->orWhere('user2_id', $userId);
            })
            ->first();

        if (!$chat) {
            return response()->json([
                'error' => true,
                'message' => 'Chat not found or you do not have access'
            ], 404);
        }

        // Toggle the is_active status
        $chat->is_active = !$chat->is_active;
        $chat->save();

        $status = $chat->is_active ? 'activated' : 'deactivated';

        return response()->json([
            'success' => true,
            'message' => "Chat has been $status",
            'data' => [
                'is_active' => $chat->is_active
            ]
        ]);
    }

    public function getChatPreviews()
    {
        $userId = Auth::id();

        // Get all active chats for the user
        $chats = Chat::where(function ($query) use ($userId) {
            $query->where('user1_id', $userId)
                ->orWhere('user2_id', $userId);
        })
            ->where('is_active', true)
            ->with([
                'user1:id,first_name,last_name,company_name,image',
                'user2:id,first_name,last_name,company_name,image',
                'lastMessage'
            ])
            ->orderBy('updated_at', 'desc')
            ->limit(10) // Limit to most recent 10 chats
            ->get();

        $chatPreviews = $chats->map(function ($chat) use ($userId) {
            // Determine the other user in the chat
            $otherUser = $chat->user1_id == $userId ? $chat->user2 : $chat->user1;

            // Get name based on user type (jobseeker or employer)
            $name = $otherUser->company_name
                ? $otherUser->company_name
                : $otherUser->first_name . ' ' . $otherUser->last_name;

            // Get position if available (can be extended based on your user model)
            $position = '';
            if (isset($otherUser->profile) && isset($otherUser->profile->current_position)) {
                $position = $otherUser->profile->current_position;
            }

            // Count unread messages
            $unreadCount = Message::where('chat_id', $chat->id)
                ->where('sender_id', '!=', $userId)
                ->where('is_read', false)
                ->count();

            // Get last message content
            $lastMessage = $chat->lastMessage ? $chat->lastMessage->content : '';

            return [
                'id' => $chat->id,
                'user_id' => $otherUser->id,
                'name' => $name,
                'position' => $position,
                'avatar' => $otherUser->image_path,
                'last_message' => $lastMessage,
                'unread_count' => $unreadCount,
                'updated_at' => $chat->updated_at,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $chatPreviews
        ]);
    }
}
