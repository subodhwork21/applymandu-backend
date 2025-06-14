<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreApiKeyRequest;
use App\Http\Requests\UpdateApiKeyRequest;
use App\Http\Resources\ApiKeyResource;
use App\Http\Resources\ApiUsageLogResource;
use App\Models\ApiKey;
use App\Models\ApiUsageLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ApiKeyController extends Controller
{
    /**
     * Display a listing of API keys
     */
    public function index(): JsonResponse
    {

        $apiKeys = ApiKey::where('employer_id', auth()->id())
                         ->orderBy('created_at', 'desc')
                         ->get();

        return response()->json([
            'success' => true,
            'data' => ApiKeyResource::collection($apiKeys),
            'message' => 'API keys retrieved successfully'
        ]);
    }

    /**
     * Store a newly created API key
     */
    public function store(StoreApiKeyRequest $request): JsonResponse
    {
        try {
            $auth_id = Auth::id();
            // Check if user has reached the limit of API keys
            $existingKeysCount = ApiKey::where('employer_id', $auth_id)->count();
            if ($existingKeysCount >= 10) { // Limit to 10 keys per employer
                return response()->json([
                    'error' => true,
                    'message' => 'Maximum number of API keys reached (10)'
                ], 422);
            }

            $apiKey = ApiKey::createKey(array_merge($request->validated(), ['employer_id' => $auth_id, 'expires_at'=> now()->addDays(30)]));
            
            // Get the actual key for one-time display
            $actualKey = ApiKey::generateKey();
            $apiKey->update([
                'key_hash' => hash('sha256', $actualKey),
                'key_prefix' => substr($actualKey, 0, 10),
            ]);

            // Return the key with the actual key value (only shown once)
            $resource = ApiKeyResource::withFullKey($apiKey);
            $resourceArray = $resource->toArray(request());
            $resourceArray['key'] = $actualKey; // Override with actual key

            return response()->json([
                'success' => true,
                'data' => $resourceArray,
                'message' => 'API key created successfully. Please save this key as it will not be shown again.'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => 'Failed to create API key',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified API key
     */
    public function show(ApiKey $apiKey): JsonResponse
    {
        if ($apiKey->employer_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to API key'
            ], 403);
        }

        return response()->json([
            'success' => true,
            'data' => new ApiKeyResource($apiKey),
            'message' => 'API key retrieved successfully'
        ]);
    }

    /**
     * Update the specified API key
     */
    public function update(UpdateApiKeyRequest $request, ApiKey $apiKey): JsonResponse
    {
        try {
            $apiKey->update($request->validated());

            return response()->json([
                'success' => true,
                'data' => new ApiKeyResource($apiKey),
                'message' => 'API key updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => 'Failed to update API key',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified API key
     */
    public function destroy(ApiKey $apiKey): JsonResponse
    {
        if ($apiKey->employer_id !== auth()->id()) {
            return response()->json([
                'error' => true,
                'message' => 'Unauthorized access to API key'
            ], 403);
        }

        try {
            $apiKey->delete();

            return response()->json([
                'success' => true,
                'message' => 'API key deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => 'Failed to delete API key',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Regenerate API key
     */
    public function regenerate(ApiKey $apiKey): JsonResponse
    {
        if ($apiKey->employer_id !== auth()->id()) {
            return response()->json([
                'error' => true,
                'message' => 'Unauthorized access to API key'
            ], 403);
        }

        try {
            $newKey = $apiKey->regenerate();

            $resource = new ApiKeyResource($apiKey);
            $resourceArray = $resource->toArray(request());
            $resourceArray['key'] = $newKey; // Show the new key once

            return response()->json([
                'success' => true,
                'data' => $resourceArray,
                'message' => 'API key regenerated successfully. Please save this key as it will not be shown again.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => 'Failed to regenerate API key',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle API key status
     */
    public function toggleStatus(ApiKey $apiKey): JsonResponse
    {
        if ($apiKey->employer_id !== auth()->id()) {
            return response()->json([
                'error' => true,
                'message' => 'Unauthorized access to API key'
            ], 403);
        }

        try {
            $newStatus = $apiKey->status === ApiKey::STATUS_ACTIVE 
                ? ApiKey::STATUS_INACTIVE 
                : ApiKey::STATUS_ACTIVE;

            $apiKey->update(['status' => $newStatus]);

            return response()->json([
                'success' => true,
                'data' => new ApiKeyResource($apiKey),
                'message' => "API key {$newStatus} successfully"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => true,
                'message' => 'Failed to toggle API key status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get API usage statistics
     */
    public function statistics(): JsonResponse
    {
        $employerId = auth()->id();
        
        // Get current month usage
        $currentMonth = now()->format('Y-m-01');
        $totalMonthlyUsage = ApiKey::where('employer_id', $employerId)
                                  ->sum('current_month_usage');
        
        $totalMonthlyLimit = ApiKey::where('employer_id', $employerId)
                                  ->sum('monthly_limit');

        // Get usage by endpoint for current month
        $endpointUsage = ApiUsageLog::where('employer_id', $employerId)
                                   ->where('created_at', '>=', $currentMonth)
                                   ->select('endpoint', DB::raw('count(*) as count'))
                                   ->groupBy('endpoint')
                                   ->orderBy('count', 'desc')
                                   ->limit(10)
                                   ->get();

        // Get daily usage for current month
        $dailyUsage = ApiUsageLog::where('employer_id', $employerId)
                                ->where('created_at', '>=', $currentMonth)
                                ->select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as count'))
                                ->groupBy('date')
                                ->orderBy('date')
                                ->get();

        // Get response status distribution
        $statusDistribution = ApiUsageLog::where('employer_id', $employerId)
                                        ->where('created_at', '>=', $currentMonth)
                                        ->select(
                                            DB::raw('CASE 
                                                WHEN response_status >= 200 AND response_status < 300 THEN "Success"
                                                WHEN response_status >= 400 AND response_status < 500 THEN "Client Error"
                                                WHEN response_status >= 500 THEN "Server Error"
                                                ELSE "Other"
                                            END as status_group'),
                                            DB::raw('count(*) as count')
                                        )
                                        ->groupBy('status_group')
                                        ->get();

        $stats = [
            'total_api_keys' => ApiKey::where('employer_id', $employerId)->count(),
            'active_api_keys' => ApiKey::where('employer_id', $employerId)->active()->count(),
            'total_monthly_usage' => $totalMonthlyUsage,
            'total_monthly_limit' => $totalMonthlyLimit,
            'usage_percentage' => $totalMonthlyLimit > 0 ? round(($totalMonthlyUsage / $totalMonthlyLimit) * 100, 2) : 0,
            'requests_this_month' => ApiUsageLog::where('employer_id', $employerId)
                                               ->where('created_at', '>=', $currentMonth)
                                               ->count(),
            'requests_today' => ApiUsageLog::where('employer_id', $employerId)
                                          ->whereDate('created_at', today())
                                          ->count(),
            'average_response_time' => ApiUsageLog::where('employer_id', $employerId)
                                                 ->where('created_at', '>=', $currentMonth)
                                                 ->avg('response_time_ms'),
            'endpoint_usage' => $endpointUsage,
            'daily_usage' => $dailyUsage,
            'status_distribution' => $statusDistribution,
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
            'message' => 'API statistics retrieved successfully'
        ]);
    }

    /**
     * Get API usage logs
     */
    public function usageLogs(Request $request): JsonResponse
    {
        $request->validate([
            'api_key_id' => 'nullable|exists:api_keys,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'endpoint' => 'nullable|string',
            'method' => 'nullable|string',
            'status' => 'nullable|integer',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        $query = ApiUsageLog::where('employer_id', auth()->id())
                           ->with('apiKey:id,name');

        // Filter by API key
        if ($request->api_key_id) {
            $query->where('api_key_id', $request->api_key_id);
        }

        // Filter by date range
        if ($request->start_date) {
            $query->where('created_at', '>=', $request->start_date);
        }
        if ($request->end_date) {
            $query->where('created_at', '<=', $request->end_date . ' 23:59:59');
        }

        // Filter by endpoint
        if ($request->endpoint) {
            $query->where('endpoint', 'like', '%' . $request->endpoint . '%');
        }

        // Filter by method
        if ($request->method) {
            $query->where('method', $request->method);
        }

        // Filter by status
        if ($request->status) {
            $query->where('response_status', $request->status);
        }

        $perPage = $request->get('per_page', 20);
        $logs = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => ApiUsageLogResource::collection($logs),
            'pagination' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
            'message' => 'API usage logs retrieved successfully'
        ]);
    }

    /**
     * Get available permissions
     */
    public function permissions(): JsonResponse
    {
        $permissions = collect(ApiKey::PERMISSIONS)->map(function ($description, $key) {
            return [
                'key' => $key,
                'description' => $description,
                'category' => explode(':', $key)[1] ?? 'general',
            ];
        })->groupBy('category');

        return response()->json([
            'success' => true,
            'data' => $permissions,
            'message' => 'Available permissions retrieved successfully'
        ]);
    }

    /**
     * Test API key
     */

    public function testKey(Request $request): JsonResponse
    {
        $request->validate([
            'api_key' => 'required|string'
        ]);

        $apiKey = ApiKey::findByKey($request->api_key);

        if (!$apiKey) {
            return response()->json([
                'error' => true,
                'message' => 'Invalid API key'
            ], 401);
        }

        if ($apiKey->employer_id !== auth()->id()) {
            return response()->json([
                'error' => true,
                'message' => 'API key does not belong to you'
            ], 403);
        }

        if (!$apiKey->canMakeRequest()) {
            return response()->json([
                'error' => true,
                'message' => 'API key cannot make requests (inactive, expired, or over limit)'
            ], 403);
        }

        // Record test usage
        $apiKey->recordUsage([
            'endpoint' => 'test',
            'method' => 'POST',
            'response_status' => 200,
            'response_time_ms' => 0,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'API key is valid and working',
            'data' => [
                'key_name' => $apiKey->name,
                'permissions' => $apiKey->permissions,
                'usage_remaining' => $apiKey->monthly_limit - $apiKey->current_month_usage,
            ]
        ]);
    }
}
