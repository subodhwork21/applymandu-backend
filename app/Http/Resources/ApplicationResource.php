<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;

class ApplicationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $this?->user;
        return [
            'id' => $this->id,
            'job_id' => $this->job_id,
            'user_id' => $this->user_id,
            'employer' => $this->job ? new EmployerResource($this->job->employer) : null,
            'year_of_experience' => $this->year_of_experience,
            'expected_salary' => $this->expected_salary,
            'notice_period' => $this->notice_period,
            'cover_letter' => $this->cover_letter,
            'applied_at' => $this->applied_at,
            'formatted_applied_at' => $this->applied_at ?
                ($this->applied_at instanceof \Carbon\Carbon ?
                    $this->applied_at->format('F d, Y') :
                    date('F d, Y', strtotime($this->applied_at))) :
                null,
            'updated_at' => $this->updated_at,
            'status' => $this->status,
            'job_title' => $this->job ? $this->job->title : null,
            'company_name' => $this->job ? $this->job->employer->company_name : null,
            'location' => $this->job ? $this->job->location : null,
            'applied_user' => $user ? $this->user->first_name . ' ' . $this->user->last_name : null,
            'user_image' => $user ? $this->user->imagePath : null,
            'skills'=> $user ? $this->user->skills->pluck('name') : null,
            'status_history' => $this->applicationStatusHistory ? $this->applicationStatusHistory : null,
        ];
    }
}
