<?php

namespace App\Http\Controllers\API\Activity;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\ActivityTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class EmployerActivityController extends Controller
{
    use ActivityTrait;
    public function viewJobSeekerProfile(Request $request, $jobSeekerId){

       $validator = Validator::make(['jobSeekerId' => $jobSeekerId ], [
            'jobSeekerId' => 'required|integer|exists:users,id',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validator->errors()->first()
            ], 422);
        }
        $user = Auth::user();
        $jobSeeker = User::find($jobSeekerId);
      
        if(!$user->whereHas('roles', function ($q) {
            $q->where('name', 'employer');
        }) && !$jobSeeker->whereHas('roles', function ($q) {
            $q->where('name', 'jobseeker');
        })){
            return response()->json([
                'error' => true,
                'message' => 'You are not authorized to view this activity'
            ], 403);
        }

        $result = $this->recordProfileView($user, $jobSeeker);

        return $result != null ? response()->json([
            'success' => true,
            'message' => 'Profile viewed successfully',
            
        ]) : response()->json([
            'success' => true,
        ], 409);

        
    }
}
