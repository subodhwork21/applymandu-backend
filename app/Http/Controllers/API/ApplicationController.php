<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\AmJobResource;
use App\Http\Resources\ApplicationResource;
use App\Models\AmJob;
use App\Models\Application;
use App\Models\ApplicationStatusHistory;
use App\Models\User;
use App\Traits\ActivityTrait;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;


class ApplicationController extends Controller
{

    use ActivityTrait, SoftDeletes;

   public function index(Request $request)
{
    $query = AmJob::with([
        "employer:company_name,id,image",
        "applications" => function ($query) {
            $query->where('user_id', Auth::id())
                ->with(['applicationStatusHistory' => function ($query) {
                    $query->latest('changed_at');
                }]);
        }
    ])->whereHas("applications", function ($query) {
        $query->where("applications.user_id", Auth::id());
    });

    // Filter by status if provided in the request
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

    $jobs = $query->get();

    // Add latest status to each application
    $jobs->each(function ($job) {
        $job->applications->each(function ($application) {
            $application->latest_status = $application->applicationStatusHistory->first() ?
                $application->applicationStatusHistory->first()->status : null;
        });
    });

    return AmJobResource::collection($jobs);
}




    public function apply(Request $request, $job_id)
    {
        DB::beginTransaction();
    
        try {
            $user = User::find(Auth::id());
            $job = AmJob::find($job_id);
    
            if (!$job) {
                return response()->json([
                    'error' => true,
                    'message' => 'Job not found'
                ], 404);
            }
    
            // if ($user->hasRole('employer')) {
            //     return response()->json([
            //         'error' => true,
            //         'message' => 'Employers are not allowed to apply for jobs.'
            //     ], 403);
            // }
    
            $validation = Validator::make($request->all(), [
                'year_of_experience' => 'required|integer|max:25|min:0',
                'expected_salary' => 'required|numeric|min:0|max:1000000',
                'notice_period' => 'required|integer|max:100',
                'cover_letter' => 'nullable|string|max:1000',
            ]);
    
            if ($validation->fails()) {
                return response()->json([
                    'error' => true,
                    'message' => 'Validation failed',
                    'errors' => $validation->errors()
                ], 422);
            }


            

    
            // Check if an application already exists
            $existingApplication = Application::where('user_id', $user->id)
                ->where('job_id', $job_id)
                ->first();
    
            if ($existingApplication) {
                // Update the existing application instead of creating a new one
                $existingApplication->update([
                    'year_of_experience' => $request->year_of_experience,
                    'expected_salary' => $request->expected_salary,
                    'notice_period' => $request->notice_period,
                    'cover_letter' => $request->cover_letter,
                    'updated_at' => now(),
                ]);
    
                // Use the existing application
                $application = $existingApplication;
            } else {
                // Create a new application if one doesn't exist
                $user->jobs()->attach($job_id, [
                    'year_of_experience' => $request->year_of_experience,
                    'expected_salary' => $request->expected_salary,
                    'notice_period' => $request->notice_period,
                    'cover_letter' => $request->cover_letter,
                    'status' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                    'applied_at' => now(),
                ]);
    
                // Retrieve the newly created application
                $application = Application::where('user_id', $user->id)
                    ->where('job_id', $job_id)
                    ->latest('id')
                    ->first();
    
                // Create a new application status history entry for new applications
                ApplicationStatusHistory::create([
                    'application_id' => $application->id,
                    'status' => 'applied',
                    'remarks' => null,
                    'changed_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
    
            // Record the activity regardless of whether it's a new or updated application
            $this->recordActivityByType("application_submitted", $job, $user->id);
    
            DB::commit();
    
            return response()->json([
                'success' => true,
                'message' => 'Application ' . ($existingApplication ? 'updated' : 'submitted') . ' successfully.'
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
    
            return response()->json([
                'error' => true,
                'message' => 'Something went wrong while applying.',
                'details' => $e->getMessage()
            ], 500);
        }
    }
    


    public function show(string $id)
    {
        $userId = Auth::id();
        $application = Application::with(['job.employer:id,company_name,email,phone,image', "applicationStatusHistory"])
            ->where('id', $id)
            ->where('user_id', $userId)
            ->first();

        if (!$application) {
            return response()->json([
                'error' => true,
                'message' => 'Application not found'
            ], 404);
        }

        // Get the job details associated with this application
        // $job = $application->job;

        // You can customize the response structure as needed
        return new ApplicationResource($application);
    }


    public function update(Request $request, string $id)
    {
        DB::beginTransaction();

        try {
            $user = User::find(Auth::id());
            $application = $user->applications()->where('id', $id)->first();

            if (!$application) {
                return response()->json([
                    'error' => true,
                    'message' => 'Application not found'
                ], 404);
            }

            $validation = Validator::make($request->all(), [
                'year_of_experience' => 'required|integer|min:0',
                'expected_salary' => 'required|numeric|min:0',
                'notice_period' => 'required|integer|max:1000',
                'cover_letter' => 'nullable|string|max:1000',
            ]);

            if ($validation->fails()) {
                return response()->json([
                    'error' => true,
                    'message' => 'Validation failed',
                    'errors' => $validation->errors()
                ], 422);
            }

            $application->update($request->all());

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Application updated successfully.'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'error' => true,
                'message' => 'Something went wrong while updating the application.',
                'details' => $e->getMessage()
            ], 500);
        }
    }


   public function destroy(string $id)
{
    DB::beginTransaction();

    try {
        $user = User::find(Auth::id());
        $application = $user->applications()->where('id', $id)->first();

        if (!$application) {
            return response()->json([
                'error' => true,
                'message' => 'Application not found'
            ], 404);
        }

        // Set the restore_until timestamp (e.g., 24 hours from now)
        $restoreUntil = now()->addHours(24);
        $application->restore_until = $restoreUntil;
        $application->save();

        // Soft delete the application
        $application->delete();
        
        // We don't delete the status history, just soft delete the application
        // This allows for complete restoration

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'Application withdrawn successfully',
            'data' => [
                'application_id' => $application->id,
                'can_undo_until' => $restoreUntil->toIso8601String(),
                'undo_url' => route('application.restore', $application->id)
            ]
        ], 200);
    } catch (\Exception $e) {
        DB::rollBack();

        return response()->json([
            'error' => true,
            'message' => 'Something went wrong while withdrawing the application.',
            'details' => $e->getMessage()
        ], 500);
    }
}

/**
 * Restore a previously deleted application
 */
public function restore(string $id)
{
    DB::beginTransaction();

    try {
        // Find the soft-deleted application
        $application = Application::withTrashed()
            ->where('id', $id)
            ->where('user_id', Auth::id())
            ->first();

        if (!$application) {
            return response()->json([
                'error' => true,
                'message' => 'Application not found'
            ], 404);
        }

        // Check if the restore period has expired
        if ($application->restore_until < now()) {
            return response()->json([
                'error' => true,
                'message' => 'The undo period for this application has expired'
            ], 400);
        }

        // Restore the application
        $application->restore();
        $application->restore_until = null;
        $application->save();

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'Application restored successfully'
        ]);
    } catch (\Exception $e) {
        DB::rollBack();

        return response()->json([
            'error' => true,
            'message' => 'Something went wrong while restoring the application.',
            'details' => $e->getMessage()
        ], 500);
    }
}


    public function updateApplicationStatus(Request $request, $id)
    {


        // $application = Application::find($id);

        // if (!$application || $application->user_id != Auth::id()) {
        //     return response()->json([
        //         'error' => true,
        //         'message' => 'Application not found'
        //     ], 404);
        // }

        DB::beginTransaction();



        try {
            $validation = Validator::make($request->all(), [
                'status' => 'required|string|in:applied,under_review,shortlisted,interview_scheduled,interviewed,selected,withdrawn,rejected',
                'remarks' => 'nullable|string|max:1000',
            ]);
            if ($validation->fails()) {
                return response()->json([
                    'error' => true,
                    'message' => 'Validation failed',
                    'errors' => $validation->errors()
                ], 422);
            }
            $application = Application::where(['id' => $id])->first();

            if (!$application || $application->job->employer->id != Auth::id()) {
                return response()->json([
                    'error' => true,
                    'message' => 'Application not found'
                ], 404);
            }

            // Check if the status is already set to the same value
            $oldStatus = DB::table('application_status_histories')
                ->where('application_id', $application->id)
                ->latest('changed_at')
                ->first();

            if ($oldStatus && $oldStatus->status === $request->status) {
                return response()->json([
                    'error' => true,
                    'message' => 'Application status is already set to this value'
                ], 409);
            }

            // Update the application status
            DB::table('application_status_histories')->insert([
                'application_id' => $application->id,
                'status' => $request->status,
                'remarks' => $request->remarks,
                'changed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->recordActivityByType($request->status, $application->job_id);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Application status updated successfully.'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'error' => true,
                'message' => 'Something went wrong while updating the application status.',
                'details' => $e->getMessage()
            ], 500);
        }
    }
}
