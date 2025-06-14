<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\ApplicationResource;
use App\Models\Activity;
use App\Models\AmJob;
use App\Models\Application;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DashboardEmployerController extends Controller
{
    protected $profileCompletionService;

    public function __construct(User $profileCompletionService)
    {
        $this->profileCompletionService = $profileCompletionService;
    }
    public function getActiveJobsApplications(Request $request)
    {
        $activeJobs = AmJob::where('employer_id', auth()->user()->id)
            ->count();

        $activeApplications = Application::whereHas('job', function ($query) {
            $query->where('employer_id', auth()->user()->id);
        })->where('status', 1)->count();

        $hiredApplications = Application::whereHas('job', function ($query) {
            $query->where('employer_id', auth()->user()->id);
        })->where('status', 1)->whereHas("applicationStatusHistory", function ($query) {
            $query->where('status', 'accepted');
        })->count();

        return response()->json([
            'active_jobs' => $activeJobs,
            'active_applications' => $activeApplications,
            'hired_applications' => $hiredApplications
        ]);
    }

    public function getRecentApplicatins(Request $request)
    {
        $recentApplications = Application::whereHas('job', function ($query) {
            $query->where('employer_id', auth()->user()->id);
        })->orderBy('created_at', 'desc')->take(5)->get();

        return ApplicationResource::collection($recentApplications);
    }

    public function getActiveJobListing(Request $request)
    {
        $activeJobs = AmJob::with([
            "skills"
        ])->where('employer_id', auth()->user()->id)
            ->where('status', 1)
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 10));


        $activeJobs->getCollection()->transform(function ($job) {
            return [
                'id' => $job->id,
                'title' => $job->title,
                'location' => $job->location,
                'department' => $job->department,
                'experience_level' => $job->experience_level,
                'company_name' => $job->company_name,
                'description' => $job->description,
                'is_remote' => $job->is_remote,
                'employment_type' => $job->employment_type,
                'salary_min' => $job->salary_min,
                'salary_max' => $job->salary_max,
                'location_type' => $job->location_type,
                'posted_date' => $job->posted_date,
                'status' => $job->status,
                'skills' => $job->skills->pluck('name')->toArray(),
                'responsibilities' => json_decode($job->responsibilities),
                'requirements' => json_decode($job->requirements),
                'benefits' => json_decode($job->benefits),
                'applicants_count' => $job->applications()->count(),
                'posted' => $job->created_at,
                'slug' => $job->slug,
                'application_deadline' => $job->application_deadline,
                'views_count' => Activity::where('subject_type', AmJob::class)
                    ->where('subject_id', $job->id)
                    ->where('type', 'job_viewed')
                    ->count(),
            ];
        });

        return response()->json([
            'active_jobs' => $activeJobs,
        ]);
    }

    public function profileCompletion(Request $request, $id)
    {
        $user = User::find($id);
        if (!$user->hasRole("jobseeker")) {
            return response()->json([
                'success' => false,
                'message' => 'User is not a job seeker',
            ]);
        }
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



    public function applicationStats(Request $request)
    {
        $employerId = auth()->user()->id;
        $timeframe = $request->input('timeframe', '5days'); // Default to 5 days if not specified

        // Create a cache key unique to this employer and timeframe
        $cacheKey = "employer_stats_{$employerId}_{$timeframe}";

        // Try to get data from cache first (with 30 minute expiration)
        return Cache::remember($cacheKey, 30 * 60, function () use ($employerId, $timeframe) {
            // Get basic stats
            $activeJobs = AmJob::where('employer_id', $employerId)
                ->where('status', 1)
                ->count();

            $activeApplications = Application::whereHas('job', function ($query) use ($employerId) {
                $query->where('employer_id', $employerId);
            })->where('status', 1)->count();

            $hiredApplications = Application::whereHas('job', function ($query) use ($employerId) {
                $query->where('employer_id', $employerId);
            })->where('status', 1)
                ->whereHas("applicationStatusHistory", function ($query) {
                    $query->where('status', 'accepted');
                })->count();

            $startDate = Carbon::now()->subDays(4)->startOfDay();

            $timeFrame = $request->timeframe ?? '5days';
            $applicationTrends = $this->getApplicationTrendsByTimeframe($timeframe);
            // Calculate stats changes - adjust comparison period based on timeframe
            $comparisonStartDate = clone $startDate;
            $comparisonEndDate = Carbon::now()->subDays($startDate->diffInDays(Carbon::now()))->startOfDay();
            $comparisonStartDate = $comparisonStartDate->subDays($startDate->diffInDays(Carbon::now()))->startOfDay();

            $previousPeriodApplications = Application::whereHas('job', function ($query) use ($employerId) {
                $query->where('employer_id', $employerId);
            })
                ->where('created_at', '>=', $comparisonStartDate)
                ->where('created_at', '<', $comparisonEndDate)
                ->count();

            $applicationChangePercent = 0;
            if ($previousPeriodApplications > 0) {
                $applicationChangePercent = round(($activeApplications - $previousPeriodApplications) / $previousPeriodApplications * 100);
            }

            $newJobsThisWeek = AmJob::where('employer_id', $employerId)
                ->where('status', 1)
                ->where('created_at', '>=', Carbon::now()->subDays(7)->startOfDay())
                ->count();

            $newHiresThisMonth = Application::whereHas('job', function ($query) use ($employerId) {
                $query->where('employer_id', $employerId);
            })
                ->whereHas("applicationStatusHistory", function ($query) {
                    $query->where('status', 'accepted')
                        ->where('created_at', '>=', Carbon::now()->subDays(30)->startOfDay());
                })
                ->count();

            return response()->json([
                'active_jobs' => $activeJobs,
                'active_applications' => $activeApplications,
                'hired_applications' => $hiredApplications,
                'application_trends' => $applicationTrends,
                'timeframe' => $timeframe, // Include the timeframe in the response
                'stats' => [
                    'application_change_percent' => $applicationChangePercent,
                    'new_jobs_this_week' => $newJobsThisWeek,
                    'new_hires_this_month' => $newHiresThisMonth
                ]
            ]);
        });
    }




    private function getApplicationTrends($employerId, $startDate = null)
    {
        // If no start date is provided, use the last 5 days
        if (!$startDate) {
            $startDate = Carbon::now()->subDays(4)->startOfDay(); // 5 days including today
        } else {
            $startDate = Carbon::parse($startDate);
        }

        $endDate = Carbon::now()->endOfDay();
        $dates = [];
        $currentDate = clone $startDate;

        // Calculate the duration in days
        $durationInDays = $startDate->diffInDays($endDate) + 1; // +1 to include today

        // For 5-day duration, show all days individually
        if ($durationInDays <= 5) {
            // Generate array for each day in the period
            while ($currentDate <= $endDate) {
                $dateKey = $currentDate->format('Y-m-d');
                $dates[$dateKey] = [
                    'date' => $currentDate->format('M j'),
                    'applications' => 0
                ];
                $currentDate->addDay();
            }
        }
        // For longer durations, use appropriate grouping
        else {
            // For durations > 5 days and <= 30 days, group by day but sample if too many
            if ($durationInDays <= 30) {
                // Generate array for each day
                while ($currentDate <= $endDate) {
                    $dateKey = $currentDate->format('Y-m-d');
                    $dates[$dateKey] = [
                        'date' => $currentDate->format('M j'),
                        'applications' => 0
                    ];
                    $currentDate->addDay();
                }

                // If we have more than 15 days, sample to reduce data points
                if (count($dates) > 15) {
                    $dates = $this->sampleDates($dates, 15);
                }
            }
            // For durations > 30 days, group by week
            else {
                // Reset to start of week
                $currentDate = clone $startDate;
                $currentDate->startOfWeek();

                // Generate array for each week
                while ($currentDate <= $endDate) {
                    $weekEnd = (clone $currentDate)->endOfWeek();
                    $dateKey = $currentDate->format('Y-m-d');
                    $dates[$dateKey] = [
                        'date' => $currentDate->format('M j') . ' - ' . $weekEnd->format('M j'),
                        'applications' => 0,
                        'week_start' => $currentDate->format('Y-m-d'),
                        'week_end' => $weekEnd->format('Y-m-d')
                    ];
                    $currentDate->addWeek();
                }
            }
        }

        // Get application counts based on the grouping
        if ($durationInDays <= 30) {
            // Daily counts
            $applicationsByDate = Application::whereHas('job', function ($query) use ($employerId) {
                $query->where('employer_id', $employerId);
            })
                ->where('created_at', '>=', $startDate)
                ->where('created_at', '<=', $endDate)
                ->select(
                    DB::raw('DATE(created_at) as date'),
                    DB::raw('COUNT(*) as count')
                )
                ->groupBy('date')
                ->get();

            // Populate the dates array with actual application counts
            foreach ($applicationsByDate as $item) {
                $dateKey = $item->date;
                if (isset($dates[$dateKey])) {
                    $dates[$dateKey]['applications'] = $item->count;
                }
            }
        } else {
            // Weekly counts
            foreach ($dates as $dateKey => $value) {
                $weekStart = $value['week_start'];
                $weekEnd = $value['week_end'];

                $count = Application::whereHas('job', function ($query) use ($employerId) {
                    $query->where('employer_id', $employerId);
                })
                    ->where('created_at', '>=', $weekStart)
                    ->where('created_at', '<=', $weekEnd)
                    ->count();

                $dates[$dateKey]['applications'] = $count;
            }
        }

        // Convert to indexed array for response and remove any helper keys
        $result = [];
        foreach ($dates as $date) {
            $resultDate = [
                'date' => $date['date'],
                'applications' => $date['applications']
            ];
            $result[] = $resultDate;
        }

        return $result;
    }

    // Helper method to sample dates to reduce data points
    private function sampleDates($dates, $sampleSize)
    {
        $count = count($dates);
        if ($count <= $sampleSize) {
            return $dates;
        }

        $step = $count / $sampleSize;
        $sampledDates = [];
        $keys = array_keys($dates);

        for ($i = 0; $i < $sampleSize; $i++) {
            $index = min(floor($i * $step), $count - 1);
            $key = $keys[$index];
            $sampledDates[$key] = $dates[$key];
        }

        return $sampledDates;
    }



    public function getApplicationTrendsByTimeframe($timeframe)
    {
        $employerId = auth()->user()->id;
        $timeframe = $timeframe ?? '30days';

        // Calculate start date based on timeframe
        $startDate = Carbon::now();

        switch ($timeframe) {
            case '5days':
                $startDate = $startDate->subDays(4)->startOfDay();
                break;
            case '7days':
                $startDate = $startDate->subDays(7)->startOfDay();
                break;
            case '60days':
                $startDate = $startDate->subDays(60)->startOfDay();
                break;
            case '90days':
                $startDate = $startDate->subDays(90)->startOfDay();
                break;
            case "180days":
                $startDate = $startDate->subDays(180)->startOfDay();
                break;
            case '30days':
            default:
                $startDate = $startDate->subDays(30)->startOfDay();
                break;
        }

        $applicationTrends = $this->getApplicationTrends($employerId, $startDate);

        return $applicationTrends;
    }

    public function popularJobs(Request $request)
    {
        $employerId = auth()->user()->id;
        $limit = $request->input('limit', 5); // Default to 5 popular jobs

        // Create a cache key unique to this employer and limit
        $cacheKey = "employer_popular_jobs_{$employerId}_{$limit}";

        // Try to get data from cache first (with 60 minute expiration)
        return Cache::remember($cacheKey, 60 * 1, function () use ($employerId, $limit) {
            $jobs = AmJob::query()
                ->with(['applications', 'skills'])
                ->where('employer_id', $employerId)
                ->withCount('applications') // Count applications for each job
                ->orderBy('applications_count', 'desc') // Order by application count
                ->limit($limit)
                ->get();

            // Transform the jobs to include only necessary data
            $popularJobs = $jobs->map(function ($job) {
                return [
                    'id' => $job->id,
                    'title' => $job->title,
                    'slug' => $job->slug,
                    'location' => $job->location,
                    'company_name' => $job->company_name,
                    'applications_count' => $job->applications_count,
                    'skills' => $job->skills->pluck('name')->toArray(),
                    'posted_date' => $job->created_at->format('M j, Y'),
                    'views_count' => Activity::where('subject_type', AmJob::class)
                        ->where('subject_id', $job->id)
                        ->where('type', 'job_viewed')
                        ->count(),
                ];
            });

            return response()->json([
                'success' => true,
                'popular_jobs' => $popularJobs
            ]);
        });
    }

     public function getHiringStats(Request $request)
    {
       $employerId = $request->input('employer_id') ?? Auth::id();
        $startDate = $request->input('start_date') ? Carbon::parse($request->input('start_date')) : Carbon::now()->subDays(90);
        $endDate = $request->input('end_date') ? Carbon::parse($request->input('end_date')) : Carbon::now();

        // Get employer's jobs
        $jobIds = AmJob::where('employer_id', $employerId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->pluck('id');

        // 1. APPLICATION RATE
        $totalApplications = Application::whereIn('job_id', $jobIds)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        $totalViews = Activity::where('subject_type', AmJob::class)
            ->whereIn('subject_id', $jobIds)
            ->where('type', 'job_viewed')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        $applicationRate = $totalViews > 0 ? round(($totalApplications / $totalViews) * 100, 2) : 0;

        // 2. TIME TO HIRE
        $acceptedApplications = Application::with(['job'])
            ->whereIn('job_id', $jobIds)
            ->where('status', 'accepted')
            ->whereBetween('updated_at', [$startDate, $endDate])
            ->get();

        $timeToHireData = $acceptedApplications->map(function($application) {
            return $application->job->created_at->diffInDays($application->updated_at);
        });

        $averageTimeToHire = $timeToHireData->count() > 0 ? round($timeToHireData->avg(), 1) : 0;

        // 3. COST PER HIRE
        $platformCostPerJob = 100; // Cost to post a job
        $recruitmentCostPerHour = 50; // HR cost per hour
        $averageHoursPerHire = 20; // Hours spent per hire

        $totalHires = $acceptedApplications->count();
        $totalJobs = $jobIds->count();
        
        $totalCost = ($totalJobs * $platformCostPerJob) + ($totalHires * $recruitmentCostPerHour * $averageHoursPerHire);
        $costPerHire = $totalHires > 0 ? round($totalCost / $totalHires, 2) : 0;

        return response()->json([
            'success' => true,
            'data' => [
                'application_rate' => $applicationRate, // Percentage
                'time_to_hire' => $averageTimeToHire, // Days
                'cost_per_hire' => $costPerHire, // Currency
            ]
        ]);
    }
}
