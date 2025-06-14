<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Models\AmJob;
use App\Models\Application;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function topStats()
    {
        //total jobseekers, total jobs, total applications, total companies from last month

        $totalJobseekers = User::whereHas('roles', function ($q) {
            $q->where('name', 'jobseeker');
        })->whereMonth('created_at', now()->subMonth())->count();
        $totalJobs = AmJob::whereMonth('created_at', now()->subMonth())->count();
        $totalApplications = Application::whereMonth('created_at', now()->subMonth())->count();
        $totalCompanies = User::whereHas('roles', function ($q) {
            $q->where('name', 'employer');
        })->whereMonth('created_at', now()->subMonth())->count();

        //total data before month
        $totalJobseekersBeforeMonth = User::whereHas('roles', function ($q) {
            $q->where('name', 'jobseeker');
        })->whereMonth('created_at', now()->subMonth()->subMonth())->count();
        $totalJobsBeforeMonth = AmJob::whereMonth('created_at', now()->subMonth()->subMonth())->count();
        $totalApplicationsBeforeMonth = Application::whereMonth('created_at', now()->subMonth()->subMonth())->count();
        $totalCompaniesBeforeMonth = User::whereHas('roles', function ($q) {
            $q->where('name', 'employer');
        })->whereMonth('created_at', now()->subMonth()->subMonth())->count();

        //increase of all from last month in percentage
        $totalJobSeekerIncreaseBeforeMonth = $totalJobseekersBeforeMonth > 0
            ? (($totalJobseekers - $totalJobseekersBeforeMonth) / $totalJobseekersBeforeMonth) * 100
            : ($totalJobseekers > 0 ? 100 : 0);

        $totalJobsIncreaseBeforeMonth = $totalJobsBeforeMonth > 0
            ? (($totalJobs - $totalJobsBeforeMonth) / $totalJobsBeforeMonth) * 100
            : ($totalJobs > 0 ? 100 : 0);

        $totalApplicationsIncreaseBeforeMonth = $totalApplicationsBeforeMonth > 0
            ? (($totalApplications - $totalApplicationsBeforeMonth) / $totalApplicationsBeforeMonth) * 100
            : ($totalApplications > 0 ? 100 : 0);

        $totalCompaniesIncreaseBeforeMonth = $totalCompaniesBeforeMonth > 0
            ? (($totalCompanies - $totalCompaniesBeforeMonth) / $totalCompaniesBeforeMonth) * 100
            : ($totalCompanies > 0 ? 100 : 0);

        return response()->json([
            'totalJobseekers' => $totalJobseekers,
            'totalJobs' => $totalJobs,
            'totalApplications' => $totalApplications,
            'totalCompanies' => $totalCompanies,
            'totalJobSeekerIncrease' => round($totalJobSeekerIncreaseBeforeMonth, 2),
            'totalJobsIncrease' => round($totalJobsIncreaseBeforeMonth, 2),
            'totalApplicationsIncrease' => round($totalApplicationsIncreaseBeforeMonth, 2),
            'totalCompaniesIncrease' => round($totalCompaniesIncreaseBeforeMonth, 2),
        ]);
    }


    public function recentList()
    {
        $jobseekersList = User::whereHas('roles', function ($q) {
            $q->where('name', 'jobseeker');
        })->orderBy('created_at', 'desc')->take(7)->get();

        $employersList = User::whereHas('roles', function ($q) {
            $q->where('name', 'employer');
        })->orderBy('created_at', 'desc')->take(7)->get();

        $jobsList = AmJob::orderBy('created_at', 'desc')->take(7)->get();

        $applicationsList = Application::orderBy('created_at', 'desc')->take(7)->get();

        return response()->json([
            'jobseekersList' => $jobseekersList,
            'employersList' => $employersList,
            'jobsList' => $jobsList,
            'applicationsList' => $applicationsList,
        ]);
    }

    public function jobseekersGrowth()
    {
        //get jobseekers count every month of this year
        $currentYear = date('Y');
        $monthlyData = [];

        // Create an array for all months with zero counts initially
        for ($month = 1; $month <= 12; $month++) {
            $monthlyData[$month] = 0;
        }

        // Query to get jobseekers registered in each month of current year
        $jobSeekers = User::whereHas("roles", function ($q) {
            $q->where("name", "jobseeker");
        })
            ->whereYear('created_at', $currentYear)
            ->selectRaw('MONTH(created_at) as month, COUNT(*) as count')
            ->groupBy('month')
            ->get();

        // Fill in the actual counts
        foreach ($jobSeekers as $data) {
            $monthlyData[$data->month] = $data->count;
        }

        // Month abbreviations
        $monthAbbreviations = [
            1 => 'Jan',
            2 => 'Feb',
            3 => 'Mar',
            4 => 'Apr',
            5 => 'May',
            6 => 'Jun',
            7 => 'Jul',
            8 => 'Aug',
            9 => 'Sep',
            10 => 'Oct',
            11 => 'Nov',
            12 => 'Dec'
        ];

        // Format the data in the requested structure
        $users_by_month = [];
        foreach ($monthlyData as $month => $count) {
            $users_by_month[] = [
                'date' => $monthAbbreviations[$month] . ' ' . $currentYear,
                'count' => $count
            ];
        }

        return response()->json([
            'success' => true,
            'message' => 'Jobseekers growth data fetched successfully',
            'data' => [
                'users_by_month' => $users_by_month
            ]
        ]);
    }

    public function jobsByMonth()
    {
        $currentYear = date('Y');
        $monthlyData = [];

        // Create an array for all months with zero counts initially
        for ($month = 1; $month <= 12; $month++) {
            $monthlyData[$month] = 0;
        }

        // Query to get jobs created in each month of current year
        $jobs = AmJob::whereYear('created_at', $currentYear)
            ->selectRaw('MONTH(created_at) as month, COUNT(*) as count')
            ->groupBy('month')
            ->get();

        // Fill in the actual counts
        foreach ($jobs as $data) {
            $monthlyData[$data->month] = $data->count;
        }

        // Month abbreviations
        $monthAbbreviations = [
            1 => 'Jan',
            2 => 'Feb',
            3 => 'Mar',
            4 => 'Apr',
            5 => 'May',
            6 => 'Jun',
            7 => 'Jul',
            8 => 'Aug',
            9 => 'Sep',
            10 => 'Oct',
            11 => 'Nov',
            12 => 'Dec'
        ];

        // Format the data in the requested structure
        $jobs_by_month = [];
        foreach ($monthlyData as $month => $count) {
            $jobs_by_month[] = [
                'date' => $monthAbbreviations[$month] . ' ' . $currentYear,
                'count' => $count
            ];
        }

        return response()->json([
            'success' => true,
            'message' => 'Jobs by month data fetched successfully',
            'data' => [
                'jobs_by_month' => $jobs_by_month
            ]
        ]);
    }


    public function applicatinTrends(Request $request)
    {
        $currentYear = date('Y');
        $monthlyData = [];

        // Create an array for all months with zero counts initially
        for ($month = 1; $month <= 12; $month++) {
            $monthlyData[$month] = 0;
        }

        // Query to get applications created in each month of current year
        $applications = Application::whereYear('created_at', $currentYear)
            ->selectRaw('MONTH(created_at) as month, COUNT(*) as count')
            ->groupBy('month')
            ->get();

        // Fill in the actual counts
        foreach ($applications as $data) {
            $monthlyData[$data->month] = $data->count;
        }

        // Month abbreviations
        $monthAbbreviations = [
            1 => 'Jan',
            2 => 'Feb',
            3 => 'Mar',
            4 => 'Apr',
            5 => 'May',
            6 => 'Jun',
            7 => 'Jul',
            8 => 'Aug',
            9 => 'Sep',
            10 => 'Oct',
            11 => 'Nov',
            12 => 'Dec'
        ];

        // Format the data in the requested structure
        $applications_by_month = [];
        foreach ($monthlyData as $month => $count) {
            $applications_by_month[] = [
                'date' => $monthAbbreviations[$month] . ' ' . $currentYear,
                'count' => $count
            ];
        }

        return response()->json([
            'success' => true,
            'message' => 'Application trends data fetched successfully',
            'data' => [
                'applications_by_month' => $applications_by_month
            ]
        ]);
    }


    public function jobByCategory(Request $request)
    {
        //job by department it,engineering,design,marketing,sales,finance,hr,operations,product,customer_support

        // Define the categories we want to track
        $categories = [
            'it',
            'engineering',
            'design',
            'marketing',
            'sales',
            'finance',
            'hr',
            'operations',
            'product',
            'customer_support'
        ];

        $jobsByCategory = [];

        // For each category, count the number of jobs
        foreach ($categories as $category) {
            $count = AmJob::where('department', 'like', '%' . $category . '%')
                ->orWhere('department', 'like', '%' . ucfirst($category) . '%') // For capitalized versions
                ->count();

            $jobsByCategory[] = [
                'department' => ucfirst($category), // Capitalize first letter for display
                'count' => $count
            ];
        }

        // Sort by count in descending order (optional)
        usort($jobsByCategory, function ($a, $b) {
            return $b['count'] - $a['count'];
        });

        return response()->json([
            'success' => true,
            'message' => 'Jobs by department fetched successfully',
            'data' => [
                'jobs_by_category' => $jobsByCategory
            ]
        ]);
    }


    public function applicationByStatus(Request $request)
    {
        // Define the statuses we want to track
        $statuses = [
            'applied',
            'interviewed',
            'offered',
            'accepted',
            'rejected',
            'interview_scheduled'
        ];

        $applicationsByStatus = [];

        // Get all applications with their latest status
        $applications = Application::all();
        $statusCounts = array_fill_keys($statuses, 0);

        foreach ($applications as $application) {
            // Get the latest status for this application
            $latestStatus = DB::table('application_status_histories')
                ->where('application_id', $application->id)
                ->orderBy('created_at', 'desc')
                ->first();

            if ($latestStatus && in_array($latestStatus->status, $statuses)) {
                $statusCounts[$latestStatus->status]++;
            }
        }

        // Format the results
        foreach ($statuses as $status) {
            $displayStatus = ucwords(str_replace('_', ' ', $status));

            $applicationsByStatus[] = [
                'status' => $displayStatus,
                'count' => $statusCounts[$status]
            ];
        }

        return response()->json([
            'success' => true,
            'message' => 'Applications by status fetched successfully',
            'data' => [
                'applications_by_status' => $applicationsByStatus
            ]
        ]);
    }
}
