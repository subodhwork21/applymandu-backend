<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SavedCandidateResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array
     */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'employer_id' => $this->employer_id,
            'jobseeker_id' => $this->jobseeker_id,
            'job_id' => $this->job_id,
            'notes' => $this->notes,
            'saved_at' => $this->saved_at ? $this->saved_at->format('Y-m-d H:i:s') : null,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
            
            // Include jobseeker information
            'jobseeker' => [
                'id' => $this->jobseeker->id,
                'first_name' => $this->jobseeker->first_name,
                'last_name' => $this->jobseeker->last_name,
                'email' => $this->jobseeker->email,
                'phone' => $this->jobseeker->phone,
                'image_path' => $this->jobseeker->image_path,
                
                // Include profile information if available
                'profile' => $this->whenLoaded('jobseeker.jobSeekerProfile', function () {
                    return [
                        'id' => $this->jobseeker->jobSeekerProfile->id,
                        'location' => $this->jobseeker->jobSeekerProfile->location ?? null,
                        'industry' => $this->jobseeker->jobSeekerProfile->industry ?? null,
                        'career_objectives' => $this->jobseeker->jobSeekerProfile->career_objectives ?? null,
                        'salary_expectation' => $this->jobseeker->jobSeekerProfile->salary_expectation ?? null,
                        'view_count' => $this->jobseeker->jobSeekerProfile->view_count ?? 0,
                    ];
                }),
                
                // Include skills
                'skills' => $this->whenLoaded('jobseeker.skills', function () {
                    return $this->jobseeker->skills->map(function ($skill) {
                        return [
                            'id' => $skill->id,
                            'name' => $skill->name,
                        ];
                    });
                }),
                
                // Include experiences
                'experiences' => $this->whenLoaded('jobseeker.experiences', function () {
                    return $this->jobseeker->experiences->map(function ($experience) {
                        return [
                            'id' => $experience->id,
                            'job_title' => $experience->job_title,
                            'company_name' => $experience->company_name,
                            'start_date' => $experience->start_date,
                            'end_date' => $experience->end_date,
                            'currently_work_here' => (bool)$experience->currently_work_here,
                            'level' => $experience->level,
                            'industry' => $experience->industry,
                            'description' => $experience->description,
                        ];
                    });
                }),
            ],
            
            // Include job information if available
            'job' => $this->whenLoaded('job', function () {
                return [
                    'id' => $this->job->id,
                    'title' => $this->job->title,
                    'slug' => $this->job->slug,
                    'location' => $this->job->location,
                    'employment_type' => $this->job->employment_type,
                    'experience_level' => $this->job->experience_level,
                    'salary_min' => $this->job->salary_min,
                    'salary_max' => $this->job->salary_max,
                    'application_deadline' => $this->job->application_deadline,
                    'status' => $this->job->status,
                ];
            }),
        ];
    }
}
