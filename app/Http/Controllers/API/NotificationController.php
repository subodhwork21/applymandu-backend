<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\Activity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /**
     * Get all notifications for the authenticated user
     *
     * @param Request $request
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $type = ['profile_viewed', 'interview_scheduled', 'application_rejected', 'job_matched'];
        if ($request->user()->hasRole('employer')) {
            $type = ['application_submitted', 'job_closed', 'job_rejected'];
        }
        $perPage = $request->input('per_page', 10);

        // Get database notifications - filter for unread only (read_at is null)
        $databaseNotifications = $user->notifications()
            ->select('id', 'type', 'data', 'read_at', 'created_at')
            ->whereNull('read_at')  // Only get unread notifications
            ->get()
            ->map(function ($notification) {
                return [
                    'id' => $notification->id,
                    'type' => 'notification',
                    'notification_type' => class_basename($notification->type),
                    'data' => $notification->data,
                    'read_at' => $notification->read_at,
                    'created_at' => $notification->created_at,
                    'source' => 'notification'
                ];
            });

        // Get activities related to the user
        $activities = Activity::where(function ($query) use ($user, $type) {
            if ($user->hasRole('employer')) {
                // For employers, get activities related to their jobs
                $query->whereIn('type', $type)
                    ->whereIn('subject_id', function ($subquery) use ($user) {
                        $subquery->select('id')
                            ->from('am_jobs')
                            ->where('employer_id', $user->id);
                    });
            } else {
                // For jobseekers, get activities where they are the subject
                $query->whereIn('type', $type)
                    ->where('subject_id', $user->id);
            }
        })
            ->select('id', 'description', 'type', 'subject_type', 'subject_id', 'created_at')
            ->get()
            ->map(function ($activity) {
                return [
                    'id' => 'activity_' . $activity->id,
                    'type' => 'activity',
                    'activity_type' => $activity->type,
                    'data' => [
                        'description' => $activity->description,
                        'subject_type' => $activity->subject_type,
                        'subject_id' => $activity->subject_id,
                    ],
                    'read_at' => null,
                    'created_at' => $activity->created_at,
                    'source' => 'activity'
                ];
            });

        // Merge both collections
        $allNotifications = $databaseNotifications->concat($activities)
            ->sortByDesc('created_at')
            ->values();

        // Manual pagination since we're working with a collection
        $page = $request->input('page', 1);
        $total = $allNotifications->count();
        $lastPage = ceil($total / $perPage);

        $paginatedItems = $allNotifications->forPage($page, $perPage);

        // Create a custom pagination response
        $result = new \Illuminate\Pagination\LengthAwarePaginator(
            $paginatedItems,
            $total,
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return response()->json($result);
    }



    /**
     * Get unread notifications for the authenticated user
     *
     * @param Request $request
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function unread(Request $request)
    {
        $user = Auth::user();
        $perPage = $request->input('per_page', 10);

        // Get only unread notifications with pagination
        $notifications = $user->unreadNotifications()
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);

        return NotificationResource::collection($notifications);
    }

    /**
     * Mark a notification as read
     *
     * @param Request $request
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function markAsRead(Request $request, $id)
    {
        $user = Auth::user();
        $notification = $user->notifications()->where('id', $id)->first();

        if (!$notification) {
            return response()->json([
                'error' => true,
                'message' => 'Notification not found'
            ], 404);
        }

        $notification->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read'
        ]);
    }

    /**
     * Mark all notifications as read
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function markAllAsRead(Request $request)
    {
        $user = Auth::user();
        $user->unreadNotifications->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read'
        ]);
    }

    /**
     * Delete a notification
     *
     * @param Request $request
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(Request $request, $id)
    {
        $user = Auth::user();
        $notification = $user->notifications()->where('id', $id)->first();

        if (!$notification) {
            return response()->json([
                'error' => true,
                'message' => 'Notification not found'
            ], 404);
        }

        $notification->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notification deleted successfully'
        ]);
    }
}
