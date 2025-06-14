<?php

namespace App\Http\Controllers\API\Employer;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Activity;
use App\Models\User;
use App\Traits\ActivityTrait;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CandidateController extends Controller
{

    use ActivityTrait;
    public function index(Request $request)
    {
        $userId = Auth::id();
        $query = User::select("id", 'first_name', 'last_name', 'company_name', 'email', 'phone', 'image')
            ->with([
                "jobSeekerProfile" => function ($query) {
                    $query->select("id", "user_id", "first_name", "last_name", "looking_for");
                },
                'experiences',
                "preferences",
                "skills" => function ($query) {
                    $query->select("name");
                }
            ])
            ->whereHas("roles", function ($q) {
                $q->where("name", "jobseeker");
            });

        // Apply text search filter
        if ($request->filled("search")) {
            $searchTerm = $request->query("search");
            $query->where(function ($q) use ($searchTerm) {
                $q->where("first_name", "like", "%{$searchTerm}%")
                    ->orWhere("last_name", "like", "%{$searchTerm}%")
                    ->orWhereHas("jobSeekerProfile", function ($subq) use ($searchTerm) {
                        $subq->where("looking_for", "like", "%{$searchTerm}%");
                    })
                    ->orWhereHas("skills", function ($subq) use ($searchTerm) {
                        $subq->where("name", "like", "%{$searchTerm}%");
                    });
            });
        }

        // Filter by experience level
        if ($request->filled('experience_level')) {
            $experienceLevels = $request->input('experience_level', []);

            if (!empty($experienceLevels)) {
                $query->whereHas('experiences', function ($q) use ($experienceLevels) {
                    $q->where(function ($subq) use ($experienceLevels) {
                        // Entry Level (0-2 years)
                        if (in_array('entry', $experienceLevels)) {
                            $subq->orWhere('job_level', 'like', '%Entry%')
                                ->orWhere(function ($dateQuery) {
                                    $twoYearsAgo = Carbon::now()->subYears(2);
                                    $dateQuery->whereDate('start_date', '>=', $twoYearsAgo);
                                });
                        }

                        // Mid Level (2-5 years)
                        if (in_array('mid', $experienceLevels)) {
                            $subq->orWhere('job_level', 'like', '%Mid%')
                                ->orWhere(function ($dateQuery) {
                                    $twoYearsAgo = Carbon::now()->subYears(2);
                                    $fiveYearsAgo = Carbon::now()->subYears(5);
                                    $dateQuery->whereDate('start_date', '<=', $twoYearsAgo)
                                        ->whereDate('start_date', '>=', $fiveYearsAgo);
                                });
                        }

                        // Senior Level (5+ years)
                        if (in_array('senior', $experienceLevels)) {
                            $subq->orWhere('job_level', 'like', '%Senior%')
                                ->orWhere('job_level', 'like', '%Lead%')
                                ->orWhere(function ($dateQuery) {
                                    $fiveYearsAgo = Carbon::now()->subYears(5);
                                    $dateQuery->whereDate('start_date', '<=', $fiveYearsAgo);
                                });
                        }
                    });
                });
            }
        }

        // Filter by skills
        if ($request->filled('skills')) {
            $requestedSkills = $request->input('skills', []);

            if (!empty($requestedSkills)) {
                foreach ($requestedSkills as $skill) {
                    $query->whereHas('skills', function ($q) use ($skill) {
                        $q->where('name', 'like', "%{$skill}%");
                    });
                }
            }
        }

        // Filter by location
        if ($request->filled('location') && $request->input('location') !== 'All Locations') {
            $location = $request->input('location');
            $query->whereHas('jobSeekerProfile', function ($q) use ($location) {
                $q->where('city_tole', 'like', "%{$location}%")
                    ->orWhere('district', 'like', "%{$location}%");
            });
        }

        // Filter by availability
        if ($request->filled('availability')) {
            $availabilityOptions = $request->input('availability', []);

            if (!empty($availabilityOptions)) {
                $query->whereHas('preferences', function ($q) use ($availabilityOptions) {
                    $q->where(function ($subq) use ($availabilityOptions) {
                        // Available Now
                        if (in_array('now', $availabilityOptions)) {
                            $subq->orWhere('immediate_availability', true);
                        }

                        // Available in 2 weeks
                        if (in_array('two_weeks', $availabilityOptions)) {
                            $subq->orWhere('availability_date', '<=', Carbon::now()->addWeeks(2));
                        }

                        // Available in 1 month
                        if (in_array('one_month', $availabilityOptions)) {
                            $subq->orWhere('availability_date', '<=', Carbon::now()->addMonth());
                        }
                    });
                });
            }
        }

        // Filter by salary range
        if ($request->filled('min_salary') || $request->filled('max_salary')) {
            $minSalary = $request->input('min_salary');
            $maxSalary = $request->input('max_salary');

            $query->whereHas('jobSeekerProfile', function ($q) use ($minSalary, $maxSalary) {
                if (!empty($minSalary)) {
                    $q->where('salary_expectations', '>=', $minSalary);
                }

                if (!empty($maxSalary)) {
                    $q->where('salary_expectations', '<=', $maxSalary);
                }
            });
        }

        $perPage = $request->query("perPage") ?? 10;
        return UserResource::collection($query->latest()->paginate($perPage));
    }


    public function candidateProfile(Request $request, $id)
    {
        $user = User::with(["roles", "jobSeekerProfile", "experiences", "educations", "skills", "languages", "preferences"])->findOrFail($id);
        if (!$user->hasRole("jobseeker")) {
            return response()->json([
                'error' => true,
                'message' => "User is not a jobseeker"
            ], 404);
        }

            $applications = $user->applications()
        ->whereHas("job", function($q){
            $q->where("employer_id", auth()->user()->id);
        })
        ->latest()->get();
        $userResource = new UserResource($user);

        $responseData = array_merge($userResource->toArray($request), [
            'applications' => $applications
        ]);

        $employer = Auth::user();

        $this->recordActivityByType( "profile_viewed",$employer, $user->id);
        

        return response()->json($responseData);
    }

    public function candidateStats(Request $request){
        //candidates with skills and total candidates

        $totalCandidates = User::whereHas("roles", function ($q) {
            $q->where("name", "jobseeker");
        })->count();
        $candidatesWithSkills = User::whereHas("roles", function ($q) {
            $q->where("name", "jobseeker");
        })->whereHas("skills")->count();

        //active candidates

        $activeCandidates = User::whereHas("roles", function ($q) {
            $q->where("name", "jobseeker");
        })->whereHas("preferences", function ($q) {
            $q->where("immediate_availability", true);
        })->count();


        $incompleteProfileCandidates = User::whereHas("roles", function ($q) {
            $q->where("name", "jobseeker");
        })->whereDoesntHave("experiences")->count();

        return response()->json([
            "total_candidates" => $totalCandidates,
            "candidates_with_skills" => $candidatesWithSkills,
            "active_candidates" => $activeCandidates,
            "incomplete_profile_candidates" => $incompleteProfileCandidates
        ]);
    }
}
