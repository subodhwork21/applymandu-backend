<?php

namespace App\Http\Controllers\API\Employer;

use App\Http\Controllers\Controller;
use App\Models\JobseekerProfile;
use App\Models\User;
use App\Models\Skill;
use App\Models\SavedCandidate;
use App\Models\JobInvitation;
use App\Models\ProfileView;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\JobseekerProfileResource;
use App\Http\Resources\ResumeResource;
use App\Http\Resources\JobseekerProfileDetailResource;
use App\Http\Resources\SavedCandidateResource;
use App\Http\Resources\JobInvitationResource;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ResumeSearchController extends Controller
{
    public function search(Request $request)
    {
        try {
            // Start with a base query on User model
            $query = User::with([
                "jobSeekerProfile",
                "experiences",
                "educations",
                "languages",
                "trainings",
                "certificates",
                "socialLinks",
                "references",
                "skills"
            ]);

            // Filter to only include jobseekers
            $query->whereHas('roles', function ($q) {
                $q->where('name', 'jobseeker');
            });

            // Filter by active status if needed
            $query->where('is_active', 1);

            // Apply text search if provided
            if ($request->has('search') && !empty($request->search)) {
                $searchTerm = $request->search;
                $query->where(function ($q) use ($searchTerm) {
                    // Search in user fields
                    $q->where('first_name', 'LIKE', "%{$searchTerm}%")
                        ->orWhere('last_name', 'LIKE', "%{$searchTerm}%")

                        // Search in profile
                        ->orWhereHas('jobSeekerProfile', function ($profile) use ($searchTerm) {
                            $profile->where('career_objectives', 'LIKE', "%{$searchTerm}%")
                                ->orWhere('industry', 'LIKE', "%{$searchTerm}%");
                        })

                        // Search in experiences
                        ->orWhereHas('experiences', function ($exp) use ($searchTerm) {
                            $exp->where('job_title', 'LIKE', "%{$searchTerm}%")
                                ->orWhere('company_name', 'LIKE', "%{$searchTerm}%")
                                ->orWhere('description', 'LIKE', "%{$searchTerm}%");
                        })

                        // Search in skills
                        ->orWhereHas('skills', function ($skill) use ($searchTerm) {
                            $skill->where('name', 'LIKE', "%{$searchTerm}%");
                        })

                        // Search in education
                        ->orWhereHas('educations', function ($edu) use ($searchTerm) {
                            $edu->where('degree', 'LIKE', "%{$searchTerm}%")
                                ->orWhere('field_of_study', 'LIKE', "%{$searchTerm}%")
                                ->orWhere('institution', 'LIKE', "%{$searchTerm}%");
                        });
                });
            }

            // Apply keyword filter
            if ($request->has('keywords') && !empty($request->keywords)) {
                $keywords = explode(',', $request->keywords);
                foreach ($keywords as $keyword) {
                    $keyword = trim($keyword);
                    if (!empty($keyword)) {
                        $query->where(function ($q) use ($keyword) {
                            $q->whereHas('jobSeekerProfile', function ($profile) use ($keyword) {
                                $profile->where('career_objectives', 'LIKE', "%{$keyword}%");
                            })
                                ->orWhereHas('experiences', function ($exp) use ($keyword) {
                                    $exp->where('description', 'LIKE', "%{$keyword}%");
                                });
                        });
                    }
                }
            }

            // Apply location filter
            if ($request->has('location') && !empty($request->location)) {
                $location = $request->location;
                $query->whereHas('jobSeekerProfile', function ($q) use ($location) {
                    $q->where('location', 'LIKE', "%{$location}%");
                });
            }

            // Apply experience level filter
            if ($request->has('experience_level') && !empty($request->experience_level)) {
                $levels = explode(',', $request->experience_level);
                $query->whereHas('experiences', function ($q) use ($levels) {
                    $q->whereIn('level', $levels);
                });
            }

            // Apply skills filter
            if ($request->has('skills') && !empty($request->skills)) {
                $skills = explode(',', $request->skills);
                foreach ($skills as $skill) {
                    $skill = trim($skill);
                    if (!empty($skill)) {
                        $query->whereHas('skills', function ($q) use ($skill) {
                            $q->where('name', 'LIKE', "%{$skill}%");
                        });
                    }
                }
            }

            // Filter by experience years
            if (($request->has('experience_years_min') && !empty($request->experience_years_min)) ||
                ($request->has('experience_years_max') && !empty($request->experience_years_max))
            ) {

                $minYears = $request->experience_years_min ?? 0;
                $maxYears = $request->experience_years_max ?? PHP_INT_MAX;

                $query->whereHas('experiences', function ($q) use ($minYears, $maxYears) {
                    $q->selectRaw('SUM(DATEDIFF(IFNULL(end_date, CURRENT_DATE), start_date)/365) as total_years')
                        ->groupBy('user_id')
                        ->havingRaw('total_years BETWEEN ? AND ?', [$minYears, $maxYears]);
                });
            }

            // Apply education level filter
            if ($request->has('education_level') && !empty($request->education_level)) {
                $levels = explode(',', $request->education_level);
                $query->whereHas('educations', function ($q) use ($levels) {
                    $q->whereIn('degree_level', $levels);
                });
            }

            // Apply last active filter
            if ($request->has('last_active') && $request->last_active != 'all') {
                $days = 0;
                switch ($request->last_active) {
                    case 'today':
                        $days = 1;
                        break;
                    case 'week':
                        $days = 7;
                        break;
                    case 'month':
                        $days = 30;
                        break;
                    case 'quarter':
                        $days = 90;
                        break;
                }

                if ($days > 0) {
                    $query->where('last_login_at', '>=', now()->subDays($days));
                }
            }

            // Apply job-specific filter
            if ($request->has('job_id') && !empty($request->job_id)) {
                $jobId = $request->job_id;
                $job = \App\Models\AmJob::with(['skills', 'employer'])->find($jobId);

                if ($job) {
                    // Match with job skills
                    $jobSkills = $job->skills->pluck('name')->toArray();
                    if (!empty($jobSkills)) {
                        $query->whereHas('skills', function ($q) use ($jobSkills) {
                            $q->whereIn('name', $jobSkills);
                        });

                        // Add a skill match count for relevance scoring
                        $query->withCount(['skills as skill_match_count' => function ($q) use ($jobSkills) {
                            $q->whereIn('name', $jobSkills);
                        }]);
                    }

                    // Match with job industry
                    if (!empty($job->industry)) {
                        $query->whereHas('jobSeekerProfile', function ($q) use ($job) {
                            $q->where('industry', 'LIKE', "%{$job->industry}%");
                        });
                    }

                    // Match with job location
                    if (!empty($job->location)) {
                        $query->whereHas('jobSeekerProfile', function ($q) use ($job) {
                            $q->where('location', 'LIKE', "%{$job->location}%");
                        });
                    }
                }
            }

            // Add relevance scoring for smart search
            if ($request->has('search') && !empty($request->search)) {
                $searchTerm = $request->search;

                // Fallback to basic relevance scoring
                $query->addSelect(\DB::raw('
                (
                    (CASE WHEN first_name LIKE ? THEN 10 ELSE 0 END) +
                    (CASE WHEN last_name LIKE ? THEN 10 ELSE 0 END) +
                    (CASE WHEN EXISTS (
                        SELECT 1 FROM job_seeker_profiles 
                        WHERE job_seeker_profiles.user_id = users.id
                        AND job_seeker_profiles.career_objectives LIKE ?
                    ) THEN 5 ELSE 0 END) +
                    (CASE WHEN EXISTS (
                        SELECT 1 FROM skills 
                        WHERE skills.user_id = users.id
                        AND skills.name LIKE ?
                    ) THEN 8 ELSE 0 END) +
                    (CASE WHEN EXISTS (
                        SELECT 1 FROM experiences 
                        WHERE experiences.user_id = users.id
                        AND (
                            experiences.job_title LIKE ? OR
                            experiences.company_name LIKE ? OR
                            experiences.description LIKE ?
                        )
                    ) THEN 6 ELSE 0 END) +
                    (CASE WHEN last_login_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 5 ELSE 0 END)
                ) as search_relevance
            '))
                    ->setBindings(array_merge($query->getBindings(), [
                        "%{$searchTerm}%",
                        "%{$searchTerm}%",
                        "%{$searchTerm}%",
                        "%{$searchTerm}%",
                        "%{$searchTerm}%",
                        "%{$searchTerm}%",
                        "%{$searchTerm}%"
                    ]))
                    ->orderBy('search_relevance', 'desc');
            } else if ($request->has('job_id') && !empty($request->job_id)) {
                // If searching by job, order by skill match count if available
                if ($query->getQuery()->columns && in_array('skill_match_count', $query->getQuery()->columns)) {
                    $query->orderBy('skill_match_count', 'desc');
                }
                $query->orderBy('updated_at', 'desc');
            } else {
                // Default ordering by last updated profile
                $query->orderBy('updated_at', 'desc');
            }

            // Get paginated results
            $perPage = $request->input('per_page', 10);
            $profiles = $query->paginate($perPage);

            // Get saved candidates for the current employer
            $employerId = Auth::id();
            $savedCandidateIds = SavedCandidate::where('employer_id', $employerId)
                ->pluck('jobseeker_id')
                ->toArray();

            // Add saved status to each profile
            $profiles->getCollection()->transform(function ($profile) use ($savedCandidateIds) {
                $profile->saved = in_array($profile->id, $savedCandidateIds);
                return $profile;
            });

            // Cache the search results for faster retrieval
            $cacheKey = 'employer_' . $employerId . '_search_' . md5(json_encode($request->all()));
            Cache::put($cacheKey, $profiles, now()->addHours(1));

            return response()->json([
                'success' => true,
                'message' => 'Profiles retrieved successfully',
                'data' => ResumeResource::collection($profiles),
                'meta' => [
                    'current_page' => $profiles->currentPage(),
                    'from' => $profiles->firstItem(),
                    'last_page' => $profiles->lastPage(),
                    'path' => $profiles->path(),
                    'per_page' => $profiles->perPage(),
                    'to' => $profiles->lastItem(),
                    'total' => $profiles->total(),
                ],
                'links' => [
                    'first' => $profiles->url(1),
                    'last' => $profiles->url($profiles->lastPage()),
                    'prev' => $profiles->previousPageUrl(),
                    'next' => $profiles->nextPageUrl(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Resume search error: ' . $e->getMessage(), [
                'exception' => $e,
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to search profiles',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred',
            ], 500);
        }
    }


    /**
     * Check if the database supports full-text indexes
     * 
     * @return bool
     */
    private function hasFullTextIndexes()
    {
        try {
            // Check if we're using MySQL and if it has full-text indexes on the relevant tables
            $connection = DB::connection()->getPdo();
            $driver = $connection->getAttribute(\PDO::ATTR_DRIVER_NAME);

            if ($driver === 'mysql') {
                $dbName = DB::connection()->getDatabaseName();
                $result = DB::select("
                    SELECT COUNT(*) as count 
                    FROM information_schema.STATISTICS 
                    WHERE table_schema = ? 
                    AND table_name = 'job_seeker_profiles' 
                    AND index_type = 'FULLTEXT'
                ", [$dbName]);

                return $result[0]->count > 0;
            }

            return false;
        } catch (\Exception $e) {
            Log::warning('Failed to check for full-text indexes: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Save a candidate to the employer's saved list
     * 
     * @param Request $request
     * @param int $jobseekerId
     * @return \Illuminate\Http\JsonResponse
     */
    public function saveCandidate(Request $request)
    {
        try {
            $employerId = Auth::id();

            // Check if already saved
            $existingSave = SavedCandidate::where('employer_id', $employerId)
                ->where('jobseeker_id', $request->jobseeker_id)
                ->first();

            if ($existingSave) {
                return response()->json([
                    'success' => true,
                    'message' => 'Candidate already saved',
                ]);
            }

            // Save the candidate
            SavedCandidate::create([
                'employer_id' => $employerId,
                'jobseeker_id' => $request->jobseeker_id,
                'notes' => $request->input('notes') ?? null,
                'job_id' => $request->input('job_id'),
                'saved_at' => now(),
            ]);

            // Clear any cached search results
            $this->clearEmployerSearchCache($employerId);

            return response()->json([
                'success' => true,
                'message' => 'Candidate saved successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Save candidate error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to save candidate',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred',
            ], 500);
        }
    }

    /**
     * Remove a candidate from the employer's saved list
     * 
     * @param int $jobseekerId
     * @return \Illuminate\Http\JsonResponse
     */
    public function unsaveCandidate(Request $request)
    {
        try {
            $employerId = Auth::id();

            // Delete the saved candidate record
            $deleted = SavedCandidate::where('employer_id', $employerId)
                ->where('jobseeker_id', $request->jobseeker_id)
                ->delete();

            if (!$deleted) {
                return response()->json([
                    'success' => false,
                    'message' => 'Candidate was not in your saved list',
                ], 404);
            }

            // Clear any cached search results
            $this->clearEmployerSearchCache($employerId);

            return response()->json([
                'success' => true,
                'message' => 'Candidate removed from saved list',
            ]);
        } catch (\Exception $e) {
            Log::error('Unsave candidate error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to remove candidate from saved list',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred',
            ], 500);
        }
    }

    /**
     * Get all saved candidates for the employer
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getSavedCandidates(Request $request)
    {
        try {
            $employerId = Auth::id();
            $perPage = $request->input('per_page', 10);

            // Get saved candidates with jobseeker profiles
            $savedCandidates = SavedCandidate::where('employer_id', $employerId)
                ->with(['jobseeker', 'jobseeker.jobSeekerProfile', 'jobseeker.skills', 'jobseeker.experiences', 'amJob'])
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Saved candidates retrieved successfully',
                'data' => SavedCandidateResource::collection($savedCandidates),
                'meta' => [
                    'current_page' => $savedCandidates->currentPage(),
                    'from' => $savedCandidates->firstItem(),
                    'last_page' => $savedCandidates->lastPage(),
                    'path' => $savedCandidates->path(),
                    'per_page' => $savedCandidates->perPage(),
                    'to' => $savedCandidates->lastItem(),
                    'total' => $savedCandidates->total(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Get saved candidates error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve saved candidates',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred',
            ], 500);
        }
    }

    /**
     * Update notes for a saved candidate
     * 
     * @param Request $request
     * @param int $jobseekerId
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateCandidateNotes(Request $request, $jobseekerId)
    {
        try {
            $employerId = Auth::id();

            $savedCandidate = SavedCandidate::where('employer_id', $employerId)
                ->where('jobseeker_id', $jobseekerId)
                ->first();

            if (!$savedCandidate) {
                return response()->json([
                    'success' => false,
                    'message' => 'Candidate not found in your saved list',
                ], 404);
            }

            $savedCandidate->update([
                'notes' => $request->input('notes'),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Candidate notes updated successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Update candidate notes error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update candidate notes',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred',
            ], 500);
        }
    }

    /**
     * Get recommended candidates based on job requirements
     * 
     * @param Request $request
     * @param int $jobId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getRecommendedCandidates(Request $request, $jobId)
    {
        try {
            $job = \App\Models\AmJob::with(['skills', 'employer'])->find($jobId);

            if (!$job) {
                return response()->json([
                    'success' => false,
                    'message' => 'Job not found',
                ], 404);
            }

            // Check if the job belongs to the authenticated employer
            if ($job->employer_id !== Auth::id()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to this job',
                ], 403);
            }

            // Check if we have cached recommendations
            $cacheKey = 'job_' . $jobId . '_recommended_candidates';
            if (Cache::has($cacheKey)) {
                $profiles = Cache::get($cacheKey);
            } else {
                // Start with a base query
                $query = User::with([
                    'jobSeekerProfile',
                    'skills',
                    'experiences',
                    'educations'
                ])
                    ->whereHas('roles', function ($q) {
                        $q->where('name', 'jobseeker');
                    })
                    ->where('is_active', 1);

                // Match with job skills
                $jobSkills = $job->skills->pluck('name')->toArray();
                if (!empty($jobSkills)) {
                    $query->whereHas('skills', function ($q) use ($jobSkills) {
                        $q->whereIn('name', $jobSkills);
                    });

                    // Add a skill match count for relevance scoring
                    $query->withCount(['skills as skill_match_count' => function ($q) use ($jobSkills) {
                        $q->whereIn('name', $jobSkills);
                    }]);
                }

                // Match with job experience level
                if (!empty($job->experience_level)) {
                    $query->whereHas('experiences', function ($q) use ($job) {
                        $q->where('level', $job->experience_level);
                    });
                }

                // Match with job location
                if (!empty($job->location)) {
                    $query->whereHas('jobSeekerProfile', function ($q) use ($job) {
                        $q->where('location', 'LIKE', "%{$job->location}%");
                    });
                }

                // Match with industry if available
                if (!empty($job->industry)) {
                    $query->where(function ($q) use ($job) {
                        $q->whereHas('jobSeekerProfile', function ($profile) use ($job) {
                            $profile->where('industry', 'LIKE', "%{$job->industry}%");
                        })
                            ->orWhereHas('experiences', function ($exp) use ($job) {
                                $exp->where('industry', 'LIKE', "%{$job->industry}%");
                            });
                    });
                }

                // Calculate relevance score
                $query->addSelect(DB::raw('
                    (
                        (skill_match_count * 10) +
                        (CASE WHEN EXISTS (
                            SELECT 1 FROM job_seeker_profiles 
                            WHERE job_seeker_profiles.user_id = users.id
                            AND job_seeker_profiles.industry LIKE ?
                        ) THEN 5 ELSE 0 END) +
                        (CASE WHEN EXISTS (
                            SELECT 1 FROM experiences 
                            WHERE experiences.user_id = users.id
                            AND experiences.level = ?
                        ) THEN 8 ELSE 0 END) +
                        (CASE WHEN EXISTS (
                            SELECT 1 FROM job_seeker_profiles 
                            WHERE job_seeker_profiles.user_id = users.id
                            AND job_seeker_profiles.location LIKE ?
                        ) THEN 3 ELSE 0 END) +
                        (CASE WHEN last_login_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 2 ELSE 0 END)
                    ) as relevance_score
                '))
                    ->setBindings(array_merge($query->getBindings(), [
                        "%{$job->industry}%",
                        $job->experience_level ?? '',
                        "%{$job->location}%"
                    ]))
                    ->orderBy('relevance_score', 'desc')
                    ->orderBy('updated_at', 'desc');

                // Get paginated results
                $perPage = $request->input('per_page', 10);
                $profiles = $query->paginate($perPage);

                // Cache the results for 1 hour
                Cache::put($cacheKey, $profiles, now()->addHour());
            }

            // Get saved candidates for the current employer
            $employerId = Auth::id();
            $savedCandidateIds = SavedCandidate::where('employer_id', $employerId)
                ->pluck('jobseeker_id')
                ->toArray();

            // Add saved status to each profile
            $profiles->getCollection()->transform(function ($profile) use ($savedCandidateIds) {
                $profile->saved = in_array($profile->id, $savedCandidateIds);
                return $profile;
            });

            return response()->json([
                'success' => true,
                'message' => 'Recommended candidates retrieved successfully',
                'data' => ResumeResource::collection($profiles),
                'meta' => [
                    'current_page' => $profiles->currentPage(),
                    'from' => $profiles->firstItem(),
                    'last_page' => $profiles->lastPage(),
                    'path' => $profiles->path(),
                    'per_page' => $profiles->perPage(),
                    'to' => $profiles->lastItem(),
                    'total' => $profiles->total(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Get recommended candidates error: ' . $e->getMessage(), [
                'exception' => $e,
                'job_id' => $jobId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve recommended candidates',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred',
            ], 500);
        }
    }

    /**
     * Clear employer search cache
     * 
     * @param int $employerId
     * @return void
     */
    private function clearEmployerSearchCache($employerId)
    {
        try {
            $cacheStore = Cache::getStore();

            // Check if the cache store supports the keys method (Redis)
            if (method_exists($cacheStore, 'keys')) {
                $pattern = 'employer_' . $employerId . '_search_*';
                $keys = $cacheStore->keys($pattern);

                foreach ($keys as $key) {
                    // For Redis, we need to remove the cache prefix if it exists
                    $unprefixedKey = str_replace(Cache::getPrefix(), '', $key);
                    Cache::forget($unprefixedKey);
                }
            } else {
                // For other cache stores that don't support pattern matching,
                // we can't easily clear by pattern, so log a warning
                Log::info('Cache driver does not support clearing by pattern. Consider using Redis for better cache management.');

                // Optionally, you could implement a system where you track keys in a separate location
                // For example, whenever you create a cache key, also store it in a list in the cache
                // Then here, you could retrieve and clear that list
            }
        } catch (\Exception $e) {
            Log::warning('Failed to clear employer search cache: ' . $e->getMessage());
        }
    }


    /**
     * Get candidate profile details
     * 
     * @param int $jobseekerId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCandidateProfile($jobseekerId)
    {
        try {
            $employerId = Auth::id();

            // Check if the employer has permission to view this profile
            $employer = \App\Models\User::find($employerId);
            // if (!$employer || !$employer->can_view_full_profiles) {
            //     return response()->json([
            //         'success' => false,
            //         'message' => 'You do not have permission to view full candidate profiles',
            //         'upgrade_required' => true
            //     ], 403);
            // }

            // Get the jobseeker profile with all related data
            $jobseeker = User::where('id', $jobseekerId)
                ->whereHas('roles', function ($q) {
                    $q->where('name', 'jobseeker');
                })
                ->with([
                    'jobSeekerProfile',
                    'skills',
                    'experiences' => function ($q) {
                        $q->orderBy('currently_work_here', 'desc')
                            ->orderBy('end_date', 'desc')
                            ->orderBy('start_date', 'desc');
                    },
                    'educations' => function ($q) {
                        $q->orderBy('currently_studying', 'desc')
                            ->orderBy('passed_year', 'desc')
                            ->orderBy('joined_year', 'desc');
                    },
                    'languages',
                    'trainings',
                    'certificates',
                    'references',
                    'socialLinks'
                ])
                ->first();

            if (!$jobseeker) {
                return response()->json([
                    'success' => false,
                    'message' => 'Candidate profile not found',
                ], 404);
            }

            // Check if the candidate is saved
            $isSaved = SavedCandidate::where('employer_id', $employerId)
                ->where('jobseeker_id', $jobseekerId)
                ->exists();

            // Record profile view
            // $this->recordProfileView($employerId, $jobseekerId);

            // Get the profile data
            $profileData = new ResumeResource($jobseeker);
            $profileData->additional(['saved' => $isSaved]);

            return response()->json([
                'success' => true,
                'message' => 'Candidate profile retrieved successfully',
                'data' => $profileData
            ]);
        } catch (\Exception $e) {
            Log::error('Get candidate profile error: ' . $e->getMessage(), [
                'exception' => $e,
                'jobseeker_id' => $jobseekerId
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve candidate profile',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred',
            ], 500);
        }
    }

  

    /**
     * Get candidate profile view statistics
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getProfileViewStats(Request $request)
    {
        try {
            $employerId = Auth::id();

            // Get date range parameters
            $startDate = $request->input('start_date') ? Carbon::parse($request->input('start_date')) : Carbon::now()->subDays(30);
            $endDate = $request->input('end_date') ? Carbon::parse($request->input('end_date')) : Carbon::now();

            // Get profile views by day
            $viewsByDay = ProfileView::where('employer_id', $employerId)
                ->whereBetween('created_at', [$startDate->startOfDay(), $endDate->endOfDay()])
                ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
                ->groupBy('date')
                ->orderBy('date')
                ->get();

            // Get most viewed profiles
            $mostViewedProfiles = ProfileView::where('employer_id', $employerId)
                ->whereBetween('created_at', [$startDate->startOfDay(), $endDate->endOfDay()])
                ->selectRaw('jobseeker_id, COUNT(*) as view_count')
                ->groupBy('jobseeker_id')
                ->orderBy('view_count', 'desc')
                ->limit(10)
                ->with(['jobseeker'])
                ->get();

            // Get total unique profiles viewed
            $uniqueProfilesViewed = ProfileView::where('employer_id', $employerId)
                ->whereBetween('created_at', [$startDate->startOfDay(), $endDate->endOfDay()])
                ->distinct('jobseeker_id')
                ->count('jobseeker_id');

            // Get total profile views
            $totalProfileViews = ProfileView::where('employer_id', $employerId)
                ->whereBetween('created_at', [$startDate->startOfDay(), $endDate->endOfDay()])
                ->count();

            return response()->json([
                'success' => true,
                'message' => 'Profile view statistics retrieved successfully',
                'data' => [
                    'views_by_day' => $viewsByDay,
                    'most_viewed_profiles' => $mostViewedProfiles->map(function ($view) {
                        return [
                            'jobseeker_id' => $view->jobseeker_id,
                            'name' => $view->jobseeker ?
                                $view->jobseeker->first_name . ' ' . $view->jobseeker->last_name :
                                'Unknown',
                            'view_count' => $view->view_count,
                        ];
                    }),
                    'unique_profiles_viewed' => $uniqueProfilesViewed,
                    'total_profile_views' => $totalProfileViews,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Get profile view stats error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve profile view statistics',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred',
            ], 500);
        }
    }

    /**
     * Invite a candidate to apply for a job
     * 
     * @param Request $request
     * @param int $jobseekerId
     * @return \Illuminate\Http\JsonResponse
     */
    public function inviteCandidate(Request $request, $jobseekerId)
    {
        try {
            $validator = \Validator::make($request->all(), [
                'job_id' => 'required|exists:am_jobs,id',
                'message' => 'nullable|string|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $employerId = Auth::id();
            $jobId = $request->input('job_id');

            // Check if the job belongs to the employer
            $job = \App\Models\AmJob::where('id', $jobId)
                ->where('employer_id', $employerId)
                ->first();

            if (!$job) {
                return response()->json([
                    'success' => false,
                    'message' => 'Job not found or does not belong to you',
                ], 404);
            }

            // Check if the jobseeker exists
            $jobseeker = User::find($jobseekerId);
            if (!$jobseeker || !$jobseeker->hasRole('jobseeker')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Jobseeker not found',
                ], 404);
            }

            // Check if already invited
            $existingInvitation = JobInvitation::where('employer_id', $employerId)
                ->where('jobseeker_id', $jobseekerId)
                ->where('job_id', $jobId)
                ->first();

            if ($existingInvitation) {
                return response()->json([
                    'success' => false,
                    'message' => 'You have already invited this candidate for this job',
                ], 409);
            }

            // Create the invitation
            $invitation = JobInvitation::create([
                'employer_id' => $employerId,
                'jobseeker_id' => $jobseekerId,
                'job_id' => $jobId,
                'message' => $request->input('message'),
                'status' => 'pending',
            ]);

            // Send notification to jobseeker
            $jobseeker->notify(new \App\Notifications\JobInvitation($invitation));

            return response()->json([
                'success' => true,
                'message' => 'Candidate invited successfully',
                'data' => [
                    'invitation_id' => $invitation->id,
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Invite candidate error: ' . $e->getMessage(), [
                'exception' => $e,
                'jobseeker_id' => $jobseekerId,
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to invite candidate',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred',
            ], 500);
        }
    }

    /**
     * Get all invitations sent by the employer
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getInvitations(Request $request)
    {
        try {
            $employerId = Auth::id();
            $perPage = $request->input('per_page', 10);

            // Get invitations with related data
            $invitations = JobInvitation::where('employer_id', $employerId)
                ->with(['jobseeker', 'job'])
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Invitations retrieved successfully',
                'data' => JobInvitationResource::collection($invitations),
                'meta' => [
                    'current_page' => $invitations->currentPage(),
                    'from' => $invitations->firstItem(),
                    'last_page' => $invitations->lastPage(),
                    'path' => $invitations->path(),
                    'per_page' => $invitations->perPage(),
                    'to' => $invitations->lastItem(),
                    'total' => $invitations->total(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Get invitations error: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve invitations',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred',
            ], 500);
        }
    }
}
