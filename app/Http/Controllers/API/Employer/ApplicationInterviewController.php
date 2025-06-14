<?php

namespace App\Http\Controllers\API\Employer;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\ApplicationInterviewers;
use App\Models\ApplicationInterviewType;
use App\Models\ApplicationStatusHistory;
use App\Models\ScheduleApplicationInterview;
use App\Traits\ActivityTrait;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ApplicationInterviewController extends Controller
{
    use ActivityTrait;
    public function scheduleInterview(Request $request)
{
    try {
        // Validate the request data
        $validator = Validator::make($request->all(), [
            'application_id' => 'required|exists:applications,id',
            'interview_type_id' => 'required|exists:application_interview_types,id',
            'date' => 'required|date',
            'interviewer_id' => 'required|exists:application_interviewers,id',
            'time' => 'required|date_format:H:i',
            'mode' => 'required|in:in-person,video-call,phone-call',
            'notes' => 'nullable|string',
            'status' => 'required|in:scheduled,completed,cancelled',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Verify that the application belongs to the authenticated employer
        $application = Application::with('job')->findOrFail($request->application_id);
        
        $interview = ScheduleApplicationInterview::updateOrCreate(
            [
                'application_id' => $request->application_id,
            ],
            [
            'application_id' => $request->application_id,
            'interview_type_id' => $request->interview_type_id,
            'date' => $request->date,
            'time' => $request->time,
            'mode' => $request->mode,
            'interviewer_id' => $request->interviewer_id,
            'notes' => $request->notes,
            'status' => $request->status,
        ]);

        // Record the activity
        $job = $application->job;
        $this->recordActivityByType("interview_scheduled", $job, $application->user_id);

        ApplicationStatusHistory::create([
            'application_id' => $application->id,
            'status' => 'interview_scheduled',
            'changed_at' => now(),
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Interview scheduled successfully',
            'data' => $interview,
        ]);
    } catch (Exception $e) {
        // Log the error for debugging
        Log::error('Error scheduling interview: ' . $e->getMessage(), [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString()
        ]);
        
        return response()->json([
            'error' => true,
            'message' => 'Something went wrong while scheduling the interview',
            'errors' => $e->getMessage(),
        ], 500);
    }
}


    public function addInterviewers(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
            'department' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $interviewer = ApplicationInterviewers::create([
            'name' => $request->name,
            'department' => $request->department,
            'user_id' => Auth::user()->id,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Interviewer added successfully',
            'data' => $interviewer,
        ], 201);
    }


    public function addInterviewType(Request $request)
    {
        $user = Auth::id();
        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $interviewType = ApplicationInterviewType::create([
            'name' => $request->name,
            'user_id' => $user,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Interview type added successfully',
            'data' => $interviewType,
        ], 201);
    }

    public function getInterviewType(Request $request)
    {
        $interviewTypes = ApplicationInterviewType::select('id', 'name')->get();
        return response()->json([
            'status' => true,
            'message' => 'Interview types',
            'data' => $interviewTypes,
        ]);
    }

    public function getInterviewers(Request $request)
    {
        $interviewers = ApplicationInterviewers::select('id', 'name', 'department')->get();
        return response()->json([
            'status' => true,
            'message' => 'Interviewers',
            'data' => $interviewers,
        ]);
    }


    public function withdrawInterview(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => 'Validation error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $interview = ScheduleApplicationInterview::where("application_id", $request->id)->first();


        if (!$interview) {
            return response()->json([
                'error' => true,
                'message' => 'Interview not found',
            ], 404);
        }

        $interview->delete();
        $result = ApplicationStatusHistory::where("application_id", $request->id)->delete();


        return response()->json([
            'status' => true,
            'message' => 'Interview withdrawn successfully',
        ]);
    }
}
