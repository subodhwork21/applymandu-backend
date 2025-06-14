<?php

namespace App\Http\Resources;

use App\Models\Activity;
use App\Models\Application;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;

class AmJobResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isJobseeker = Auth::user()?->hasRole("jobseeker");
        return [
            'id' => $this->id,
            'title' => $this->title,
            'experience_level' => $this->experience_level,
            'location' => $this->location,
            'description' => $this->description,
            'location_type' => $this->location_type,
            'employment_type' => $this->employment_type,
            'department' => $this->department,
            'application_deadline' => $this->application_deadline,
            'salary_range' => [
                'min' => $this->salary_min,
                'max' => $this->salary_max,
                'formatted' => 'Rs. ' . number_format($this->salary_min) . 'k - Rs. ' . number_format($this->salary_max) . 'k',
            ],
            'requirements' => json_decode($this->requirements),
            'responsibilities' => json_decode($this->responsibilities),
            'benefits' => json_decode($this->benefits),
            'posted_date' => $this->posted_date,
            'posted_date_formatted' => $this->posted_date ?
                ($this->posted_date instanceof \Carbon\Carbon ?
                    $this->posted_date->format('F d, Y') :
                    date('F d, Y', strtotime($this->posted_date))) :
                null,
            'employer_id' => $this?->employer?->id,
            'employer_name' => $this?->employer?->company_name,
            'image' =>  $this?->employer?->employerLogo ?? asset("/image.png"),
            'skills' => SkillResource::collection($this->whenLoaded('skills')),
            // 'employer' => new EmployerResource($this->whenLoaded('employer')) ?? null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'viewed' => $isJobseeker ? Activity::where("type", "job_viewed")->where("subject_id", $this->id)->where("user_id", Auth::user()->id)->exists() : null,
            'saved' => $isJobseeker ? Activity::where("type", "job_saved")->where("subject_id", $this->id)->where("user_id", Auth::user()->id)->exists() : null,
            'is_applied' => $isJobseeker ? Application::where("job_id", $this->id)->where("user_id", Auth::user()->id)->exists() : null,
            'location_type' => $this->location_type,
            'status' => $this->status,
            'slug' => $this->slug,
            'applications' => ApplicationResource::collection($this->whenLoaded('applications')) ?? null,
            'deleted_at' => $this->deleted_at,
            'job_label' => $this->job_label,
        ];
    }
}
