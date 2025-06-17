<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class NewChatMessage implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $chatMessage;

    /**
     * Create a new event instance.
     */
    public function __construct(Message $chatMessage)
    {
        // Make sure we only load the required data for frontend
        $this->chatMessage = $chatMessage->load([
            'sender:id,image',
            'receiver:id,image'
        ]);

        // Append image_path to sender and receiver
        if ($this->chatMessage->sender) {
            $this->chatMessage->sender->append('image_path');
        }

        if ($this->chatMessage->receiver) {
            $this->chatMessage->receiver->append('image_path');
        }
    }



    /**
     * Get the channels the event should broadcast on.
     */
    public function broadcastOn(): array
    {
        // Log::info('Broadcasting message', ['chat_id' => $this->chatMessage->chat_id, 'message_id' => $this->chatMessage->id]);

        return [
            new PrivateChannel('chat.' . $this->chatMessage->chat_id),
            new PrivateChannel('user.' . $this->chatMessage->receiver_id . '.messages'),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'chat_message' => $this->chatMessage,
        ];
    }
}
