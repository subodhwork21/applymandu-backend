<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class JobseekerProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'first_name' => $this->first_name,
            'middle_name' => $this->middle_name,
            'last_name' => $this->last_name,
            'full_name' => trim($this->first_name . ' ' . $this->middle_name . ' ' . $this->last_name),
            'district' => $this->district,
            'municipality' => $this->municipality,
            'city_tole' => $this->city_tole,
            'location' => $this->formatLocation(),
            'date_of_birth' => $this->date_of_birth,
            'age' => $this->date_of_birth ? now()->diffInYears($this->date_of_birth) : null,
            'mobile' => $this->mobile,
            'industry' => $this->industry,
            'preferred_job_type' => $this->preferred_job_type,
            'gender' => $this->gender,
            'has_driving_license' => (bool) $this->has_driving_license,
            'has_vehicle' => (bool) $this->has_vehicle,
            'career_objectives' => $this->career_objectives,
            'immediate_availability' => (bool) $this->immediate_availability,
            'availability_date' => $this->availability_date,
            'expected_salary' => $this->expected_salary,
            'image' => $this->user->image_path,
            'email' => $this->when($this->user->show_contact_info, $this->user->email),
            'phone' => $this->when($this->user->show_contact_info, $this->user->phone),
            'skills' => SkillResource::collection($this->whenLoaded('skills')),
            'experiences' => ExperienceResource::collection($this->whenLoaded('experiences')),
            'educations' => EducationResource::collection($this->whenLoaded('educations')),
            'total_experience_years' => $this->calculateTotalExperienceYears(),
            'highest_education' => $this->getHighestEducation(),
            'last_active' => $this->user->last_login_at ? $this->user->last_login_at->diffForHumans() : 'Never',
            'online_status' => $this->when($this->user->show_online_status, function() {
                return $this->user->last_login_at && $this->user->last_login_at->gt(now()->subMinutes(15)) ? 'online' : 'offline';
            }),
            'saved' => $this->when(isset($this->saved), $this->saved),
            'saved_notes' => $this->when(isset($this->saved_notes), $this->saved_notes),
            'saved_at' => $this->when(isset($this->saved_at), function() {
                return $this->saved_at->toISOString();
            }),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
    
    /**
     * Format the location string
     *
     * @return string
     */
    protected function formatLocation()
    {
        $parts = array_filter([$this->district, $this->municipality, $this->city_tole]);
        return implode(', ', $parts);
    }
    
    /**
     * Calculate total years of experience
     *
     * @return float
     */
    protected function calculateTotalExperienceYears()
    {
        if (!$this->relationLoaded('experiences')) {
            return null;
        }
        
        $totalYears = 0;
        
        foreach ($this->experiences as $experience) {
            $startDate = \Carbon\Carbon::parse($experience->start_date);
            $endDate = $experience->currently_work_here ? now() : \Carbon\Carbon::parse($experience->end_date);
            
            if ($startDate && $endDate) {
                $totalYears += $startDate->diffInDays($endDate) / 365;
            }
        }
        
        return round($totalYears, 1);
    }
    
    /**
     * Get the highest education level
     *
     * @return string|null
     */
    protected function getHighestEducation()
    {
        if (!$this->relationLoaded('educations') || $this->educations->isEmpty()) {
            return null;
        }
        
        // Define education level hierarchy
        $educationLevels = [
            'High School' => 1,
            'Diploma' => 2,
            'Associate Degree' => 3,
            'Bachelors Degree' => 4,
            'Masters Degree' => 5,
            'PhD' => 6,
            'Post Doctoral' => 7
        ];
        
        $highestLevel = null;
        $highestRank = 0;
        
        foreach ($this->educations as $education) {
            $rank = $educationLevels[$education->degree] ?? 0;
            if ($rank > $highestRank) {
                $highestRank = $rank;
                $highestLevel = $education->degree;
            }
        }
        
        return $highestLevel;
    }
}
