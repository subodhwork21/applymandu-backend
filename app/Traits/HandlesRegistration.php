<?php

namespace App\Traits;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use ParagonIE\ConstantTime\Base32;

trait HandlesRegistration
{
    protected function registerUser(Request $request, $model, $verificationEvent, String $type)
    {
        DB::beginTransaction();

        try {
      

            $data = $request->validated();
            $user = User::where('email', $data['email'])?->first();
        
            $data['company_name'] = $type == "jobseeker" ? null : $data['company_name'];
            $data['first_name'] = $type == "jobseeker" ? $data['first_name'] : null;
            $data['last_name'] = $type == "jobseeker" ? $data['last_name'] : null;
            $data['password'] = Hash::make($data['password']);
            $verifyEmailToken = Str::random(10);
            $secret = random_bytes(20);
            $data['secret_key'] = Base32::encodeUpper($secret);
            $data['verify_email_token'] = $verifyEmailToken;

            $user = $model::create($data);
            if ($type) {
                $user->assignRole($type == 'employer' ? 'employer' : ($type == 'jobseeker' ? 'jobseeker' : 'admin'));
            }

            // $tokenResult = $user->createToken($user->email)?->accessToken;

            Event::dispatch(new $verificationEvent($user, $verifyEmailToken));

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Registered successfully. Please check your email to verify your account.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'error' => true,
                'message' => 'Registration failed: ' . $e->getMessage()
            ], 500);
        }
    }
}

