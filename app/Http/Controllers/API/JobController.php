<?php

namespace App\Http\Controllers\API;

use App\Helpers\ForgetCacheHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\JobRequest;
use App\Http\Resources\AmJobResource;
use App\Models\Activity;
use App\Models\AmJob;
use App\Models\SearchLog;
use App\Models\Skill;
use App\Services\JobAlertMatchingService;
use App\Traits\ActivityTrait;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;


class JobController extends Controller
{

    use ActivityTrait;

    public function index(Request $request)
    {
        //     // If search parameter is provided, use Algolia search
        // if ($request->filled('search')) {
        //     $searchQuery = $request->search;
        //     $perPage = (int) $request->input('per_page', 10);
        //     $perPage = ($perPage > 0 && $perPage <= 100) ? $perPage : 10;

        //     // Use Scout's search functionality
        //     $jobs = AmJob::search($searchQuery)
        //         ->where('status', 1) // Only include active jobs
        //         ->when($request->filled('label'), function ($query) use ($request) {
        //             $query->where('job_label', $request->label);
        //         })
        //         ->when(
        //             is_array($request->employment_type) && !empty($request->employment_type),
        //             fn($q) => $q->whereIn('employment_type', $request->employment_type)
        //         )
        //         ->when(
        //             is_array($request->experience_level) && !empty($request->experience_level),
        //             fn($q) => $q->whereIn('experience_level', $request->experience_level)
        //         )
        //         ->when(
        //             $request->filled('salary_min'),
        //             fn($q) => $q->where('salary_min', '>=', $request->salary_min)
        //         )
        //         ->when(
        //             $request->filled('salary_max'),
        //             fn($q) => $q->where('salary_max', '<=', $request->salary_max)
        //         )
        //         ->when(
        //             $request->filled('posted_after'),
        //             fn($q) => $q->where('posted_date', '>=', $request->posted_after)
        //         )
        //         ->when(
        //             !empty($request->location),
        //             fn($q) => $q->where('location', 'like', '%' . $request->location . '%')
        //         )
        //         ->paginate($perPage);

        //     return AmJobResource::collection($jobs)
        //         ->response()
        //         ->setStatusCode(200);
        // }
        $query = AmJob::select("id", "slug", "employer_id", "location_type", 'title', 'location', 'location_type', 'employment_type', 'salary_min', 'salary_max', 'posted_date', 'status', 'job_label')->with('skills', 'employer:company_name,id,image');


        $query->when($request->filled('label'), function ($query) use ($request) {
            $query->where('job_label', $request->label);
        });

        $query->where("status", 1);

        // Apply filters
        $query->when(
            is_array($request->employment_type) && !empty($request->employment_type),
            fn($q) => $q->whereIn('employment_type', $request->employment_type)
        );

        $query->when(
            is_array($request->experience_level) && !empty($request->experience_level),
            fn($q) => $q->whereIn('experience_level', $request->experience_level)
        );


        $query->when(
            $request->filled('salary_min'),
            fn($q) => $q->where('salary_min', '>=', $request->salary_min)
        );

        $query->when(
            $request->filled('salary_max'),
            fn($q) => $q->where('salary_max', '<=', $request->salary_max)
        );

        $query->when(
            $request->filled('posted_after'),
            fn($q) => $q->where('posted_date', '>=', $request->posted_after)
        );

        $query->when(
            !empty($request->location),
            fn($q) => $q->where('location', 'like', '%' . $request->location . '%')
        );

        $query->when(
            is_array($request->skills) && !empty($request->skills),
            fn($q) => $q->whereHas('skills', function ($subq) use ($request) {
                $skillNames = is_array($request->skills) ? $request->skills : $request->skills->toArray();
                $subq->whereIn('skills.name', $skillNames);
            })
        );


        $query->when(
            $request->filled('search'),
            fn($q) => $q->where(function ($subq) use ($request) {
                $search = '%' . $request->search . '%';
                $query = strtolower(trim($request->search));

                if (strlen($query) > 2) {
                    SearchLog::create([
                        'query' => $request->search,
                        'ip_address' => $request->ip(),
                    ]);
                }


                $subq->where('title', 'like', $search)
                    ->orWhere('description', 'like', $search)->orWhereHas("employer", function ($query) use ($search) {
                        $query->where("company_name", "like", $search);
                    })->orWhereHas("skills", function ($query) use ($search) {
                        $query->where('skills.name', 'like', $search);
                    })->orWhere("description", "like", $search)->orWhere("department", "like", $search)->orWhere("location", "like", $search);
                // $subq->search($request->search);
            })
        );

        $sortField = $request->input('sort_by', 'posted_date');
        $sortOrder = $request->input('sort_order', 'desc');
        $perPage = (int) $request->input('per_page', 10);
        $perPage = ($perPage > 0 && $perPage <= 100) ? $perPage : 10; // max 100 per page
        $page = (int) $request->input('page', 1);


        $jobs = $query->paginate(10);

        $cacheKey = 'jobs:' . md5(json_encode([
            'filters' => $request->except('page'),
            'page' => $page,
            'perPage' => $perPage,
            'sortField' => $sortField,
            'sortOrder' => $sortOrder,
        ]));

        $jobs = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($query, $sortField, $sortOrder, $perPage) {
            if ($sortField === "salary-high") {
                return $query->orderBy('salary_max', 'desc')->paginate($perPage);
            }
            if ($sortField === "salary-low") {
                return $query->orderBy('salary_max', 'asc')->paginate($perPage);
            }
            return $query->orderBy($sortField, $sortOrder)->paginate($perPage);
        });

        return AmJobResource::collection($jobs)
            ->response()
            ->setStatusCode(200);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create() {}

    /**
     * Store a newly created resource in storage.
     */
    public function store(JobRequest $request)
    {
        DB::beginTransaction();


        try {
            $data = $request->validated();

            $data['employer_id'] = Auth::user()?->id;
            $data['posted_date'] = Carbon::now();
            $data['status'] = 1;
            $titleSlug = Str::slug($data['title']);
            $departmentSlug = Str::slug($data['department'] ?? 'job');
            $data['slug'] = $titleSlug . '-' . $departmentSlug;
            $job = AmJob::create($data);

            // Attach skills if provided
            foreach ($request->skills as $skillName) {
                $ifCreatedAlready = Skill::where(['name' => $skillName])->first();
                if ($ifCreatedAlready) {
                    // Attach the existing skill
                    $job->skills()->attach($ifCreatedAlready->id);
                } else {
                    // Create and attach the new skill
                    $skillData = Skill::create(['name' => $skillName]);
                    Log::info($skillData);
                    $job->skills()->attach($skillData->id);
                }
            }

            ForgetCacheHelper::forgetCacheByPrefix('jobs:');
            DB::commit();
            if ($data['status'] == 1) {
                try {
                    $matchingService = new JobAlertMatchingService();
                    $matchingService->matchJobWithAlerts($job);
                } catch (\Exception $e) {
                    Log::error('Error in matchJobWithAlerts: ' . $e->getMessage());
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Job created successfully",

            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'error' => true,
                'message' => 'Job Creation failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $job = AmJob::with('skills')->find($id);

        if (!$job) {
            return response()->json([
                'error' => true,
                'message' => 'Job not found'
            ], 404);
        }

        return new AmJobResource($job);
    }

    public function trash()
    {

        $jobs = AmJob::where('employer_id', Auth::user()->id)->onlyTrashed()->latest()->paginate(10);
        return AmJobResource::collection($jobs);
    }

    public function delete($id)
    {
        $job = AmJob::where('employer_id', Auth::user()->id)->findOrFail($id);
        $job->delete();
        ForgetCacheHelper::forgetCacheByPrefix('jobs:');

        return response()->json([
            'success' => true,
            'message' => 'Job soft deleted successfully'
        ], 200);
    }

    public function restore($id)
    {
        $job = AmJob::withTrashed()->where('employer_id', Auth::user()->id)->findOrFail($id);
        $job->restore();

        return response()->json([
            'success' => true,
            'message' => 'Job restored successfully'
        ], 200);
    }

    public function forceDelete($id)
    {
        $job = AmJob::withTrashed()->where('employer_id', Auth::user()->id)->findOrFail($id);
        $job->forceDelete();

        return response()->json([
            'success' => true,
            'message' => 'Job permanently deleted'
        ], 200);
    }

    public function batchRestore(Request $request)
    {
        $ids = $request->input('ids', []);
        AmJob::withTrashed()->where('employer_id', Auth::user()->id)->whereIn('id', $ids)->restore();

        return response()->json([
            'message' => count($ids) . ' Jobs restored successfully'
        ], 200);
    }

    public function batchDelete(Request $request)
    {
        $ids = $request->input('ids', []);
        AmJob::where('employer_id', Auth::user()->id)->whereIn('id', $ids)->delete();

        return response()->json([
            'message' => count($ids) . ' jobs soft deleted successfully'
        ], 200);
    }

    public function batchForceDelete(Request $request)
    {
        $ids = $request->input('ids', []);
        AmJob::withTrashed()->where('employer_id', Auth::user()->id)->whereIn('id', $ids)->forceDelete();

        return response()->json([
            'message' => count($ids) . ' jobs permanently deleted'
        ], 200);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(JobRequest $request, string $id)
    {
        $validation = Validator::make(['slug' => $request->slug], [
            'slug' => 'nullable|string|unique:am_jobs,slug,' . $id,
        ]);
        if ($validation->fails()) {
            return response()->json([
                'error' => true,
                'message' => $validation->errors()
            ], 422);
        }
        $job = AmJob::find($id);
        if (!$job) {
            return response()->json([
                'error' => true,
                'message' => 'Job not found'
            ], 404);
        }
        DB::beginTransaction();

        try {
            $data = $request->validated();

            $job->update($data);

            if ($request->has('skills')) {
                $skillIds = [];

                foreach ($request->skills as $skillName) {
                    if (empty($skillName)) continue;

                    $skill = Skill::firstOrCreate(['name' => $skillName]);

                    $skillIds[] = $skill->id;
                }

                $job->skills()->sync($skillIds);
            }
            ForgetCacheHelper::forgetCacheByPrefix('jobs:');
            DB::commit();


            return response()->json([
                'success' => true,
                'message' => "Job updated successfully",
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'error' => true,
                'message' => 'Job update failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $job = AmJob::find($id);
        if (!$job) {
            return response()->json([
                'error' => true,
                'message' => 'Job not found'
            ], 404);
        }

        DB::beginTransaction();

        try {
            $job->delete();
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Job deleted successfully",
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'error' => true,
                'message' => 'Job deletion failed: ' . $e->getMessage()
            ], 500);
        }
    }


    public function allEmployerJobs(Request $request)
    {
        $user = Auth::user();
        $employerId = $user->id;

        $query = AmJob::with("skills");

        $query->when(
            $request->filled('search'),
            fn($q) => $q->where(function ($subq) use ($request) {
                $search = '%' . $request->search . '%';
                $subq->where('title', 'like', "%$search%")
                    ->orWhere('description', 'like', "%$search%");
            })
        );

        $query->when($request->filled('status'), function ($q) use ($request) {
            if ($request->status == 'active') {
                $q->where('status', 0);
            } elseif ($request->status == 'inactive') {
                $q->where('status', 1);
            }
        });

        $query->when($request->filled('departments'), function ($q) use ($request) {
            $q->where("department", $request->department);
        });

        foreach ($request->except('search', 'status', 'departments', 'page_size') as $key => $value) {
            if (is_array($value) && !empty($value)) {
                $query->whereIn($key, $value);
            }
        }

        $result = $query->where('employer_id', $employerId)
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('page_size', 10));

        return AmJobResource::collection($result);
    }



    public function jobDescription($slug)
    {
        $job = AmJob::with('skills', 'employer:id,company_name,image')->where('slug', $slug)->firstOrFail();

        if (!$job) {
            return response()->json([
                'message' => 'Job not found'
            ], 404);
        }
        $user = Auth::user();

        if ($user?->hasRole("jobseeker")) {
            $this->recordViewJob($job, $user);
        }

        return new AmJobResource($job);
    }

    public function updateStatus(Request $request, $id)
    {
        $job = AmJob::findOrFail($id);
        $oldStatus = $job->status;
        $job->status = !$job->status;
        $job->save();
        if ($oldStatus == 0 && $job->status == 1) {
            // Process job alerts asynchronously
            dispatch(function () use ($job) {
                app(JobAlertMatchingService::class)->matchJobWithAlerts($job);
            })->afterCommit();
        }

        ForgetCacheHelper::forgetCacheByPrefix('jobs:');
        return response()->json([
            'message' => 'Job status updated successfully'
        ]);
    }

    public function availableSlug(Request $request, $id)
    {
        $validation = Validator::make($request->all(), [
            'slug' => 'required|string|max:255|unique:am_jobs,slug,' . $id,
        ]);

        if ($validation->fails()) {
            return response()->json([
                'message' => 'Validation error',
                'errors' => $validation->errors()
            ], 422);
        }
        return response()->json([
            'success' => true,
            'message' => "slug is available",
        ], 200);
    }


    public function popularJobs(Request $request)
    {
        $user = Auth::user();
        $isjobseeker = $user?->hasRole("jobseeker");
        $cacheKey = "popular_jobs_10";

        return Cache::remember($cacheKey, 60 * 1, function () use ($isjobseeker, $user) {
            $jobs = AmJob::query()
                ->with(['applications', 'skills'])
                ->withCount('applications') // Count applications for each job
                ->orderBy('applications_count', 'desc') // Order by application count
                ->limit(10)
                ->get();

            // Transform the jobs to include only necessary data
            $popularJobs = $jobs->map(function ($job) use ($isjobseeker, $user) {
                return [
                    'id' => $job->id,
                    'title' => $job->title,
                    'slug' => $job->slug,
                    'location' => $job->location,
                    'company_logo' => $job?->employer?->employerLogo ?? asset('image.png'),
                    'applications_count' => $job->applications_count,
                    'salary' => $job->salary_min . 'k -' . $job->salary_max . 'k',
                    'company_name' => $job?->employer?->company_name,
                    'skills' => $job->skills->pluck('name')->toArray(),
                    'posted_date' => $job->created_at->format('M j, Y'),
                    'saved' => $isjobseeker ? Activity::where("type", "job_saved")->where("subject_id", $job->id)->where("user_id", $user->id)->exists() : null


                ];
            });

            return response()->json([
                'success' => true,
                'popular_jobs' => $popularJobs
            ]);
        });
    }


    public function expiringJobs(Request $request)
    {
        $user = Auth::user();
        $isjobseeker = $user?->hasRole("jobseeker");
        $cacheKey = "expiring_jobs_10";
        $daysToExpiration = $request->input('days', 7); // Default to 7 days

        return Cache::remember($cacheKey, 60 * 1, function () use ($daysToExpiration, $isjobseeker, $user) {
            // Get current date
            $now = now();

            // Get jobs that will expire within the specified number of days
            $jobs = AmJob::query()
                ->with(['applications', 'skills', 'employer'])
                ->withCount('applications')
                ->where('status', 1) // Only active jobs
                ->where('application_deadline', '>=', $now) // Deadline hasn't passed yet
                ->where('application_deadline', '<=', $now->copy()->addDays($daysToExpiration)) // Will expire within specified days
                ->orderBy('application_deadline', 'asc') // Order by closest to expiration
                ->limit(10)
                ->get();

            $expiringJobs = $jobs->map(function ($job) use ($isjobseeker, $user) {
                // Calculate days remaining until expiration
                $daysRemaining = now()->diffInDays($job->application_deadline, false);

                return [
                    'id' => $job->id,
                    'title' => $job->title,
                    'slug' => $job->slug,
                    'location' => $job->location,
                    'company_logo' => $job->employer?->employerLogo ?? asset('person.png'),
                    'applications_count' => $job->applications_count,
                    'salary' => $job->salary_min . 'k -' . $job->salary_max . 'k',
                    'company_name' => $job->employer->company_name,
                    'skills' => $job->skills->pluck('name')->toArray(),
                    'posted_date' => $job->created_at->format('M j, Y'),
                    'application_deadline' => Carbon::parse($job->application_deadline)->format('M j, Y'),
                    'days_remaining' => round($daysRemaining, 1),
                    'expiring_soon' => $daysRemaining <= 3,
                    'saved' => $isjobseeker ? Activity::where("type", "job_saved")->where("subject_id", $job->id)->where("user_id", $user->id)->exists() : null,
                    // Flag for jobs expiring very soon (within 3 days)
                ];
            });

            return response()->json([
                'success' => true,
                'expiring_jobs' => $expiringJobs
            ]);
        });
    }



    public function jobListingOverview(Request $request)
    {
        $employer = Auth::user();
        $activeJobs = AmJob::where("employer_id", $employer->id)->where("status", 1)->count();
        $pausedJobs = AmJob::where("employer_id", $employer->id)->where("status", 0)->count();
        $closedJobs = AmJob::where('employer_id', $employer->id)->onlyTrashed()->count();
        $allJobs = AmJob::select("id")->with("employer")->whereHas("employer", function ($query) use ($employer) {
            $query->where("id", $employer->id);
        })->get();

        $allViews = Activity::where("type", "job_viewed")->whereIn("subject_id", $allJobs->pluck("id")->toArray())->count();

        return response()->json([
            'success' => true,
            'active_jobs' => $activeJobs,
            'paused_jobs' => $pausedJobs,
            'closed_jobs' => $closedJobs,
            'total_views' => $allViews,
        ]);
    }

    public function getJobDepartments(Request $request)
    {
        $deparments = [
            "it",
            "engineering",
            "design",
            "marketing",
            "sales",
            "finance",
            "hr",
            "operations",
            "product",
            "customer_support"
        ];

        $countDepartment = [];
        foreach ($deparments as $department) {
            $countDepartment[$department] = AmJob::where('department', $department)->count();
        }
        return response()->json([
            'success' => true,
            'message' => 'Jobs by department',
            'data' => $countDepartment
        ], 200);
    }

    private function getDepartmentDisplayName($department)
    {
        $departmentNames = [
            'it' => 'Information Technology',
            'engineering' => 'Engineering',
            'design' => 'Design & Creative',
            'marketing' => 'Marketing & Communications',
            'sales' => 'Sales & Business Development',
            'finance' => 'Finance & Accounting',
            'hr' => 'Human Resources',
            'operations' => 'Operations & Management',
            'product' => 'Product Management',
            'customer_support' => 'Customer Support',
            'other' => 'Other'
        ];

        return $departmentNames[$department] ?? ucfirst(str_replace('_', ' ', $department));
    }

    public function departments()
    {
        $cacheKey = 'departments_with_stats';

        return Cache::remember($cacheKey, now()->addHours(2), function () {
            // Get all active jobs with their relationships
            $jobs = AmJob::with(['skills'])
                ->where('status', 1)
                ->get();

            $totalJobs = $jobs->count();

            if ($totalJobs === 0) {
                return response()->json([
                    'success' => true,
                    'data' => [],
                    'total_jobs' => 0
                ]);
            }

            // Group jobs by department
            $departmentStats = $jobs->groupBy('department')->map(function ($departmentJobs, $department) use ($totalJobs) {
                $jobCount = $departmentJobs->count();
                $percentage = round(($jobCount / $totalJobs) * 100, 1);

                // Calculate salary statistics
                $salariesMin = $departmentJobs->where('salary_min', '>', 0)->pluck('salary_min');
                $salariesMax = $departmentJobs->where('salary_max', '>', 0)->pluck('salary_max');

                $avgMinSalary = $salariesMin->count() > 0 ? round($salariesMin->avg()) : 0;
                $avgMaxSalary = $salariesMax->count() > 0 ? round($salariesMax->avg()) : 0;

                // Get top skills for this department
                $skillCounts = [];
                foreach ($departmentJobs as $job) {
                    foreach ($job->skills as $skill) {
                        $skillName = strtolower($skill->name);
                        $skillCounts[$skillName] = ($skillCounts[$skillName] ?? 0) + 1;
                    }
                }

                // Sort skills by count and get top 5
                arsort($skillCounts);
                $topSkills = array_slice($skillCounts, 0, 5, true);

                return [
                    'name' => $department,
                    'key' => $department,
                    'count' => $jobCount,
                    'percentage' => $percentage,
                    'salary_info' => [
                        'avg_min_salary' => $avgMinSalary,
                        'avg_max_salary' => $avgMaxSalary,
                        'min_salary' => $salariesMin->count() > 0 ? $salariesMin->min() : 0,
                        'max_salary' => $salariesMax->count() > 0 ? $salariesMax->max() : 0,
                    ],
                    'top_skills' => $topSkills,
                    'recent_jobs_count' => $departmentJobs->where('posted_date', '>=', now()->subDays(7))->count(),
                    'employment_types' => $departmentJobs->groupBy('employment_type')->map->count()->toArray(),
                    'experience_levels' => $departmentJobs->groupBy('experience_level')->map->count()->toArray(),
                ];
            });

            // Sort by job count (descending)
            $sortedDepartments = $departmentStats->sortByDesc('count');

            return response()->json([
                'success' => true,
                'data' => $sortedDepartments->toArray(),
                'total_jobs' => $totalJobs,
                'total_departments' => $sortedDepartments->count(),
                'last_updated' => now()->toISOString()
            ]);
        });
    }


    public function search(Request $request)
    {
        $popularSearches = Cache::remember('popular_searches', 3600, function () {
            return SearchLog::select('query', DB::raw('COUNT(*) as count'))
                ->groupBy('query')
                ->having('count', '>', 4)  // Only include searches with more than 4 occurrences
                ->orderByDesc('count')
                ->limit(10)
                ->get();
        });

        return response()->json([
            'success' => true,
            'popular_searches' => $popularSearches
        ]);
    }

    /**
     * Import jobs from LinkedIn CSV file
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function importLinkedInJobs(Request $request)
    {
        // Validate the uploaded file
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:csv,json|max:20480', // Max 10MB
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $file = $request->file('file');
        $fileExtension = $file->getClientOriginalExtension();

        // Define valid department values - update this list based on your database constraints
        $validDepartments = ['engineering', 'design', 'marketing', 'sales', 'finance', 'hr', 'it', 'other'];

        DB::beginTransaction();

        try {
            $importedCount = 0;
            $failedCount = 0;
            $errors = [];

            if ($fileExtension === 'csv') {
                // Process CSV file
                $handle = fopen($file->getPathname(), 'r');

                // Read the header row
                $headers = fgetcsv($handle);

                // Map CSV headers to database fields
                $headerMap = [
                    'title' => array_search('title', array_map('strtolower', $headers)),
                    'description' => array_search('description', array_map('strtolower', $headers)),
                    'location' => array_search('location', array_map('strtolower', $headers)),
                    'location_type' => array_search('location_type', array_map('strtolower', $headers)),
                    'employment_type' => array_search('employment_type', array_map('strtolower', $headers)),
                    'experience_level' => array_search('experience_level', array_map('strtolower', $headers)),
                    'salary_min' => array_search('salary_min', array_map('strtolower', $headers)),
                    'salary_max' => array_search('salary_max', array_map('strtolower', $headers)),
                    'department' => array_search('department', array_map('strtolower', $headers)),
                    'application_deadline' => array_search('application_deadline', array_map('strtolower', $headers)),
                    'skills' => array_search('skills', array_map('strtolower', $headers)),
                    'requirements' => array_search('requirements', array_map('strtolower', $headers)),
                    'responsibilities' => array_search('responsibilities', array_map('strtolower', $headers)),
                    'benefits' => array_search('benefits', array_map('strtolower', $headers)),
                ];

                // Process each row
                $rowNumber = 1; // Start from 1 to account for header row
                while (($row = fgetcsv($handle)) !== false) {
                    $rowNumber++;

                    try {
                        // Extract data from CSV row using the header map
                        $jobData = [
                            'title' => $headerMap['title'] !== false ? $row[$headerMap['title']] : null,
                            'description' => $headerMap['description'] !== false ? $row[$headerMap['description']] : null,
                            'location' => $headerMap['location'] !== false ? $row[$headerMap['location']] : null,
                            'location_type' => $headerMap['location_type'] !== false ? $row[$headerMap['location_type']] : 'on-site',
                            'employment_type' => $headerMap['employment_type'] !== false ? $row[$headerMap['employment_type']] : 'Full-time',
                            'experience_level' => $headerMap['experience_level'] !== false ? $row[$headerMap['experience_level']] : 'Mid Level',
                            'salary_min' => $headerMap['salary_min'] !== false ? $row[$headerMap['salary_min']] : 0,
                            'salary_max' => $headerMap['salary_max'] !== false ? $row[$headerMap['salary_max']] : 0,
                            'application_deadline' => $headerMap['application_deadline'] !== false ? $row[$headerMap['application_deadline']] : Carbon::now()->addDays(30)->format('Y-m-d'),
                            'employer_id' => Auth::user()->id,
                            'posted_date' => Carbon::now(),
                            'status' => 1,
                        ];

                        // Map department to valid values
                        if ($headerMap['department'] !== false && !empty($row[$headerMap['department']])) {
                            $departmentValue = strtolower(trim($row[$headerMap['department']]));
                            // Map common department names to valid values
                            if (in_array($departmentValue, $validDepartments)) {
                                $jobData['department'] = $departmentValue;
                            } else {
                                // Map similar departments or default to 'other'
                                if (strpos($departmentValue, 'eng') !== false || strpos($departmentValue, 'dev') !== false) {
                                    $jobData['department'] = 'engineering';
                                } elseif (strpos($departmentValue, 'mark') !== false) {
                                    $jobData['department'] = 'marketing';
                                } elseif (strpos($departmentValue, 'fin') !== false || strpos($departmentValue, 'account') !== false) {
                                    $jobData['department'] = 'finance';
                                } elseif (strpos($departmentValue, 'hr') !== false || strpos($departmentValue, 'human') !== false) {
                                    $jobData['department'] = 'hr';
                                } elseif (strpos($departmentValue, 'tech') !== false || strpos($departmentValue, 'it') !== false) {
                                    $jobData['department'] = 'it';
                                } elseif (strpos($departmentValue, 'des') !== false) {
                                    $jobData['department'] = 'design';
                                } elseif (strpos($departmentValue, 'sale') !== false) {
                                    $jobData['department'] = 'sales';
                                } else {
                                    $jobData['department'] = 'other';
                                }
                            }
                        } else {
                            // Default department if not provided
                            $jobData['department'] = 'other';
                        }
                        // After extracting data from CSV row or JSON
                        if ($headerMap['description'] !== false && !empty($row[$headerMap['description']])) {
                            // Format description by adding line breaks before asterisks
                            $description = $row[$headerMap['description']];
                            $description = preg_replace('/\s*\*\s*/', "\n\n* ", $description);
                            $jobData['description'] = $description;
                        }

                        // Validate required fields
                        if (empty($jobData['title']) || empty($jobData['description']) || empty($jobData['location'])) {
                            throw new \Exception("Row $rowNumber: Title, description, and location are required fields");
                        }

                        // Process JSON fields
                        $requirements = $headerMap['requirements'] !== false ? $row[$headerMap['requirements']] : '[]';
                        $responsibilities = $headerMap['responsibilities'] !== false ? $row[$headerMap['responsibilities']] : '[]';
                        $benefits = $headerMap['benefits'] !== false ? $row[$headerMap['benefits']] : '[]';

                        // Convert to arrays if they're comma-separated strings
                        if (!$this->isJson($requirements)) {
                            $requirements = json_encode(array_map('trim', explode(',', $requirements)));
                        }

                        if (!$this->isJson($responsibilities)) {
                            $responsibilities = json_encode(array_map('trim', explode(',', $responsibilities)));
                        }

                        if (!$this->isJson($benefits)) {
                            $benefits = json_encode(array_map('trim', explode(',', $benefits)));
                        }

                        $jobData['requirements'] = $requirements;
                        $jobData['responsibilities'] = $responsibilities;
                        $jobData['benefits'] = $benefits;

                        // Generate slug
                        $titleSlug = Str::slug($jobData['title']);
                        $departmentSlug = Str::slug($jobData['department'] ?? 'job');
                        $jobData['slug'] = $titleSlug . '-' . $departmentSlug;

                        // Create the job
                        $job = AmJob::create($jobData);

                        // Process skills
                        if ($headerMap['skills'] !== false && !empty($row[$headerMap['skills']])) {
                            $skillsString = $row[$headerMap['skills']];
                            $skillNames = array_map('trim', explode(',', $skillsString));

                            foreach ($skillNames as $skillName) {
                                if (empty($skillName)) continue;

                                $skill = Skill::firstOrCreate(['name' => $skillName]);
                                $job->skills()->attach($skill->id);
                            }
                        }

                        $importedCount++;
                    } catch (\Exception $e) {
                        $failedCount++;
                        $errors[] = "Row $rowNumber: " . $e->getMessage();
                    }
                }

                fclose($handle);
            } elseif ($fileExtension === 'json') {
                // Process JSON file
                $jsonData = json_decode(file_get_contents($file->getPathname()), true);

                if (!is_array($jsonData)) {
                    throw new \Exception("Invalid JSON format");
                }

                foreach ($jsonData as $index => $jobItem) {
                    try {
                        // Extract data from JSON
                        $jobData = [
                            'title' => $jobItem['title'] ?? null,
                            'description' => $jobItem['description'] ?? null,
                            'location' => $jobItem['location'] ?? null,
                            'location_type' => $jobItem['location_type'] ?? 'on-site',
                            'employment_type' => $jobItem['employment_type'] ?? 'Full-time',
                            'experience_level' => $jobItem['experience_level'] ?? 'Mid Level',
                            'salary_min' => $jobItem['salary_min'] ?? 0,
                            'salary_max' => $jobItem['salary_max'] ?? 0,
                            'application_deadline' => $jobItem['application_deadline'] ?? Carbon::now()->addDays(30)->format('Y-m-d'),
                            'employer_id' => Auth::user()->id,
                            'posted_date' => Carbon::now(),
                            'status' => 1,
                        ];

                        // Map department to valid values
                        if (isset($jobItem['department']) && !empty($jobItem['department'])) {
                            $departmentValue = strtolower(trim($jobItem['department']));
                            // Map common department names to valid values
                            if (in_array($departmentValue, $validDepartments)) {
                                $jobData['department'] = $departmentValue;
                            } else {
                                // Map similar departments or default to 'other'
                                if (strpos($departmentValue, 'eng') !== false || strpos($departmentValue, 'dev') !== false) {
                                    $jobData['department'] = 'engineering';
                                } elseif (strpos($departmentValue, 'mark') !== false) {
                                    $jobData['department'] = 'marketing';
                                } elseif (strpos($departmentValue, 'fin') !== false || strpos($departmentValue, 'account') !== false) {
                                    $jobData['department'] = 'finance';
                                } elseif (strpos($departmentValue, 'hr') !== false || strpos($departmentValue, 'human') !== false) {
                                    $jobData['department'] = 'hr';
                                } elseif (strpos($departmentValue, 'tech') !== false || strpos($departmentValue, 'it') !== false) {
                                    $jobData['department'] = 'it';
                                } elseif (strpos($departmentValue, 'des') !== false) {
                                    $jobData['department'] = 'design';
                                } elseif (strpos($departmentValue, 'sale') !== false) {
                                    $jobData['department'] = 'sales';
                                } else {
                                    $jobData['department'] = 'other';
                                }
                            }
                        } else {
                            // Default department if not provided
                            $jobData['department'] = 'other';
                        }

                        // Validate required fields
                        if (empty($jobData['title']) || empty($jobData['description']) || empty($jobData['location'])) {
                            throw new \Exception("Item $index: Title, description, and location are required fields");
                        }

                        // For JSON import
                        if (isset($jobItem['description']) && !empty($jobItem['description'])) {
                            // Format description by adding line breaks before asterisks
                            $description = $jobItem['description'];
                            $description = preg_replace('/\s*\*\s*/', "\n\n* ", $description);
                            $jobData['description'] = $description;
                        }

                        // Process JSON fields
                        $requirements = isset($jobItem['requirements']) ? (is_array($jobItem['requirements']) ? json_encode($jobItem['requirements']) : $jobItem['requirements']) : '[]';
                        $responsibilities = isset($jobItem['responsibilities']) ? (is_array($jobItem['responsibilities']) ? json_encode($jobItem['responsibilities']) : $jobItem['responsibilities']) : '[]';
                        $benefits = isset($jobItem['benefits']) ? (is_array($jobItem['benefits']) ? json_encode($jobItem['benefits']) : $jobItem['benefits']) : '[]';

                        // Ensure they're valid JSON
                        if (!$this->isJson($requirements)) {
                            $requirements = '[]';
                        }

                        if (!$this->isJson($responsibilities)) {
                            $responsibilities = '[]';
                        }

                        if (!$this->isJson($benefits)) {
                            $benefits = '[]';
                        }

                        $jobData['requirements'] = $requirements;
                        $jobData['responsibilities'] = $responsibilities;
                        $jobData['benefits'] = $benefits;

                        // Generate slug
                        $titleSlug = Str::slug($jobData['title']);
                        $departmentSlug = Str::slug($jobData['department'] ?? 'job');
                        $jobData['slug'] = $titleSlug . '-' . $departmentSlug;

                        // Create the job
                        $job = AmJob::create($jobData);

                        // Process skills
                        if (isset($jobItem['skills']) && is_array($jobItem['skills'])) {
                            foreach ($jobItem['skills'] as $skillName) {
                                if (empty($skillName)) continue;

                                $skill = Skill::firstOrCreate(['name' => $skillName]);
                                $job->skills()->attach($skill->id);
                            }
                        } elseif (isset($jobItem['skills']) && is_string($jobItem['skills'])) {
                            $skillNames = array_map('trim', explode(',', $jobItem['skills']));

                            foreach ($skillNames as $skillName) {
                                if (empty($skillName)) continue;

                                $skill = Skill::firstOrCreate(['name' => $skillName]);
                                $job->skills()->attach($skill->id);
                            }
                        }

                        $importedCount++;
                    } catch (\Exception $e) {
                        $failedCount++;
                        $errors[] = "Item $index: " . $e->getMessage();
                    }
                }
            }

            // Clear cache
            if (method_exists('ForgetCacheHelper', 'forgetCacheByPrefix')) {
                ForgetCacheHelper::forgetCacheByPrefix('jobs');
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Import completed. $importedCount jobs imported successfully, $failedCount failed.",
                'errors' => $errors
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'error' => true,
                'message' => 'Import failed: ' . $e->getMessage(),
                'errors' => [$e->getMessage()]
            ], 500);
        }
    }


    private function isJson($string)
    {
        if (!is_string($string)) {
            return false;
        }

        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }


    public function jobSEOInfo(Request $request, $slug)
    {


        $job = AmJob::select('id', 'title', 'slug', 'description')->with("employer", function ($q) {
            $q->select('id', 'image');
        })->where('slug', $slug)->firstOrFail();

        if (!$job) {
            return response()->json([
                'success' => false,
                'message' => 'Job not found'
            ], 404);
        }

        return new AmJobResource($job);
    }


    public function getJobSlugs()
    {
        $jobSlugs = AmJob::pluck('slug');
        return response()->json(['job_slugs' => $jobSlugs]);
    }
}
