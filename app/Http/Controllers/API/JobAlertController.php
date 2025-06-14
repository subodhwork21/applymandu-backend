<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\JobAlertResource;
use App\Models\JobAlert;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class JobAlertController extends Controller
{
    public function index(Request $request){
        $user_id = Auth::user()->id;
        $query = JobAlert::query();
        $query->where("user_id", $user_id);
        $query->when($request->filled("search"), function($query)use ($request){
            $query->where("alert_title", "like", "%{$request->search}%");
            $query->orWhere("job_category", "like", "%{$request->search}%")->orWhere("location", "like", "%{$request->search}%");
        });

        $query->when($request->filled("status"), function($query)use ($request){
            $query->where("status", $request->status);
        });

        if(!empty(is_array($request->all()))){
            foreach($request->except("search", "status") as $key => $value){
                $query->where($key, "like", "%{$value}%");
            }
        }
        
        return JobAlertResource::collection($query->paginate());
    }

    public function store(Request $request){
        $validation = Validator::make($request->all(), [
            'alert_title' => 'required|string|max:255',
            'job_category' => 'required|string|max:255',
            'experience_level' => 'required|string|max:255',
            'salary_min' => 'required|integer',
            'salary_max' => 'required|integer',
            'location' => 'required|string|max:255',
            'keywords' => 'required|string|max:255',
            'alert_frequency' => 'required|string|max:255',
            'status' => 'nullable|string|max:255',
        ]);

        if($validation->fails()){
            return response()->json([
                'error' => true,
                'message' => 'Validation error',
                'errors' => $validation->errors(),
            ], 422);
        }

        $user_id = Auth::user()->id;
        $request->merge(["user_id" => $user_id]);
        $request->merge(["status" => "active"]);
        $alert = JobAlert::create($request->all());
        return response()->json([
            'success' => true,
            'message' => 'Job alert created successfully',
            // 'data' => new JobAlertResource($alert),
        ], 201);
    }

    public function update(Request $request, $id){
        $validation = Validator::make($request->all(), [
            'alert_title' => 'required|string|max:255',
            'job_category' => 'required|string|max:255',
            'experience_level' => 'required|string|max:255',
            'salary_min' => 'required|integer',
            'salary_max' => 'required|integer',
            'location' => 'required|string|max:255',
            'keywords' => 'required|string|max:255',
            'alert_frequency' => 'required|string|max:255',
            'status' => 'nullable|string|max:255',
        ]);

        if($validation->fails()){
            return response()->json([
                'error' => true,
                'message' => 'Validation error',
                'errors' => $validation->errors(),
            ], 422);
        }

        $alert = JobAlert::find($id);
        if(!$alert){
            return response()->json([
                'error' => true,
                'message' => 'Job alert not found',
            ], 404);
        }
        $alert->update($request->except("user_id"));
        return response()->json([
            'success' => true,
            'message' => 'Job alert updated successfully',
            // 'data' => new JobAlertResource($alert),
        ], 200);
    }

    public function destroy($id){
        $alert = JobAlert::find($id);
        if(!$alert){
            return response()->json([
                'error' => true,
                'message' => 'Job alert not found',
            ], 404);
        }
        $alert->delete();
        return response()->json([
            'success' => true,
            'message' => 'Job alert deleted successfully',
        ], 200);
    }


    public function pauseAlert($id){
        $alert = JobAlert::find($id);
        if(!$alert){
            return response()->json([
                'error' => true,
                'message' => 'Job alert not found',
            ], 404);
        }
        if($alert->status == 'paused'){
            $alert->status = "active";
        }
        else 
            $alert->status = 'paused';
        $alert->save();
        return response()->json([
            'success' => true,
            'message' => 'Job alert '.$alert->status.' successfully',
        ], 200);
    }

}
