<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ResumeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'jobseekerProfile' => $this->jobSeekerProfile,
            'experiences' => $this->experiences,
            'educations' => $this->educations,
            'languages' => $this->languages,
            'trainings' => $this->trainings,
            'certificates' => $this->certificates,
            'social_links' => $this->socialLinks,
            'references' => $this->references,
            'skills' => SkillResource::collection($this->whenLoaded('skills')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
