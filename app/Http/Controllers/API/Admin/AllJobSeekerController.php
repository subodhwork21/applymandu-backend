<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AllJobSeekerController extends Controller
{
    public function index(Request $request)
    {
          $query = User::with('jobSeekerProfile')
            ->whereHas('roles', function ($query) {
                $query->where('name', 'jobseeker');
            });


        // foreach ($request->except('page', 'page_size') as $key => $value) {
        //     if (in_array($key, ['address', 'description', 'industry']) && !empty($value)) {
        //         $query->where($key, 'like', '%' . $value . '%');
        //     }
        // }

        $pageSize = intval($request->input('page_size', 10));
        $pageSize = $pageSize > 0 && $pageSize <= 100 ? $pageSize : 10;

        $result = $query->paginate($pageSize);

        $cacheKey = 'jobseekers:' . md5(json_encode($request->query()));

        if (Cache::has($cacheKey)) {
            $result = Cache::get($cacheKey);
        } else {
            $result = $query->paginate($pageSize);
            Cache::put($cacheKey, $result, now()->addMinutes(5));
        }


        // Return the collection directly
        return UserResource::collection($result->appends($request->query()));
    }
}
