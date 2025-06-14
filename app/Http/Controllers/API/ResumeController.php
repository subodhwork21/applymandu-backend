<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\ResumeRequest;
use App\Http\Resources\ResumeResource;
use App\Models\Activity;
use App\Models\JobSeekerCertificate;
use App\Models\JobSeekerEducation;
use App\Models\JobSeekerExperience;
use App\Models\JobSeekerLanguage;
use App\Models\JobSeekerProfile;
use App\Models\JobSeekerReference;
use App\Models\JobSeekerSocialLink;
use App\Models\JobSeekerTraining;
use App\Models\Skill;
use App\Models\User;
use App\Traits\ActivityTrait;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ResumeController extends Controller
{
    use ActivityTrait;

    protected $profileCompletionService;

    public function __construct(User $profileCompletionService)
    {
        $this->profileCompletionService = $profileCompletionService;
    }

    public function index(Request $request)
    {
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

        $query->whereHas('roles', function ($q) {
            $q->where('name', 'jobseeker');
        });

        // Basic search by name
        if ($request->has('name')) {
            $query->where(function ($q) use ($request) {
                $q->where('first_name', 'like', '%' . $request->name . '%')
                    ->orWhere('last_name', 'like', '%' . $request->name . '%');
            });
        }

        // Filter by skills
        if ($request->has('skills')) {
            $skills = explode(',', $request->skills);
            $query->whereHas('skills', function ($q) use ($skills) {
                $q->whereIn('name', $skills);
            });
        }

        // Filter by experience level (years)
        if ($request->has('min_experience')) {
            $query->whereHas('experiences', function ($q) use ($request) {
                $q->selectRaw('SUM(DATEDIFF(IFNULL(end_date, CURRENT_DATE), start_date)/365) as total_years')
                    ->havingRaw('total_years >= ?', [$request->min_experience]);
            });
        }

        // Filter by education level
        if ($request->has('education_level')) {
            $query->whereHas('educations', function ($q) use ($request) {
                $q->where('degree_level', $request->education_level);
            });
        }

        // Filter by job title
        if ($request->has('job_title')) {
            $query->whereHas('experiences', function ($q) use ($request) {
                $q->where('job_title', 'like', '%' . $request->job_title . '%');
            });
        }

        // Filter by location
        if ($request->has('location')) {
            $query->whereHas('jobSeekerProfile', function ($q) use ($request) {
                $q->where('location', 'like', '%' . $request->location . '%');
            });
        }

        // Filter by industry
        if ($request->has('industry')) {
            $query->whereHas('jobSeekerProfile', function ($q) use ($request) {
                $q->where('industry', 'like', '%' . $request->industry . '%');
            });
        }

        // Filter by languages
        if ($request->has('languages')) {
            $languages = explode(',', $request->languages);
            $query->whereHas('languages', function ($q) use ($languages) {
                $q->whereIn('language', $languages);
            });
        }

        // Filter by salary expectations
        if ($request->has('max_salary_expectation')) {
            $query->whereHas('jobSeekerProfile', function ($q) use ($request) {
                $q->where('salary_expectation', '<=', $request->max_salary_expectation);
            });
        }

        if ($request->has('min_salary_expectation')) {
            $query->whereHas('jobSeekerProfile', function ($q) use ($request) {
                $q->where('salary_expectation', '>=', $request->min_salary_expectation);
            });
        }

        // Sort by date (most recent profiles first)
        if ($request->has('sort_by')) {
            if ($request->sort_by === 'date') {
                $query->orderBy('created_at', 'desc');
            } elseif ($request->sort_by === 'experience') {
                // More complex sorting can be added here
                $query->withCount('experiences')
                    ->orderBy('experiences_count', 'desc');
            }
        } else {
            // Default sort by recent first
            $query->orderBy('created_at', 'desc');
        }

        // Pagination
        $perPage = $request->has('per_page') ? (int)$request->per_page : 10;
        $jobSeekers = $query->paginate($perPage);

        return ResumeResource::collection($jobSeekers);
    }





    public function store(ResumeRequest $request)
    {
        try {
            DB::beginTransaction();

            $userId = Auth::id();
            $validated = $request->validated();

            JobSeekerProfile::updateOrCreate(
                ['user_id' => $userId],
                [
                    'first_name' => $validated['first_name'],
                    'middle_name' => $validated['middle_name'] ?? null,
                    'last_name' => $validated['last_name'],
                    'district' => $validated['district'],
                    'municipality' => $validated['municipality'],
                    'city_tole' => $validated['city_tole'],
                    'date_of_birth' => $validated['date_of_birth'],
                    'mobile' => $validated['mobile'] ?? null,
                    'industry' => $validated['industry'],
                    'preferred_job_type' => $validated['preferred_job_type'],
                    'gender' => $validated['gender'],
                    'has_driving_license' => $validated['has_driving_license'] ?? false,
                    'has_vehicle' => $validated['has_vehicle'] ?? false,
                    'career_objectives' => $validated['career_objectives'] ?? null,
                    'looking_for' => $validated['looking_for'] ?? null,
                    'salary_expectations' => $validated['salary_expectations'] ?? null,
                ]
            );

            JobSeekerExperience::where('user_id', $userId)->delete();
            if (isset($validated['work_experiences'])) {

                foreach ($validated['work_experiences'] as $experience) {
                    JobseekerExperience::create([
                        'user_id' => $userId,
                        'position_title' => $experience['position_title'],
                        'company_name' => $experience['company_name'],
                        'industry' => $experience['industry'],
                        'job_level' => $experience['job_level'],
                        'roles_and_responsibilities' => $experience['roles_and_responsibilities'] ?? null,
                        'start_date' => $experience['start_date'],
                        'end_date' => $experience['currently_work_here'] ? null : ($experience['end_date'] ?? null),
                        'currently_work_here' => $experience['currently_work_here'] ?? false,
                    ]);
                }
            }

            JobSeekerEducation::where('user_id', $userId)->delete();
            if (isset($validated['educations'])) {

                foreach ($validated['educations'] as $education) {
                    JobseekerEducation::create([
                        'user_id' => $userId,
                        'degree' => $education['degree'],
                        'subject_major' => $education['subject_major'],
                        'institution' => $education['institution'],
                        'university_board' => $education['university_board'],
                        'grading_type' => $education['grading_type'] ?? null,
                        'joined_year' => $education['joined_year'],
                        'passed_year' => $education['currently_studying'] ? null : ($education['passed_year'] ?? null),
                        'currently_studying' => $education['currently_studying'] ?? false,
                    ]);
                }
            }

            $user = User::find($userId);
            $user->skills()->detach();
            if (isset($validated['skills'])) {

                foreach ($validated['skills'] as $skillName) {
                    $skill = Skill::firstOrCreate(['name' => $skillName]);

                    $user->skills()->attach($skill->id);
                }
            }

            JobSeekerLanguage::where('user_id', $userId)->delete();
            if (isset($validated['languages'])) {

                foreach ($validated['languages'] as $language) {
                    JobseekerLanguage::create([
                        'user_id' => $userId,
                        'language' => $language['language'],
                        'proficiency' => $language['proficiency'],
                    ]);
                }
            }

            JobSeekerTraining::where('user_id', $userId)->delete();
            if (isset($validated['trainings'])) {

                foreach ($validated['trainings'] as $training) {
                    JobseekerTraining::create([
                        'user_id' => $userId,
                        'title' => $training['title'],
                        'description' => $training['description'] ?? null,
                        'institution' => $training['institution'] ?? null,
                    ]);
                }
            }

            JobSeekerCertificate::where('user_id', $userId)->delete();
            if (isset($validated['certificates'])) {

                foreach ($validated['certificates'] as $certificate) {
                    JobseekerCertificate::create([
                        'user_id' => $userId,
                        'title' => $certificate['title'],
                        'year' => $certificate['year'] ?? null,
                        'issuer' => $certificate['issuer'] ?? null,
                    ]);
                }
            }

            JobSeekerSocialLink::where('user_id', $userId)->delete();
            if (isset($validated['social_links'])) {

                foreach ($validated['social_links'] as $link) {
                    JobseekerSocialLink::create([
                        'user_id' => $userId,
                        'url' => $link['url'],
                        'platform' => $link['platform'],
                    ]);
                }
            }

            JobSeekerReference::where('user_id', $userId)->delete();
            if (isset($validated['references'])) {

                foreach ($validated['references'] as $reference) {
                    JobseekerReference::create([
                        'user_id' => $userId,
                        'name' => $reference['name'],
                        'position' => $reference['position'] ?? null,
                        'company' => $reference['company'] ?? null,
                        'email' => $reference['email'] ?? null,
                        'phone' => $reference['phone'] ?? null,
                    ]);
                }
            }

            $this->profileCompletionService->clearCache($user);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Resume information saved successfully',
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'error' => true,
                'message' => 'Failed to save resume information',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    public function show(string $id)
    {
        $validate = Validator::make(['id'=> $id], [
            'id' => 'required|integer|exists:users,id',
        ]);
        if ($validate->fails()) {
            return response()->json([
                'error' => true,
                'message' => 'Validation failed',
                'details' => $validate->errors()
            ], 422);
        }
        $ifJobSeeker = JobSeekerProfile::where('user_id', $id)->first();

        if (!$ifJobSeeker) {
            return response()->json([
                'error' => true,
                
                'message' => 'Job Seeker Profile not found'
            ], 404);
        }

        $jobSeekerUser = User::with([
            'jobSeekerProfile',
            'experiences',
            'educations',
            'languages',
            'trainings',
            'certificates',
            'socialLinks',
            'references',
            'skills'
        ])->find($id);
        if (!$jobSeekerUser) {
            return response()->json([
                'error' => true,
                'message' => 'Job Seeker not found'
            ], 404);
        }
        return new ResumeResource($jobSeekerUser);
    }


    public function showResume(){
        $user_id = Auth::user()->id;
        return $this->show($user_id);
    }


    public function destroy(string $id)
    {
        //delete whole resume

        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'error' => true,
                'message' => 'User not found'
            ], 404);
        }
        DB::beginTransaction();
        try {
            $user->jobSeekerProfile()->delete();
            $user->experiences()->delete();
            $user->educations()->delete();
            $user->languages()->delete();
            $user->trainings()->delete();
            $user->certificates()->delete();
            $user->socialLinks()->delete();
            $user->references()->delete();
            $user->skills()->detach();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Resume deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'error' => true,
                'message' => 'Failed to delete resume: ' . $e->getMessage()
            ], 500);
        }
    }

    public function generateResume()
    {
        $userId = Auth::id();
        $user = User::with([
            'jobSeekerProfile',
            'experiences',
            'educations',
            'languages',
            'trainings',
            'certificates',
            'socialLinks',
            'references',
            'skills'
        ])->find($userId);
        if (!$user) {
            return response()->json([
                'error' => true,
                'message' => 'User not found'
            ], 404);
        }
        $pdf = PDF::loadView('pdf.resume-template', ['user' => new ResumeResource($user)]);
        return response($pdf->output(), 200)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'inline; filename="resume.pdf"');
       

       
    }


    public function resumeStats(){
        $userId = Auth::id();
        $profileViews = Activity::where("type", 'profile_viewed')
        ->where("user_id", $userId)
        ->count();
        $resumeDownloads = Activity::where("type", 'resume_downloaded')
        ->where("user_id", $userId)
        ->count();
        $resumeUpdated = JobSeekerProfile::select('updated_at')->where("user_id", $userId)->first();
        $stats = [
            'profile_views' => $profileViews,
            'resume_downloads' => $resumeDownloads,
            'resume_updated_at' => $resumeUpdated ? Carbon::parse($resumeUpdated->updated_at)->format('Y-m-d') : null,
        ];
        return response()->json([
            'success' => true,
            'message' => 'Resume stats',
            'data' => $stats
        ], 200);
    }
}
