<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityResource extends JsonResource
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
            'type' => $this->type,
            'subject_id' => $this->subject_id,
            "type" => $this->type,
            'description' => $this->description,
            'user_id' => $this->user_id,
            'created_at' => $this->created_at,
            'created_at_formatted' => $this->created_at ?
                ($this->created_at instanceof \Carbon\Carbon ?
                    $this->created_at->format('F d, Y') :
                    date('F d, Y', strtotime($this->created_at))) :
                null,
            'updated_at' => $this->updated_at,
        ];
    }
}
