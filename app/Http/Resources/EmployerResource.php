<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
                'id' => $this?->id,
                'company_name' => $this?->company_name,
                'email' => $this?->email,
                'phone' => $this?->phone,
                'image' => $this?->employerLogo,
                'email_verified_at' => $this?->email_verified_at,
                'employer_profile' => $this?->employerProfile,
                'created_at' => $this?->created_at,
                'updated_at' => $this?->updated_at,
                'jobs_count' => $this?->employerJobs?->count(),
                'active_jobs_count' => $this?->employerJobs?->where('status', 1)?->count(),
                'total_applicants' => $this?->applications?->count(),
        ];
    }
}
