<?php

namespace App\Http\Controllers\API;

use App\Events\EmailVerificationRequested;
use App\Http\Controllers\Controller;
use App\Http\Requests\EmployerRegisterRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\AmEmployer;
use App\Models\EmployerProfile;
use Illuminate\Support\Str;
use App\Models\User;
use App\Traits\FileUploadTrait;
use App\Traits\HandlesRegistration;
use App\Traits\HandlesUserAuthentication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AuthEmployerController extends Controller
{
    use FileUploadTrait;
    use HandlesUserAuthentication;
    use HandlesRegistration;

    public function login(Request $request)
    {
        return $this->loginWithUserType($request, 'employer');
    }


    public function register(RegisterRequest $request): JsonResponse
    {
        return $this->registerUser($request, User::class, EmailVerificationRequested::class, "employer");
    }


    public function allEmployers(Request $request): JsonResponse
    {
        $employers = User::whereHas("roles", function ($query) {
            $query->where("name", "employer");
        })->get();
        return response()->json([
            'success' => true,
            'employers' => $employers
        ]);
    }

    public function loginWithToken(Request $request)
    {
        $user = User::find(Auth::user()->id);
        if ($user->hasRole("jobseeker")) {
            return response()->json([
                'error' => true,
                'message' => 'User not found'
            ], 404);
        }
        if ($user) {
            return response()->json([
                'success' => true,
                'data' => new UserResource($user),
                'is_employer' => $user->hasRole('employer')
            ], 200);
        }
        return response()->json([
            'error' => true,
            'message' => 'User not found'
        ], 404);
    }

    public function employerSettings(Request $request)
    {
        $user = User::with("employerProfile")->find(Auth::user()->id);
        if ($user->hasRole("employer")) {
            return response()->json([
                'success' => true,
                'data' => new UserResource($user)
            ], 200);
        }
        return response()->json([
            'error' => true,
            'message' => 'User not found'
        ], 404);
    }


    public function updateSettings(Request $request)
    {

        $validation = Validator::make($request->all(), [
            'company_name' => "required|string|max:255",
            'size' => "required|string",
            'industry' => "required|string|max:255",
            'address' => "required|string|max:255",
            'website' => "required|string|max:255",
            'founded_year' => "required|integer|min:1900",
            'description' => "required|string|max:255",
            'email' => 'required|string|email|unique:users,email,' . Auth::user()->id,
            'phone' => 'required|integer|digits:10',
            'website' => 'required|string|max:255',
            'logo' => 'nullable|file|max:255'
        ]);
        if ($validation->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validation->errors()->first(),
            ], 422);
        }
        $validated_data = $validation->validated();

        if ($request->hasFile("logo")) {
            $path = $this->uploadEmployerLogo($request->file("logo"));
            $validated_data["logo"] = $path;
        }
        $user = User::find(Auth::user()->id);
        $user->company_name = $validated_data['company_name'];  
        $user->email = $validated_data['email'];
        $user->phone = $validated_data['phone'];
        $user->save();
        $employer_profile = EmployerProfile::where("user_id", $user->id)->first();
        if (!$employer_profile) {
            $employer_profile = new EmployerProfile();
            $employer_profile->user_id = $user->id;
            $employer_profile->size = $validated_data['size'];
            $employer_profile->industry = $validated_data['industry'];
            $employer_profile->address = $validated_data['address'];
            $employer_profile->website = $validated_data['website'];
            $employer_profile->founded_year = $validated_data['founded_year'];
            $employer_profile->description = $validated_data['description'];
            $employer_profile->logo = $validated_data['logo'] ?? null;
            $employer_profile->save();
        } else {
            $employer_profile->update(collect($validated_data)->except(['email', 'phone'])->toArray());
        }

        return response()->json([
            'success' => true,
            'message' => 'Settings updated successfully',
            'data' => $user
        ]);
    }

    public function employer2faUpdate(Request $request){
        $validation = Validator::make($request->all(), [
            '2fa' => 'required|boolean',
        ]);
        if ($validation->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validation->errors()->first(),
            ], 422);
        }
        $validated_data = $validation->validated();
        $user = User::find(Auth::user()->id);
        if(!$user->employerProfile){
            return response()->json([
                'error' => true,
                'message' => 'You do not have any profile, please update your profile first.',
            ], 400);
        }
         $user->employerProfile->update(['two_fa' => $validated_data['2fa']]);

        return response()->json([
            'success' => true,
            'message' => "Two factor authentication turned " . ($validated_data['2fa'] ? 'on' : 'off') . " successfully",
        ]);
    }
}
