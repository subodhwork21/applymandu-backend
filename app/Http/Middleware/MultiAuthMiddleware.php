<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class MultiAuthMiddleware
{
    public function handle(Request $request, Closure $next)
    {
        if (Auth::guard('api')->check()) {
            $user = Auth::guard('api')->user();
            Auth::setUser($user);

            if ($request->is('api/broadcasting/auth')) {
                Log::debug('Channel authorization attempt', [
                    'user_id' => $user->id,
                    'channel' => $request->input('channel_name'),
                    'raw_request' => $request->all()
                ]);
            }

            return $next($request);
        }

        Log::debug('User not authenticated');
        return response()->json(['error' => 'Unauthenticated.'], 401);
    }
}
