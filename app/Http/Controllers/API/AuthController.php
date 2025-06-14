<?php

namespace App\Http\Controllers\API;

use Illuminate\Support\Str;
use App\Events\EmailVerificationRequested;
use App\Http\Controllers\Controller;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Mail\ForgotPasswordMail;
use App\Models\User;
use App\Traits\FileUploadTrait;
use App\Traits\HandlesRegistration;
use App\Traits\HandlesUserAuthentication;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Email;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{

    use FileUploadTrait;

   
    use HandlesRegistration;
    use HandlesUserAuthentication;

    public function login(Request $request){
        return $this->loginWithUserType($request, "jobseeker");
    }


    public function register(RegisterRequest $request){

        return $this->registerUser($request, User::class, EmailVerificationRequested::class, $request->accountType);

    }


    public function forgotPassword(Request $request){
        $validation = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
        ]);
        if($validation->fails()){
            return response()->json([
                'error' => true,
                'message' => 'Validation failed',
                'errors' => $validation->errors()
            ], 422);
        }

        $user = User::where('email', $request->email)->first();
        
        if(!$user){
            return response()->json([
                'error' => true,
                'message' => 'User not found'
            ], 404);
        }


        $token = Str::random(10);
        $user->reset_password_token = $token;

        $user->save();

        // send email to user with reset password link

        Mail::to($user->email)->send(new ForgotPasswordMail($user, $user->reset_password_token));

        return response()->json([
            'success' => true,
            'message' => 'Password reset link sent to your email'
        ],200);

    }


    public function resetPassword(Request $request){
        $validation = Validator::make($request->all(), [
            'token' => 'required|string',
            'password' => 'required|string|min:8',
            'password_confirmation' => 'required|string|same:password',
        ]);

        if($validation->fails()){
            return response()->json([
                'error' => true,
                'message' => 'Validation failed',
                'errors' => $validation->errors()
            ], 422);
        }

        $user = User::where('reset_password_token', $request->token)->first();
        if(!$user){
            return response()->json([
                'error' => true,
                'message' => 'Invalid token'
            ], 404);
        }

        $user->password = bcrypt($request->password);
        $user->reset_password_token = null;
        $user->save();
        return response()->json([
            'success' => true,
            'message' => 'Password reset successfully'
        ], 201);



    }


    public function changePassword(Request $request){
        $validation = Validator::make($request->all(), [
            'current_password' => 'required|string',
            'password' => 'required|string|min:8',
            'password_confirmation' => 'required|string|same:password',
        ]);

        if($validation->fails()){
            return response()->json([
                'error' => true,
                'message' => 'Validation failed',
                'errors' => $validation->errors()
            ], 422);
        }

        $user = Auth::user();
        if(!Hash::check($request->current_password, $user->password)){
            return response()->json([
                'error' => true,
                'message' => 'Current password does not match'
            ], 400);
        }

        $user->password = bcrypt($request->password);
        $user->save();
        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully'
        ], 200);

    }


    public function verifyEmail(Request $request){

        $validation = Validator::make($request->all(), [
            'token' => 'required|string',
        ]);

        if($validation->fails()){
            return response()->json([
                'error' => true,
                'message' => 'Validation failed',
                'errors' => $validation->errors()
            ], 422);
        }

        $user = User::where('verify_email_token', $request->token)->first();
        if(!$user){
            return response()->json([
                'error' => true,
                'message' => 'Invalid token'
            ], 404);
        }
        $user->email_verified_at = now();
        $user->save();
        return response()->json([
            'success' => true,
            'message' => 'Email verified successfully'
        ],200);


    }

    public function logout(Request $request){
        $user = Auth::user();
        if($user){
           $request->user()->token()->revoke();
            return response()->json([
                'success' => true,
                'message' => 'Logged out successfully'
            ], 200);
        }
        
    }


    public function allUsers(){
        $allUser = User::all();
        return response()->json([
            'success'=> true,
            'data' => $allUser,
        ], 200);
    }

    public function userProfile(){
        $user = User::with('socialLinks')->find(Auth::user()->id);
        if($user){
            return response()->json([
                'success' => true,
                'data' => new UserResource($user),
            ], 200);
        }
        return response()->json([
            'success' => false,
            'message' => 'User not found'
        ], 404);
    }

    public function deactivateAccount(Request $request){
        $user = Auth::user();
        if($user){
            $user->is_active = 0;
            $user->save();
            return response()->json([
                'success' => true,
                'message' => 'Account deactivated successfully'
            ], 200);
        }
        return response()->json([
            'error' => true,
            'message' => 'User not found'
        ], 404);
    }

    public function activateAccount(Request $request){
        $user = Auth::user();
        if($user){
            $user->is_active = 1;
            $user->save();
            return response()->json([
                'success' => true,
                'message' => 'Account activated successfully'
            ], 200);
        }
        return response()->json([
            'error' => true,
            'message' => 'User not found'
        ], 404);
    }

    public function uploadImage(Request $request){
        $validation = Validator::make($request->all(), [
            'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if($validation->fails()){
            return response()->json([
                'error' => true,
                'message' => 'Validation failed',
                'errors' => $validation->errors()
            ], 422);
        }

        $user = Auth::user();

          if ($request->hasFile('image')) {
            $path = $this->uploadJobSeekerImage($request->file('image'));
            $validatedData['image'] = $path;
        }

        if($user){
            $user->image = $path;
            $user->save();
            return response()->json([
                'success' => true,
                'message' => 'Image uploaded successfully',
                'data' => new UserResource($user),
            ], 200);
        }
       
        return response()->json([
            'error' => true,
            'message' => 'User not found'
        ], 404);
    }



    public function loginWithToken(Request $request){
        $user = User::with("experiences")->find(Auth::user()?->id);
        if($user){
            return response()->json([
                'success' => true,
                'data' => new UserResource($user),
                'is_employer' => $user->hasRole('employer'),
                'token' => $request->bearerToken()
            ], 200);
        }
        return response()->json([
            'error' => true,
            'message' => 'User not found'
        ], 404);
    }


    public function userPreference(){
        $user = User::with('preferences')->find(Auth::user()->id);
        if($user){
            return response()->json([
                'success' => true,
                'data' => new UserResource($user),
            ], 200);
        }
        return response()->json([
            'error' => true,
            'message' => 'User not found'
        ], 404);
    }


    public function updatePreference(Request $request){
        $validator = Validator::make($request->all(), [
            "first_name" => 'string',
            "last_name" => 'string',
            'email'=> 'email|unique:users,email,'.Auth::user()->id,
            'phone'=> 'string|unique:users,phone,'.Auth::user()->id,
            'visible_to_employers' => 'boolean',
            'appear_in_search_results' => 'boolean',
            'show_contact_info' => 'boolean',
            'show_online_status' => 'boolean',
            'allow_personalized_recommendations' => 'boolean',
            'email_job_matches' => 'boolean',
            'sms_application_updates' => 'boolean',
            'subscribe_to_newsletter' => 'boolean',
            'immediate_availability' => 'boolean|nullable',
            'availability_date' => 'date|nullable|after:today',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }
        // if(!$request->has('immediate_availability') && !$request->has('availability_date')){
        //     return response()->json([
        //         'error' => true,
        //         'errors' => [
        //             'immediate_availability' => ['Immediate availability and availability date are mutually exclusive. Choose one or the other.'],
        //             'availability_date' => ['Immediate availability and availability date are mutually exclusive. Choose one or the other.'],
        //         ],
        //         'message' => 'Validation failed'
        //     ], 422);
        // }
        $user = User::find(Auth::user()->id);
        $user->first_name = $request->first_name;
        $user->last_name = $request->last_name;
        $user->email = $request->email;
        $user->phone = $request->phone;
        $user->save();

        if($user){
            if($user?->preferences){

                $user->preferences->update($request->except('first_name', 'last_name', 'email', 'phone'));
            }
            else{
                $user->preferences()->create($request->except('first_name', 'last_name', 'email', 'phone'));
            }
            return response()->json([
                'success' => true,
                'message' => 'Preference updated successfully',
                'data' => new UserResource($user),
            ], 200);
        }
        return response()->json([
            'error' => true,
            'message' => 'User not found'
        ], 404);
    }
}
