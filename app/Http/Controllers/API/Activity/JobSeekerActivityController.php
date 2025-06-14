<?php

namespace App\Http\Controllers\API\Activity;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityResource;
use App\Http\Resources\AmJobResource;
use App\Models\Activity;
use App\Models\AmJob;
use App\Traits\ActivityTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class JobSeekerActivityController extends Controller
{
    use ActivityTrait;
    public function saveJobs(Request $request, $jobId)
    {

        $user = Auth::user();

        $job = AmJob::find($jobId);

        if (!$job) {
            return response()->json([
                'error' => true,
                'message' => 'Job not found'
            ], 404);
        }


        $this->recordActivityByType("job_saved", $job, $user->id);
 $cacheKey = 'user_' . auth()->id() . '_recommended_jobs';
    cache()->forget($cacheKey);
        return response()->json([
            'success' => true,
            'message' => 'Job saved successfully',
        ]);
    }

    public function unsaveJobs(Request $request, $jobId)
    {
        $user = Auth::user();

        $job = AmJob::find($jobId);

        if (!$job) {
            return response()->json([
                'error' => true,
                'message' => 'Job not found'
            ], 404);
        }

        Activity::where('user_id', $user->id)
            ->where('type', 'job_saved')
            ->where('subject_id', $job->id)
            ->delete();
         $cacheKey = 'user_' . auth()->id() . '_recommended_jobs';
    cache()->forget($cacheKey);

        return response()->json([
            'success' => true,
            'message' => 'Job unsaved successfully',
        ]);
    }


public function allSavedJobs(Request $request)
{
    $user = Auth::user();

    $savedJobs = Activity::where('user_id', $user?->id)
        ->where('type', 'job_saved')
        ->orderBy('created_at', 'desc')
        ->paginate(10);

    $jobs = $savedJobs->map(function ($activity) {
       return AmJob::with("skills")->find($activity->subject_id);
    })->filter(); // This will remove null values

    return response()->json([
        'success' => true,
        'message' => 'Saved jobs fetched successfully',
        'data' => AmJobResource::collection($jobs)
    ]);
}

    public function viewJob(Request $request, $jobId)    {
        $user = Auth::user();

        $job = AmJob::find($jobId);

        if (!$job) {
            return response()->json([
                'error' => true,
                'message' => 'Job not found'
            ], 404);
        }

        if($user){
            $this->recordActivityByType("job_viewed", $job, $user->id);
        }

        return response()->json([
            'success' => true,
            'message' => 'Job viewed successfully',
        ]);
   
    }

    public function recentActivity(Request $request){
        $activity = Activity::recentJobSeekerActivity();
        return ActivityResource::collection($activity);
    }
}
