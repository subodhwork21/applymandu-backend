<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AmJobResource;
use App\Models\AmJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AllJobController extends Controller
{
    public function index(Request $request)
    {
        $query = AmJob::select("id", "slug", "department", "employer_id", "location_type", 'title', 'location', 'location_type', 'employment_type', 'salary_min', 'salary_max', 'posted_date', 'status', 'job_label')->with('skills', 'employer:company_name,id,image');


        $query->when($request->filled('label'), function ($query) use ($request) {
            $query->where('job_label', $request->label);
        });

        $query->when($request->filled("department"), function($query) use ($request){
            if($request->department != "all"){
                $query->where("department", "like", "%" . $request->department . "%");
            }
        });

        $query->when($request->filled("status"), function($query) use ($request){
            $query->where("status", $request->status == "active" ? 1 : 0);
        });

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
                $subq->where('title', 'like', $search)
                    ->orWhere('description', 'like', $search)->orWhereHas("employer", function ($query) use ($search) {
                        $query->where("company_name", "like", "%$search%");
                    })->orWhereHas("skills", function ($query) use ($search) {
                        $query->where('skills.name', 'like', "%$search%");
                    })->orWhere("description", "like", "%$search%")->orWhere("department", "like", "%$search%");
                // $subq->search($request->search);
            })
        );

        $sortField = $request->input('sort_by', 'posted_date');
        $sortOrder = $request->input('sort_order', 'desc');
        $perPage = (int) $request->input('per_page', 10);
        $perPage = ($perPage > 0 && $perPage <= 100) ? $perPage : 10; // max 100 per page
        $page = (int) $request->input('page', 1);


        $jobs = $query->paginate($perPage, ['*'], 'page', $page);

        // $cacheKey = 'jobs:' . md5(json_encode([
        //     'filters' => $request->except('page'),
        //     'page' => $page,
        //     'perPage' => $perPage,
        //     'sortField' => $sortField,
        //     'sortOrder' => $sortOrder,
        // ]));

        // $jobs = Cache::remember($cacheKey, now()->addMinutes(5), function () use ($query, $sortField, $sortOrder, $perPage) {
        //     if ($sortField === "salary-high") {
        //         return $query->orderBy('salary_max', 'desc')->paginate($perPage);
        //     }
        //     if ($sortField === "salary-low") {
        //         return $query->orderBy('salary_max', 'asc')->paginate($perPage);
        //     }
        //     return $query->orderBy($sortField, $sortOrder)->paginate($perPage);
        // });

        return AmJobResource::collection($jobs)
            ->response()
            ->setStatusCode(200);
    }


    public function bulkDeleteJobs(Request $request)
    {
        $validation = Validator::make($request->all(), [
            'ids' => 'required|array',
            'ids.*' => 'required|integer|exists:am_jobs,id',
        ]);

        if ($validation->fails()) {
            return response()->json([
                'error' => true,
                'message' => 'Validation failed',
                'errors' => $validation->errors(),
            ], 422);
        }

        AmJob::whereIn('id', $request->ids)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Jobs deleted successfully',
        ]);
    }
}
