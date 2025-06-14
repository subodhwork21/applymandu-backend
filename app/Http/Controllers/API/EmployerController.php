<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Resources\EmployerResource;
use App\Http\Resources\UserResource;
use App\Models\EmployerProfile;
use App\Models\User;
use App\Traits\FileUploadTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class EmployerController extends Controller
{
    use FileUploadTrait;

    public function index(Request $request)
    {
        $query = User::with('employerProfile')
            ->whereHas('roles', function ($query) {
                $query->where('name', 'employer');
            });


        foreach ($request->except('page', 'page_size') as $key => $value) {
            if (in_array($key, ['address', 'description', 'industry']) && !empty($value)) {
                $query->where($key, 'like', '%' . $value . '%');
            }
        }

        $pageSize = intval($request->input('page_size', 10));
        $pageSize = $pageSize > 0 && $pageSize <= 100 ? $pageSize : 10;

        $result = $query->paginate($pageSize);

        $cacheKey = 'employers:' . md5(json_encode($request->query()));

        if (Cache::has($cacheKey)) {
            $result = Cache::get($cacheKey);
        } else {
            $result = $query->paginate($pageSize);
            Cache::put($cacheKey, $result, now()->addMinutes(5));
        }


        // Return the collection directly
        return EmployerResource::collection($result->appends($request->query()));
    }



    public function getEmployer($id)
    {
        // Fetch a specific employer with their jobs
        $employer = User::with('employerProfile')->find($id);
        if (!$employer) {
            return response()->json([
                'error' => true,
                'message' => 'Employer not found'
            ], 404);
        }

        return new EmployerResource($employer);
    }

    public function store(Request $request)
    {
        // Validate and create a new employer
        $validatedData = Validator::make($request->all(), [
            'address' => 'required|string|max:255',
            'website' => 'required|string|max:255',
            'logo' => 'required|image|max:2048',
            'description' => 'required|string',
            'industry' => ['required', 'string', Rule::in(['technology', 'healthcare', 'finance', 'education', 'manufacturing', 'retail', 'hospitality', 'construction', 'agriculture', 'transportation', 'media', 'telecom', 'energy', 'real_estate', 'consulting', 'legal', 'nonprofit', 'government', 'pharma', 'automotive', 'aerospace', 'ecommerce', 'food', 'insurance', 'marketing'])],
            'size' => 'required|integer',
            'founded_year' => 'required|integer',
            'two_fa' => 'nullable|boolean',
            'notification' => 'nullable|boolean',
        ]);

        DB::beginTransaction();
        try {
            if ($validatedData->fails()) {
                return response()->json([
                    'error' => true,
                    'message' => 'Validation failed',
                    'errors' => $validatedData->errors()
                ], 422);
            }

            // Check if the user already has an employer profile
            if (Auth::user()->employerProfile) {
                return response()->json([
                    'error' => true,
                    'message' => 'You already have an employer profile'
                ], 422);
            }

            $validatedData = $validatedData->validated();


            $validatedData['user_id'] = Auth::user()->id;

            
            if ($request->hasFile('logo')) {
                $path = $this->uploadEmployerImage($request->file('logo'));
                $validatedData['logo'] = $path;
            }
            // Create the employer profile

            $employer = EmployerProfile::create($validatedData);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Employer created successfully',
                'data' => new EmployerResource($employer)
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => true,
                'message' => 'Failed to create employer',
                'errors' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            DB::beginTransaction();
            $validatedData = Validator::make($request->all(), [
                'address' => 'required|string|max:255',
                'website' => 'required|string|max:255',
                'logo' => 'nullable|image|max:2048',
                'description' => 'required|string',
                'industry' => 'required|string|max:255',
                'size' => 'required|integer',
                'founded_year' => 'required|integer',
                'two_fa' => 'nullable|boolean',
                'notification' => 'nullable|boolean',
            ]);
            
            if ($validatedData->fails()) {
                return response()->json([
                    'error' => true,
                    'message' => 'Validation failed',
                    'errors' => $validatedData->errors()
                ], 422);
            }

            if(Auth::user()->employerProfile->id != $id){
                return response()->json([
                    'error' => true,
                    'message' => 'You are not authorized to update this employer'
                ], 403);
            }

            $validatedData = $validatedData->validated();

            // Find the employer profile
            $employer = EmployerProfile::findOrFail($id);
            if (!$employer) {
                return response()->json([
                    'error' => true,
                    'message' => 'Employer not found'
                ], 404);
            }

            // Handle file upload for logo
            if ($request->hasFile('logo')) {
                $path = $this->uploadEmployerImage($request->file('logo'));
                $validatedData['logo'] = $path;
            }

            // Update the employer profile
            $employer->update($validatedData);

            // Clear the cache for the specific employer
            $cacheKey = 'employers:' . md5(json_encode($request->query()));
            Cache::forget($cacheKey);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Employer updated successfully',
                'data' => new EmployerResource($employer)
            ], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => true,
                'message' => 'Failed to update employer',
                'errors' => $e->getMessage()
            ], 500);
        }
    }
    public function destroy($id)
    {
        // Delete an employer
        $employer = EmployerProfile::findOrFail($id);
        if (!$employer) {
            return response()->json([
                'error' => true,
                'message' => 'Employer not found'
            ], 404);
        }

        $employer->delete();

        return response()->json([
            'success' => true,
            'message' => 'Employer deleted successfully'
        ], 200);
    }

    public function getAllEmployers(){
    try {
        $query = User::with(["employerProfile" => function($q){
            $q->select("id", "address", "size", "user_id", 'industry', 'logo');
        }]);

        $query->whereHas("roles", function($q){
            $q->where("name", "employer");
        });

        $employers = $query->paginate(15);

        return UserResource::collection($employers);
    } catch (\Exception $e) {
        Log::error('Error fetching employers', ['error' => $e->getMessage()]);
        return response()->json(['error' => 'Failed to retrieve employers'], 500);
    }
}

public function getEmployersByIndustry(){
     $industries = [
  "technology",
  "healthcare",
  "finance",
  "education",
  "manufacturing",
  "retail",
  "hospitality",
  "construction",
  "agriculture",
  "transportation",
  "media",
  "telecom",
  "energy",
  "real_estate",
  "consulting",
  "legal",
  "nonprofit",
  "government",
  "pharma",
  "automotive",
  "aerospace",
  "ecommerce",
  "food",
  "insurance",
  "marketing"
];
    $countIndustry = [];
    forEach($industries as $industry){
        $countIndustry[$industry] = EmployerProfile::where('industry', $industry)->count();
    }
    return response()->json([
        'success' => true,
        'message' => 'Employers by industry',
        'data' => $countIndustry
    ], 200);
}



}
