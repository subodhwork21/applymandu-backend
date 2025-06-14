<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyResource extends JsonResource
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
            'description' => $this->description,
            'website_url' => $this->website_url,
            'linkedin_url' => $this->linkedin_url,
            'employee_count' => [
                'min' => $this->employee_count_min,
                'max' => $this->employee_count_max,
                'formatted' => $this->formatEmployeeCount(),
            ],
        ];
    }
}
