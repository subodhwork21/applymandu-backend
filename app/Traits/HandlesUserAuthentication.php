<?php

namespace App\Traits;

use App\Http\Resources\UserResource;
use App\Models\Scopes\ActiveUsersScope;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use ParagonIE\ConstantTime\Base32;

trait HandlesUserAuthentication
{


    public function loginWithUserType(Request $request, string $type)
    {
        $validation = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required'
        ]);

        if ($validation->fails()) {
            return response()->json([
                'error' => true,
                'message' => 'Validation failed',
                'errors' => $validation->errors()
            ], 422);
        }

        // Find user without the global scope
        $user = User::withoutGlobalScope(ActiveUsersScope::class)
            ->where('email', $request->email)
            ->first();


        // Check if user exists but is inactive
        if ($user && !$user->is_active) {
            return response()->json([
                'error' => true,
                'message' => 'Your account is inactive. Please contact support for assistance.',
                'inactive' => true
            ], 404);
        }

        if (!$user?->hasRole('employer') && !$user?->hasRole('jobseeker')) {
            return response()->json([
                'error' => true,
                'message' => "Invalid Credentials",
                'invalid_role' => true
            ], 404);
        }

        if ($user && $user->secret_key == null) {
            $secret = random_bytes(20);
            $user->secret_key = Base32::encodeUpper($secret);
            $user->save();
        }

        if ($user && Hash::check($request->password, $user->password)) {
            $token = $user->createToken($user->email)->accessToken;

            if ($user?->email_verified_at == null) {
                return response()->json([
                    'error' => true,
                    'message' => 'User not verified'
                ], 404);
            }
            return response()->json([
                'success' => true,
                'user' => new UserResource($user),
                'message' => 'Login successful',
                'token' => $token,
                'is_employer' => $user->hasRole("employer"),
                'two_fa' => $user->hasRole("employer") ? (bool)$user?->employerProfile?->two_fa : false
            ]);
        }
        return response()->json([
            'error' => true,
            'message' => 'Invalid credentials'
        ], 401);
    }
}
