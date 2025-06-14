<?php

namespace App\Http\Controllers\API;

use App\Helpers\StringHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\AmJobResource;
use App\Http\Resources\ApplicationResource;
use App\Models\Activity;
use App\Models\AmJob;
use App\Models\Application;
use App\Models\JobSeekerStats;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class DashboardJobSeekerController extends Controller
{

    protected $profileCompletionService;
    
    public function __construct(User $profileCompletionService)
    {
        $this->profileCompletionService = $profileCompletionService;
    }

    public function getTotalApplications()
    {
        $totalApplications = auth()->user()->applications()->count();
        // $totalInterviews = auth()->user()->applications()
        //     ->whereHas('applicationStatusHistory', function ($query) {
        //         $query->where('status', 'interview');
        //     })
        //     ->count();

        $totalInterviews = Activity::where("type", 'interview_scheduled')->where("user_id", Auth::user()->id)->count();

        $savedJobs = Activity::where("type", 'job_saved')->where("user_id", Auth::user()->id)->count();

        return response()->json([
            'success' => true,
            'message' => 'Total applications fetched successfully',
            'count' => [
                'total_applications' => $totalApplications,
                'total_interviews' => $totalInterviews,
                'saved_jobs' => $savedJobs,
            ],
        ]);
    }

    public function recentApplications()
    {
        $recentApplications = Application::with("job.employer")->where("user_id", Auth::user()->id)
            ->orderBy('created_at', 'desc')
            ->take(5)                                                                                           
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Recent applications fetched successfully',
            'data' => ApplicationResource::collection($recentApplications),
        ]);
    }

    public function recommendedJobs()
    {
        return cache()->remember('user_' . auth()->id() . '_recommended_jobs', 60 * 60, function () {
            $user = auth()->user();
            $experiences = $user->experiences;
            $skills = $user->skills;

            // Build base query once
            $query = AmJob::with([
                "skills",
                "employer",
                "user" => function ($query) {
                    return $query->whereHas('roles', function ($q) {
                        $q->where('name', 'employer');
                    });
                }
            ])
                ->select(
                    'id',
                    'title',
                    'description',
                    'location',
                    'salary_min',
                    'salary_max',
                    'employment_type',
                    'created_at',
                    'employer_id'
                );

            // Get all jobs at once
            $jobs = $query->get();


            // Process job matching only once
            $relatedJobIds = collect();

            foreach ($experiences as $experience) {
                $titles = $jobs->map(function ($item) {
                    return [$item->title, $item->id];
                })->toArray();

                $descriptions = $jobs->map(function ($item) {
                    return [$item->description, $item->id];
                })->toArray();


                $resultTitle = StringHelper::findRelatedStrings($experience->position_title, $titles);
                $resultDescription = StringHelper::findRelatedStrings($experience->position_title, $descriptions);

                $relatedJobIds = $relatedJobIds->merge(collect(array_merge($resultTitle, $resultDescription)));
            }

            

            // Get unique job IDs
            $uniqueJobIds = $relatedJobIds->pluck("id")->unique();


            // Filter the already loaded jobs instead of making a new query
            $result = $jobs->whereIn('id', $uniqueJobIds);

            return response()->json([
                'success' => true,
                'message' => 'Recommended jobs fetched successfully',
                'data' => AmJobResource::collection($result)
            ]);
        });
    }

    public function applicationStats(){
        $user = Auth::user();
        $calculatedStats = JobSeekerStats::calculateStats();
        // $stats = JobSeekerStats::where('user_id', $user->id)->first();
        if (!$calculatedStats) {
            return response()->json([
                'success' => false,
                'message' => 'No stats found for this user',
            ], 404);
        }
        return response()->json([
            'success' => true,
            'message' => 'Stats fetched successfully',
            'data' => [
                'success_rate' => $calculatedStats['success_rate'],
                'response_rate' => $calculatedStats['response_rate'],
                'interview_rate' => $calculatedStats['interview_rate'],
            ],
        ]);
    }


    public function profileCompletion()
    {
        $user = Auth::user();
        $forceRefresh = false;
        $profileCompletion = $this->profileCompletionService->calculateProfileCompletion($user, $forceRefresh);

        return response()->json([
            'success' => true,
            'message' => 'Profile completion fetched successfully',
            'data' => [
                'profile_completion' => $profileCompletion,
            ],
        ]);
    }
}
