<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AmJobResource;
use App\Models\AmJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AllApplicationController extends Controller
{
    public function index(Request $request)
    {
        $query = AmJob::with([
        "employer:company_name,id",
        "applications" => function ($query) use($request) {
            $query->with([
                'applicationStatusHistory' => function ($query)  {
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
    ]);

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
}
