<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminAuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // Find the user by email
        $user = User::where('email', $request->email)->first();

        // Check if user exists and password is correct
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials',
            ], 401);
        }

        // Check if user has admin role
        if (!$user->hasRole('admin')) {
            return response()->json([
                'message' => 'Unauthorized',
            ], 401);
        }

          $token = $user->createToken($user->email)->accessToken;

        return response()->json([
            'token' => $token,
            'user' => $user,
        ]);
    }
    
    public function logout(Request $request)
    {
        // Revoke the token that was used to authenticate the current request
        $request->user()->currentAccessToken()->delete();
        
        return response()->json([
            'message' => 'Successfully logged out',
        ]);
    }

    public function loginWithToken(Request $request){
        $user = User::find(Auth::user()?->id);
        if($user->hasRole('admin')){
            return response()->json([
                'success' => true,
                'data' => new UserResource($user),
                'is_admin' => $user->hasRole('admin'),
                'token' => $request->bearerToken()
            ], 200);
        }
        return response()->json([
            'error' => true,
            'message' => 'User not found'
        ], 404);
    }
}
