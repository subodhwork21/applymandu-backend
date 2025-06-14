<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
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
            'first_name' => $this?->first_name,
            'last_name' => $this?->last_name,
            'company_name' => $this?->company_name,
            'role' => $this->whenLoaded('roles'),
            
            'email' => $this->email,
            'phone' => $this->phone,
            'image_path' => $this->company_name ? $this->employerLogo : $this->imagePath ,
            'profile' => $this->company_name ? $this->whenLoaded("employerProfile") : $this?->whenLoaded("jobSeekerProfile"),
            'experiences' => $this->whenLoaded('experiences'),
            'educations' => $this->whenLoaded('educations'),
            'languages' => $this->whenLoaded('languages'),
            'preferences' => $this->whenLoaded('preferences'),
            'skills' => $this->whenLoaded('skills'),
            'social_links' => $this->whenLoaded('socialLinks'),
            'immediate_availability' => $this->whenLoaded('preferences', function () {
                return $this->preferences->immediate_availability;
            }),
            'availability_date' => $this->whenLoaded('preferences', function () {
                if (!$this->preferences->availability_date) {
                    return null;
                }

                $availabilityDate = \Carbon\Carbon::parse($this->preferences->availability_date);
                $now = \Carbon\Carbon::now();

                if ($availabilityDate->isPast()) {
                    return null;
                }

                return intval(abs($availabilityDate->diffInDays($now)));

            }),


        ];
    }
}
