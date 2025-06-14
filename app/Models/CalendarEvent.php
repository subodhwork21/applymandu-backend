<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class CalendarEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'employer_id',
        'title',
        'description',
        'start_time',
        'end_time',
        'type',
        'status',
        'location',
        'attendees',
        'job_id',
        'application_id',
        'candidate_name',
        'candidate_email',
        'meeting_link',
        'notes',
        'is_all_day',
        'timezone',
        'reminder_settings',
        'reminded_at',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'attendees' => 'array',
        'reminder_settings' => 'array',
        'reminded_at' => 'datetime',
        'is_all_day' => 'boolean',
    ];

    protected $appends = [
        'formatted_start_time',
        'formatted_end_time',
        'duration_minutes',
        'is_upcoming',
        'is_past',
    ];

    // Event types
    const TYPE_INTERVIEW = 'interview';
    const TYPE_MEETING = 'meeting';
    const TYPE_DEADLINE = 'deadline';
    const TYPE_OTHER = 'other';

    // Event statuses
    const STATUS_SCHEDULED = 'scheduled';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_RESCHEDULED = 'rescheduled';

    /**
     * Get the employer that owns the event
     */
    public function employer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employer_id');
    }

    /**
     * Get the job associated with the event
     */
    public function job(): BelongsTo
    {
        return $this->belongsTo(AmJob::class);
    }

    /**
     * Get the job application associated with the event
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class, 'application_id');
    }

    /**
     * Scope for upcoming events
     */
    public function scopeUpcoming($query)
    {
        return $query->where('start_time', '>', now())
                    ->where('status', self::STATUS_SCHEDULED);
    }

    /**
     * Scope for past events
     */
    public function scopePast($query)
    {
        return $query->where('end_time', '<', now());
    }

    /**
     * Scope for today's events
     */
    public function scopeToday($query)
    {
        return $query->whereDate('start_time', today());
    }

    /**
     * Scope for this week's events
     */
    public function scopeThisWeek($query)
    {
        return $query->whereBetween('start_time', [
            now()->startOfWeek(),
            now()->endOfWeek()
        ]);
    }

    /**
     * Scope by event type
     */
    public function scopeOfType($query, $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Scope by status
     */
    public function scopeWithStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for events in date range
     */
    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->where(function ($q) use ($startDate, $endDate) {
            $q->whereBetween('start_time', [$startDate, $endDate])
              ->orWhereBetween('end_time', [$startDate, $endDate])
              ->orWhere(function ($q2) use ($startDate, $endDate) {
                  $q2->where('start_time', '<=', $startDate)
                     ->where('end_time', '>=', $endDate);
              });
        });
    }

    /**
     * Get formatted start time
     */
    public function getFormattedStartTimeAttribute()
    {
        return $this->start_time->format('Y-m-d H:i:s');
    }

    /**
     * Get formatted end time
     */
    public function getFormattedEndTimeAttribute()
    {
        return $this->end_time->format('Y-m-d H:i:s');
    }

    /**
     * Get duration in minutes
     */
    public function getDurationMinutesAttribute()
    {
        return $this->start_time->diffInMinutes($this->end_time);
    }

    /**
     * Check if event is upcoming
     */
    public function getIsUpcomingAttribute()
    {
        return $this->start_time->isFuture() && $this->status === self::STATUS_SCHEDULED;
    }

    /**
     * Check if event is past
     */
    public function getIsPastAttribute()
    {
        return $this->end_time->isPast();
    }

    /**
     * Get event color based on type
     */
    public function getEventColor()
    {
        return match($this->type) {
            self::TYPE_INTERVIEW => '#10b981',
            self::TYPE_MEETING => '#3b82f6',
            self::TYPE_DEADLINE => '#ef4444',
            self::TYPE_OTHER => '#8b5cf6',
            default => '#6b7280',
        };
    }

    /**
     * Get status color
     */
    public function getStatusColor()
    {
        return match($this->status) {
            self::STATUS_SCHEDULED => '#3b82f6',
            self::STATUS_COMPLETED => '#10b981',
            self::STATUS_CANCELLED => '#ef4444',
            self::STATUS_RESCHEDULED => '#f59e0b',
            default => '#6b7280',
        };
    }

    /**
     * Mark event as completed
     */
    public function markAsCompleted()
    {
        $this->update(['status' => self::STATUS_COMPLETED]);
    }

    /**
     * Cancel event
     */
    public function cancel()
    {
        $this->update(['status' => self::STATUS_CANCELLED]);
    }

    /**
     * Reschedule event
     */
    public function reschedule($newStartTime, $newEndTime)
    {
        $this->update([
            'start_time' => $newStartTime,
            'end_time' => $newEndTime,
            'status' => self::STATUS_RESCHEDULED,
        ]);
    }

    /**
     * Generate ICS calendar invite
     */
    public function generateIcsInvite()
    {
        $startTime = $this->start_time->utc()->format('Ymd\THis\Z');
        $endTime = $this->end_time->utc()->format('Ymd\THis\Z');
        $now = now()->utc()->format('Ymd\THis\Z');

        $ics = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//ApplyMandu//Calendar//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:REQUEST',
            'BEGIN:VEVENT',
            "UID:{$this->id}@applymandu.com",
            "DTSTART:{$startTime}",
            "DTEND:{$endTime}",
            "DTSTAMP:{$now}",
            "SUMMARY:{$this->title}",
            "DESCRIPTION:" . str_replace(["\r\n", "\n", "\r"], "\\n", $this->description ?? ''),
            "LOCATION:" . ($this->location ?? ''),
            "STATUS:" . strtoupper($this->status),
            'END:VEVENT',
            'END:VCALENDAR'
        ];

        return implode("\r\n", $ics);
    }
}
