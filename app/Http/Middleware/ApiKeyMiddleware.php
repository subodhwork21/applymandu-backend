<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ApiKeyMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, ...$permissions): Response
    {
        $apiKey = $this->getApiKeyFromRequest($request);

        if (!$apiKey) {
            return response()->json([
                'success' => false,
                'message' => 'API key required'
            ], 401);
        }

        $apiKeyModel = ApiKey::findByKey($apiKey);

        if (!$apiKeyModel) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid API key'
            ], 401);
        }

        if (!$apiKeyModel->canMakeRequest()) {
            return response()->json([
                'success' => false,
                'message' => 'API key cannot make requests (inactive, expired, or over limit)',
                'details' => [
                    'status' => $apiKeyModel->status,
                    'is_expired' => $apiKeyModel->is_expired,
                    'usage_remaining' => $apiKeyModel->monthly_limit - $apiKeyModel->current_month_usage,
                ]
            ], 403);
        }

        // Check permissions if specified
        if (!empty($permissions)) {
            $hasPermission = false;
            foreach ($permissions as $permission) {
                if ($apiKeyModel->hasPermission($permission)) {
                    $hasPermission = true;
                    break;
                }
            }

            if (!$hasPermission) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient permissions',
                    'required_permissions' => $permissions,
                    'your_permissions' => $apiKeyModel->permissions,
                ], 403);
            }
        }

        // Set the authenticated user
        $employer = \App\Models\User::find($apiKeyModel->employer_id);
        if ($employer) {
            $request->setUserResolver(function () use ($employer) {
                return $employer;
            });
            Auth::setUser($employer);
        }

        // Store API key in request for usage tracking
        $request->attributes->set('api_key', $apiKeyModel);

        $startTime = microtime(true);
        $response = $next($request);
        $endTime = microtime(true);

        // Record API usage
        $apiKeyModel->recordUsage([
            'endpoint' => $request->path(),
            'method' => $request->method(),
            'response_status' => $response->getStatusCode(),
            'response_time_ms' => round(($endTime - $startTime) * 1000),
            'request_data' => $this->sanitizeRequestData($request->all()),
        ]);

        return $response;
    }

    /**
     * Get API key from request
     */
    private function getApiKeyFromRequest(Request $request): ?string
    {
        // Check Authorization header
        $authHeader = $request->header('Authorization');
        if ($authHeader && str_starts_with($authHeader, 'Bearer ')) {
            return substr($authHeader, 7);
        }

        // Check query parameter
        return $request->query('api_key');
    }

    /**
     * Sanitize request data for logging
     */
    private function sanitizeRequestData(array $data): array
    {
        $sensitiveFields = ['password', 'token', 'api_key', 'secret'];

        foreach ($sensitiveFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = '[REDACTED]';
            }
        }

        return $data;
    }
}
