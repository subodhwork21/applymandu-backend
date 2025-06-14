<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class AllUserController extends Controller
{
      public function index(Request $request)
    {
          $query = User::with("roles");


        // foreach ($request->except('page', 'page_size') as $key => $value) {
        //     if (in_array($key, ['address', 'description', 'industry']) && !empty($value)) {
        //         $query->where($key, 'like', '%' . $value . '%');
        //     }
        // }

        $pageSize = intval($request->input('page_size', 10));
        $pageSize = $pageSize > 0 && $pageSize <= 100 ? $pageSize : 10;

        $result = $query->paginate($pageSize);


        // Return the collection directly
        return UserResource::collection($result->appends($request->query()));
    }
}
