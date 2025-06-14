<?php

namespace App\Http\Requests;

use App\Models\CalendarEvent;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreCalendarEventRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check() && Auth::user()->whereHas('roles', function($q){
            $q->where('name', 'employer');
        })->exists();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'start_time' => ['required', 'date', 'after:now'],
            'end_time' => ['required', 'date', 'after:start_time'],
            'type' => ['required', Rule::in([
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
            'job_id' => ['nullable', 'exists:am_jobs,id'],
            'application_id' => ['nullable', 'exists:applications,id'],
            'candidate_name' => ['nullable', 'string', 'max:255'],
            'candidate_email' => ['nullable', 'email'],
            'meeting_link' => ['nullable', 'url'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_all_day' => ['boolean'],
            'timezone' => ['nullable', 'string', 'max:50'],
            'reminder_settings' => ['nullable', 'array'],
            'reminder_settings.email' => ['boolean'],
            'reminder_settings.minutes_before' => ['integer', 'min:0', 'max:10080'], // Max 1 week
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
            'start_time.after' => 'Start time must be in the future.',
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

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Set default status if not provided
        if (!$this->has('status')) {
            $this->merge(['status' => CalendarEvent::STATUS_SCHEDULED]);
        }

        // Set employer_id from authenticated user
        $this->merge(['employer_id' => auth()->id()]);

        // Convert timezone if provided
        if ($this->has('timezone') && $this->timezone) {
            try {
                $startTime = new \DateTime($this->start_time, new \DateTimeZone($this->timezone));
                $endTime = new \DateTime($this->end_time, new \DateTimeZone($this->timezone));
                
                $this->merge([
                    'start_time' => $startTime->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
                    'end_time' => $endTime->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
                ]);
            } catch (\Exception $e) {
                // Keep original times if timezone conversion fails
            }
        }

        
    }

     public function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'error' => true,
            'message' => 'Validation failed',
            'errors' => $validator->errors()
        ], 422));
    }
}
