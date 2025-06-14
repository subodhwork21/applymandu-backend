<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CalendarEventResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'start' => $this->start_time->toISOString(),
            'end' => $this->end_time->toISOString(),
            'type' => $this->type,
            'status' => $this->status,
            'location' => $this->location,
            'attendees' => $this->attendees,
            'job_id' => $this->job_id,
            'application_id' => $this->application_id,
            'candidate_name' => $this->candidate_name,
            'candidate_email' => $this->candidate_email,
            'meeting_link' => $this->meeting_link,
            'notes' => $this->notes,
            'is_all_day' => $this->is_all_day,
            'timezone' => $this->timezone,
            'reminder_settings' => $this->reminder_settings,
            'duration_minutes' => $this->duration_minutes,
            'is_upcoming' => $this->is_upcoming,
            'is_past' => $this->is_past,
            'event_color' => $this->getEventColor(),
            'status_color' => $this->getStatusColor(),
            'formatted_start_time' => $this->start_time->format('M j, Y g:i A'),
            'formatted_end_time' => $this->end_time->format('M j, Y g:i A'),
            'formatted_date' => $this->start_time->format('M j, Y'),
            'formatted_time_range' => $this->start_time->format('g:i A') . ' - ' . $this->end_time->format('g:i A'),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            
            // Related data
            'job' => $this->whenLoaded('job', function () {
                return [
                    'id' => $this->job->id,
                    'title' => $this->job->title,
                    'company_name' => $this->job->employer_name,
                ];
            }),
            
            'application' => $this->whenLoaded('application', function () {
                return [
                    'id' => $this->application->id,
                    'candidate_name' => $this->application->user->first_name . ' ' . $this->application->user->last_name,
                    'candidate_email' => $this->application->user->email,
                ];
            }),
        ];
    }
}
