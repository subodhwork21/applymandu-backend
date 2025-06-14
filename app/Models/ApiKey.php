<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ApiKey extends Model
{
    use HasFactory;

    protected $fillable = [
        'employer_id',
        'name',
        'key_hash',
        'key_prefix',
        'permissions',
        'status',
        'last_used_at',
        'last_used_ip',
        'usage_count',
        'monthly_limit',
        'current_month',
        'current_month_usage',
        'expires_at',
    ];

    protected $casts = [
        'permissions' => 'array',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
        'current_month' => 'date',
    ];

    protected $hidden = [
        'key_hash',
    ];

    protected $appends = [
        'masked_key',
        'usage_percentage',
        'is_expired',
        'days_until_expiry',
    ];

    // Available permissions
    const PERMISSIONS = [
        'read:jobs' => 'View job listings',
        'write:jobs' => 'Create and update jobs',
        'read:applications' => 'View applications',
        'write:applications' => 'Update application status',
        'read:analytics' => 'Access analytics data',
    ];

    // Status constants
    const STATUS_ACTIVE = 'active';
    const STATUS_INACTIVE = 'inactive';

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (is_null($model->permissions)) {
                $model->permissions = [];
            }
            if (is_null($model->current_month)) {
                $model->current_month = now()->format('Y-m-01');
            }
        });
    }


    /**
     * Get the employer that owns the API key
     */


    public function employer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'employer_id');
    }

    /**
     * Get the usage logs for this API key
     */
    public function usageLogs(): HasMany
    {
        return $this->hasMany(ApiUsageLog::class);
    }

    /**
     * Generate a new API key
     */
    public static function generateKey(): string
    {
        return 'am_' . Str::random(32);
    }

    /**
     * Create a new API key
     */
    public static function createKey(array $data): self
    {
        $key = self::generateKey();

        return self::create([
            'employer_id' => $data['employer_id'],
            'name' => $data['name'],
            'key_hash' => hash('sha256', $key),
            'key_prefix' => substr($key, 0, 10),
            'permissions' => $data['permissions'] ?? [],
            'monthly_limit' => $data['monthly_limit'] ?? 10000,
            'expires_at' => $data['expires_at'] ?? null,
        ]);
    }

    /**
     * Find API key by the actual key
     */
    public static function findByKey(string $key): ?self
    {
        $hash = hash('sha256', $key);
        return self::where('key_hash', $hash)
            ->where('status', self::STATUS_ACTIVE)
            ->first();
    }

    /**
     * Get masked key for display
     */
    public function getMaskedKeyAttribute(): string
    {
        return $this->key_prefix . str_repeat('*', 22);
    }

    /**
     * Get usage percentage
     */
    public function getUsagePercentageAttribute(): float
    {
        if ($this->monthly_limit <= 0) {
            return 0;
        }

        return round(($this->current_month_usage / $this->monthly_limit) * 100, 2);
    }

    /**
     * Check if key is expired
     */
    public function getIsExpiredAttribute(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Get days until expiry
     */
    public function getDaysUntilExpiryAttribute(): ?int
    {
        if (!$this->expires_at) {
            return null;
        }

        return max(0, now()->diffInDays($this->expires_at, false));
    }

    /**
     * Check if key has permission
     */
    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions);
    }

    /**
     * Check if key can make requests (not expired, active, under limit)
     */
    public function canMakeRequest(): bool
    {
        if ($this->status !== self::STATUS_ACTIVE) {
            return false;
        }

        if ($this->is_expired) {
            return false;
        }

        // Reset monthly usage if new month
        $this->resetMonthlyUsageIfNeeded();

        return $this->current_month_usage < $this->monthly_limit;
    }

    /**
     * Record API usage
     */
    public function recordUsage(array $data = []): void
    {
        // Reset monthly usage if needed
        $this->resetMonthlyUsageIfNeeded();

        // Update usage counters
        $this->increment('usage_count');
        $this->increment('current_month_usage');

        // Update last used info
        $this->update([
            'last_used_at' => now(),
            'last_used_ip' => request()->ip(),
        ]);

        // Log the usage
        ApiUsageLog::create([
            'api_key_id' => $this->id,
            'employer_id' => $this->employer_id,
            'endpoint' => $data['endpoint'] ?? request()->path(),
            'method' => $data['method'] ?? request()->method(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'response_status' => $data['response_status'] ?? 200,
            'response_time_ms' => $data['response_time_ms'] ?? 0,
            'request_data' => $data['request_data'] ?? null,
            'response_data' => $data['response_data'] ?? null,
        ]);
    }

    /**
     * Reset monthly usage if new month
     */
    private function resetMonthlyUsageIfNeeded(): void
    {
        $currentMonth = now()->format('Y-m-01');

        if ($this->current_month->format('Y-m-01') !== $currentMonth) {
            $this->update([
                'current_month' => $currentMonth,
                'current_month_usage' => 0,
            ]);
        }
    }

    /**
     * Regenerate the API key
     */
    public function regenerate(): string
    {
        $newKey = self::generateKey();

        $this->update([
            'key_hash' => hash('sha256', $newKey),
            'key_prefix' => substr($newKey, 0, 10),
            // 'usage_count' => 0,
            // 'current_month_usage' => 0,
            'current_month' => now()->format('Y-m-01'),
            'last_used_at' => null,
            'last_used_ip' => null,
        ]);

        return $newKey;
    }

    /**
     * Scope for active keys
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Scope for non-expired keys
     */
    public function scopeNotExpired($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });
    }
}
