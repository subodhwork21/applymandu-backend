<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
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
            'type' => $this->getNotificationType(),
            'data' => $this->data,
            'read_at' => $this->read_at,
            'created_at' => $this->created_at,
            'time_ago' => $this->created_at->diffForHumans(),
        ];
    }
    
    /**
     * Get a user-friendly notification type
     *
     * @return string
     */
    private function getNotificationType()
    {
        $type = class_basename($this->type);
        
        // Convert camel case to spaces
        return preg_replace('/(?<!^)[A-Z]/', ' $0', $type);
    }
}
