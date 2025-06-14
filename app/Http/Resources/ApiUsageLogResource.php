<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApiUsageLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'endpoint' => $this->endpoint,
            'method' => $this->method,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'response_status' => $this->response_status,
            'response_time_ms' => $this->response_time_ms,
            'created_at' => $this->created_at->toISOString(),
            'formatted_created_at' => $this->created_at->format('M j, Y g:i A'),
            'status_color' => $this->getStatusColor(),
            'method_color' => $this->getMethodColor(),
        ];
    }

    private function getStatusColor(): string
    {
        return match(true) {
            $this->response_status >= 200 && $this->response_status < 300 => 'text-green-600',
            $this->response_status >= 300 && $this->response_status < 400 => 'text-blue-600',
            $this->response_status >= 400 && $this->response_status < 500 => 'text-yellow-600',
            $this->response_status >= 500 => 'text-red-600',
            default => 'text-gray-600',
        };
    }

    private function getMethodColor(): string
    {
        return match($this->method) {
            'GET' => 'text-green-600',
            'POST' => 'text-blue-600',
            'PUT', 'PATCH' => 'text-yellow-600',
            'DELETE' => 'text-red-600',
            default => 'text-gray-600',
        };
    }
}
