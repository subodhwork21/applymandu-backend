<?php

namespace App\Http\Controllers\API\Employer;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCalendarEventRequest;
use App\Http\Requests\UpdateCalendarEventRequest;
use App\Http\Resources\CalendarEventResource;
use App\Models\CalendarEvent;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CalendarEventController extends Controller
{
    /**
     * Display a listing of calendar events
     */
    public function index(Request $request): JsonResponse
    {
        $query = CalendarEvent::where('employer_id', auth()->id())
                             ->with(['job', 'application.user']);

        // Filter by date range
        if ($request->has('start') && $request->has('end')) {
            $startDate = Carbon::parse($request->start);
            $endDate = Carbon::parse($request->end);
            $query->inDateRange($startDate, $endDate);
        }

        // Filter by type
        if ($request->has('type') && !empty($request->type)) {
            $types = is_array($request->type) ? $request->type : [$request->type];
            $query->whereIn('type', $types);
        }

        // Filter by status
        if ($request->has('status') && !empty($request->status)) {
            $statuses = is_array($request->status) ? $request->status : [$request->status];
            $query->whereIn('status', $statuses);
        }

        // Filter by job
        if ($request->has('job_id')) {
            $query->where('job_id', $request->job_id);
        }

        // Sort by start time
        $events = $query->orderBy('start_time', 'asc')->get();

        return response()->json([
            'success' => true,
            'data' => CalendarEventResource::collection($events),
            'message' => 'Calendar events retrieved successfully'
        ], 200);
    }

    /**
     * Store a newly created calendar event
     */
    public function store(StoreCalendarEventRequest $request): JsonResponse
    {
        try {
            //add employer id to the request
            $request->merge(['employer_id' => auth()->id()]);
            $event = CalendarEvent::create($request->all());
            $event->load(['job', 'application.user']);

            return response()->json([
                'success' => true,
                'data' => new CalendarEventResource($event),
                'message' => 'Calendar event created successfully'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => 'Failed to create calendar event',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified calendar event
     */
    public function show(CalendarEvent $event): JsonResponse
    {
        // Check if user owns this event
        if ($event->employer_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to calendar event'
            ], 403);
        }

        $event->load(['job', 'application.user']);

        return response()->json([
            'success' => true,
            'data' => new CalendarEventResource($event),
            'message' => 'Calendar event retrieved successfully'
        ]);
    }

    /**
     * Update the specified calendar event
     */
    public function update(UpdateCalendarEventRequest $request, CalendarEvent $event): JsonResponse
    {
        try {
            $event->update($request->validated());
            $event->load(['job', 'application.user']);

            return response()->json([
                'success' => true,
                'data' => new CalendarEventResource($event),
                'message' => 'Calendar event updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => 'Failed to update calendar event',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified calendar event
     */
    public function destroy(CalendarEvent $event): JsonResponse
    {
        // Check if user owns this event
        if ($event->employer_id !== auth()->id()) {
            return response()->json([
                'error' => true,
                'message' => 'Unauthorized access to calendar event'
            ], 403);
        }

        try {
            $event->delete();

            return response()->json([
                'success' => true,
                'message' => 'Calendar event deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => 'Failed to delete calendar event',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get calendar statistics
     */
    public function statistics(): JsonResponse
    {
        $employerId = auth()->id();
        
        $stats = [
            'total_events' => CalendarEvent::where('employer_id', $employerId)->count(),
            'interviews' => CalendarEvent::where('employer_id', $employerId)
                                      ->where('type', CalendarEvent::TYPE_INTERVIEW)
                                      ->count(),
            'meetings' => CalendarEvent::where('employer_id', $employerId)
                                     ->where('type', CalendarEvent::TYPE_MEETING)
                                     ->count(),
            'scheduled' => CalendarEvent::where('employer_id', $employerId)
                                      ->where('status', CalendarEvent::STATUS_SCHEDULED)
                                      ->count(),
            'completed' => CalendarEvent::where('employer_id', $employerId)
                                      ->where('status', CalendarEvent::STATUS_COMPLETED)
                                      ->count(),
            'cancelled' => CalendarEvent::where('employer_id', $employerId)
                                      ->where('status', CalendarEvent::STATUS_CANCELLED)
                                      ->count(),
            'upcoming_this_week' => CalendarEvent::where('employer_id', $employerId)
                                                ->thisWeek()
                                                ->upcoming()
                                                ->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
            'message' => 'Calendar statistics retrieved successfully'
        ]);
    }

    /**
     * Get upcoming events
     */
    public function upcoming(Request $request): JsonResponse
    {
        $limit = $request->get('limit', 10);
        
        $events = CalendarEvent::where('employer_id', auth()->id())
                              ->upcoming()
                              ->with(['job', 'application.user'])
                              ->orderBy('start_time', 'asc')
                              ->limit($limit)
                              ->get();

        return response()->json([
            'success' => true,
            'data' => CalendarEventResource::collection($events),
            'message' => 'Upcoming events retrieved successfully'
        ]);
    }

    /**
     * Export calendar events
     */
    public function export(Request $request): \Illuminate\Http\Response
    {
        $events = CalendarEvent::where('employer_id', auth()->id())
                              ->with(['job', 'application.user'])
                              ->orderBy('start_time', 'asc')
                              ->get();

        $icsContent = $this->generateIcsFile($events);

        return response($icsContent)
            ->header('Content-Type', 'text/calendar')
            ->header('Content-Disposition', 'attachment; filename="calendar-events.ics"');
    }

    /**
     * Generate ICS file content
     */
    private function generateIcsFile($events): string
    {
        $ics = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//ApplyMandu//Calendar//EN',
            'CALSCALE:GREGORIAN',
        ];

        foreach ($events as $event) {
            $startTime = $event->start_time->utc()->format('Ymd\THis\Z');
            $endTime = $event->end_time->utc()->format('Ymd\THis\Z');
            $now = now()->utc()->format('Ymd\THis\Z');

            $ics[] = 'BEGIN:VEVENT';
            $ics[] = "UID:{$event->id}@applymandu.com";
            $ics[] = "DTSTART:{$startTime}";
            $ics[] = "DTEND:{$endTime}";
            $ics[] = "DTSTAMP:{$now}";
            $ics[] = "SUMMARY:{$event->title}";
            $ics[] = "DESCRIPTION:" . str_replace(["\r\n", "\n", "\r"], "\\n", $event->description ?? '');
            $ics[] = "LOCATION:" . ($event->location ?? '');
            $ics[] = "STATUS:" . strtoupper($event->status);
            $ics[] = 'END:VEVENT';
        }

        $ics[] = 'END:VCALENDAR';

        return implode("\r\n", $ics);
    }

    /**
     * Bulk update event status
     */
    public function bulkUpdateStatus(Request $request): JsonResponse
    {
        $request->validate([
            'event_ids' => 'required|array',
            'event_ids.*' => 'exists:calendar_events,id',
            'status' => 'required|in:scheduled,completed,cancelled,rescheduled'
        ]);

        try {
            $updated = CalendarEvent::where('employer_id', auth()->id())
                                   ->whereIn('id', $request->event_ids)
                                   ->update(['status' => $request->status]);

            return response()->json([
                'success' => true,
                'message' => "{$updated} events updated successfully"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => 'Failed to update events',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get events for a specific date
     */
    public function getEventsByDate(Request $request): JsonResponse
    {
        $request->validate([
            'date' => 'required|date'
        ]);

        $date = Carbon::parse($request->date);
        
        $events = CalendarEvent::where('employer_id', auth()->id())
                              ->whereDate('start_time', $date)
                              ->with(['job', 'application.user'])
                              ->orderBy('start_time', 'asc')
                              ->get();

        return response()->json([
            'success' => true,
            'data' => CalendarEventResource::collection($events),
            'message' => 'Events for date retrieved successfully'
        ]);
    }
}
