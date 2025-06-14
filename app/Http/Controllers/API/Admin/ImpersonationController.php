<?php

namespace App\Http\Controllers\API\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Laravel\Passport\PersonalAccessTokenFactory;
use Laravel\Passport\TokenRepository;

class ImpersonationController extends Controller
{
    public function impersonate(Request $request, User $user, PersonalAccessTokenFactory $tokenFactory, TokenRepository $tokenRepository)
    {
        $admin = $request->user();

        // Check if admin is authorized
        if (!$admin->hasRole('admin')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Check if the target user is an employer
        if (!$user->hasRole('employer') && !$user->hasRole("jobseeker")) {
            return response()->json([
                'success' => false,
                'message' => 'The selected user is not an employer. You can only impersonate employer accounts.'
            ], 400);
        }

        // Verify that the employer account is active
        if (!$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'This employer account is inactive or blocked.'
            ], 400);
        }

        // Create a token for the user being impersonated
        $token = $tokenFactory->make(
            $user->getKey(),
            'impersonation-token', // token name
            ['*'], // scopes
            now()->addHours(1) // expiration
        );

        // Store the impersonator_id to track who is impersonating
        $tokenModel = $tokenRepository->find($token->token->id);
        $tokenModel->impersonator_id = $admin->id;
        $tokenModel->save();

        // Cache admin id for 1 hour to remember who started the impersonation
        Cache::put('impersonating_admin_' . $user->id, $admin->id, now()->addHours(1));

        return response()->json([
            'success' => true,
            'message' => 'Impersonation started successfully',
            'token' => $token->accessToken,
            'token_type' => 'Bearer',
            'expires_in' => 3600,
            'employer' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'company_name' => $user->company_name ?? $user->employer_profile->company_name ?? null,
            ]
        ]);
    }

    public function leaveImpersonation(Request $request)
    {
        $user = $request->user();
        
        // Delete all tokens for the current user
        $request->user()->tokens()->delete();
        
        // Get the admin ID from cache if available
        $adminId = Cache::pull('impersonating_admin_' . $user->id);
        
        // If we have the admin ID, we could potentially return their token here
        // or provide information about who was impersonating
        
        return response()->json([
            'success' => true,
            'message' => 'Impersonation ended successfully',
            'admin_id' => $adminId
        ], 200);
    }
}
