<?php

namespace App\Http\Controllers\API\Employer;

use App\Http\Controllers\Controller;
use App\Http\Resources\AmJobResource;
use App\Http\Resources\ApplicationResource;
use App\Http\Resources\ResumeResource;
use App\Models\AmJob;
use App\Models\Application;
use App\Models\User;
use App\Traits\ActivityTrait;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ApplicationController extends Controller
{
    use ActivityTrait;

    public function index(Request $request)
    {
        $query = AmJob::with([
            "employer:company_name,id",
            "applications" => function ($query) use ($request) {
                $query->with([
                    'applicationStatusHistory' => function ($query) {
                        $query->orderBy('changed_at', 'desc');
                    },
                    'user' => function ($query) {
                        $query->select("id", "first_name", "last_name", "email", "phone", "image")
                            ->with(['skills', 'experiences', 'educations']); // Include user skills and other relevant info
                    }
                ]);

                $query->orderBy('created_at', $request->orderby == "newest" ? 'desc' : 'asc');

                if ($request->has('orderby') && $request->orderby == "today") {
                    $query->whereDate('created_at', now());
                }
            }
        ])->where("employer_id", Auth::user()->id);

        if ($request->has('status') && $request->status) {
            $query->whereHas("applications.applicationStatusHistory", function ($query) use ($request) {
                $query->where("status", $request->status)
                    ->whereIn('id', function ($subQuery) {
                        $subQuery->selectRaw('MAX(id)')
                            ->from('application_status_histories')
                            ->groupBy('application_id');
                    });
            });
        }

        // Filter by job if provided
        if ($request->has('job_id') && $request->job_id) {
            $query->where('id', $request->job_id);
        }

        // Filter by search term if provided
        if ($request->has('search') && $request->search) {
            $searchTerm = '%' . $request->search . '%';
            $query->whereHas('applications.user', function ($query) use ($searchTerm) {
                $query->where('first_name', 'like', $searchTerm)
                    ->orWhere('last_name', 'like', $searchTerm)
                    ->orWhere('email', 'like', $searchTerm);
            });
        }

        // Add pagination
        $perPage = $request->input('per_page', 10);
        $jobs = $query->paginate($perPage);

        // Add latest status to each application
        $jobs->each(function ($job) {
            $job->applications->each(function ($application) {
                // Add latest status
                $application->latest_status = $application->applicationStatusHistory->first() ?
                    $application->applicationStatusHistory->first()->status : null;

                // Calculate days since application
                $application->days_since_applied = $application->created_at ?
                    now()->diffInDays($application->created_at) : null;
            });
        });

        // Return the paginated resource collection directly
        // This will automatically include Laravel's pagination metadata
        return AmJobResource::collection($jobs)
            ->additional([
                'success' => true,
                'message' => 'Applications fetched successfully'
            ]);
    }




    public function viewApplication($id)
    {
        $userId = Auth::user()->id;

        $query = Application::with("user", "job", "applicationStatusHistory")->where("id", $id);

        if (!$query->exists()) {
            return response()->json([
                'error' => true,
                'message' => 'Application not found'
            ], 404);
        }

        $query->whereHas('job', function ($query) use ($userId) {
            $query->where('employer_id', $userId);
        });

        $this->recordActivityByType('application_viewed', $query->first()->job, $query->first()->user_id);


        return response()->json([
            'success' => true,
            'data' => ApplicationResource::collection($query->get())
        ]);
    }


    public function generateDocument(Request $request, $id)
    {
        $application = Application::where('id', $id)->first();
        if (!$application) {
            return response()->json([
                'error' => true,
                'message' => 'Application not found'
            ], 404);
        }
        $userId = $application->user_id;
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
        $employer = Auth::user();
        $this->recordActivityByType("resume_downloaded", $employer, $user->id);
        $pdf = Pdf::loadView('pdf.resume-template', ['user' => new ResumeResource($user)]);
        return response($pdf->output(), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="cv.pdf"');
    }


    public function generateDocumentByProfile(Request $request, $id)
    {
        $userId = $id;
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
        $pdf = Pdf::loadView('pdf.resume-template', ['user' => new ResumeResource($user)]);
        $employer = Auth::user();
        $this->recordActivityByType("resume_downloaded", $employer, $id);
        return response($pdf->output(), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="cv.pdf"');
    }


    public function applicationSummary(Request $request)
    {
        $id = Auth::user()->id;

        $results = Application::selectRaw('
        COUNT(*) as total_applications,
        SUM(CASE WHEN latest_status.status = "applied" THEN 1 ELSE 0 END) as applied_applications,
        SUM(CASE WHEN latest_status.status = "interviewed" THEN 1 ELSE 0 END) as interview_applications,
        SUM(CASE WHEN latest_status.status = "interview_scheduled" THEN 1 ELSE 0 END) as interview_scheduled_applications,
        SUM(CASE WHEN latest_status.status = "shortlisted" THEN 1 ELSE 0 END) as shortlisted_applications,
        SUM(CASE WHEN latest_status.status = "hired" THEN 1 ELSE 0 END) as hired_applications,
        SUM(CASE WHEN latest_status.status = "rejected" THEN 1 ELSE 0 END) as rejected_applications
    ')
            ->join('am_jobs', 'applications.job_id', '=', 'am_jobs.id')
            ->join('application_status_histories as latest_status', function ($join) {
                $join->on('applications.id', '=', 'latest_status.application_id')
                    ->whereIn('latest_status.id', function ($query) {
                        $query->selectRaw('MAX(id)')
                            ->from('application_status_histories')
                            ->groupBy('application_id');
                    });
            })
            ->where('am_jobs.employer_id', $id)
            ->first();

        return response()->json([
            'total_applications' => $results->total_applications,
            'applied_applications' => $results->applied_applications,
            'interviewed_applications' => $results->interview_applications,
            'interview_scheduled_applications' => $results->interview_scheduled_applications,
            'shortlisted_applications' => $results->shortlisted_applications,
            'hired_applications' => $results->hired_applications,
            'rejected_applications' => $results->rejected_applications
        ], 200);
    }
}
