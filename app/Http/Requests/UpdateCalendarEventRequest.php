<?php

namespace App\Http\Requests;

use App\Models\CalendarEvent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCalendarEventRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $event = $this->route('event');
        return auth()->check() && 
               auth()->user()->role === 'employer' && 
               $event && 
               $event->employer_id === auth()->id();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'start_time' => ['sometimes', 'required', 'date'],
            'end_time' => ['sometimes', 'required', 'date', 'after:start_time'],
            'type' => ['sometimes', 'required', Rule::in([
                CalendarEvent::TYPE_INTERVIEW,
                CalendarEvent::TYPE_MEETING,
                CalendarEvent::TYPE_DEADLINE,
                CalendarEvent::TYPE_OTHER,
            ])],
            'status' => ['sometimes', Rule::in([
                CalendarEvent::STATUS_SCHEDULED,
                CalendarEvent::STATUS_COMPLETED,
                CalendarEvent::STATUS_CANCELLED,
                CalendarEvent::STATUS_RESCHEDULED,
            ])],
            'location' => ['nullable', 'string', 'max:255'],
            'attendees' => ['nullable', 'array'],
            'attendees.*' => ['email'],
            'job_id' => ['nullable', 'exists:jobs,id'],
            'application_id' => ['nullable', 'exists:job_applications,id'],
            'candidate_name' => ['nullable', 'string', 'max:255'],
            'candidate_email' => ['nullable', 'email'],
            'meeting_link' => ['nullable', 'url'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_all_day' => ['boolean'],
            'timezone' => ['nullable', 'string', 'max:50'],
            'reminder_settings' => ['nullable', 'array'],
            'reminder_settings.email' => ['boolean'],
            'reminder_settings.minutes_before' => ['integer', 'min:0', 'max:10080'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Event title is required.',
            'start_time.required' => 'Start time is required.',
            'end_time.required' => 'End time is required.',
            'end_time.after' => 'End time must be after start time.',
            'type.required' => 'Event type is required.',
            'type.in' => 'Invalid event type selected.',
            'attendees.*.email' => 'All attendees must have valid email addresses.',
            'job_id.exists' => 'Selected job does not exist.',
            'application_id.exists' => 'Selected application does not exist.',
            'candidate_email.email' => 'Candidate email must be a valid email address.',
            'meeting_link.url' => 'Meeting link must be a valid URL.',
        ];
    }
}
