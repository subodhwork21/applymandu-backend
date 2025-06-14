<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApiKeyResource extends JsonResource
{
    private $showFullKey = false;

    public function __construct($resource, $showFullKey = false)
    {
        parent::__construct($resource);
        $this->showFullKey = $showFullKey;
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'key' => $this->showFullKey ? $this->getOriginal('key') : $this->masked_key,
            'key_prefix' => $this->key_prefix,
            'permissions' => $this->permissions,
            'status' => $this->status,
            'usage_count' => $this->usage_count,
            'monthly_limit' => $this->monthly_limit,
            'current_month_usage' => $this->current_month_usage,
            'usage_percentage' => $this->usage_percentage,
            'last_used_at' => $this->last_used_at?->toISOString(),
            'last_used_ip' => $this->last_used_ip,
            'expires_at' => $this->expires_at?->toISOString(),
            'is_expired' => $this->is_expired,
            'days_until_expiry' => $this->days_until_expiry,
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            'formatted_created_at' => $this->created_at->format('M j, Y'),
            'formatted_last_used' => $this->last_used_at?->format('M j, Y g:i A'),
            'can_make_request' => $this->canMakeRequest(),
        ];
    }

    public static function withFullKey($resource)
    {
        return new static($resource, true);
    }
}
