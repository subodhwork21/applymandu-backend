<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class MigrationController extends Controller
{
   public function runMigrations(Request $request)
    {
        // Check for a secure token
        if ($request->header('Migration-Secret') !== env('MIGRATION_SECRET_TOKEN')) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Run migrations
        $output = [];
        Artisan::call('migrate', ['--force' => true], $output);

        return response()->json([
            'success' => true,
            'message' => 'Migrations completed successfully',
            'output' => $output
        ]);
    }
}
