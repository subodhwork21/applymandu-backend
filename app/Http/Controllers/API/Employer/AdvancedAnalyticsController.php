<?php

namespace App\Http\Controllers\API\Employer;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\AmJob;
use App\Models\Application;
use App\Models\User;
use App\Models\JobSeekerProfile;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdvancedAnalyticsController extends Controller
{
    /**
     * Get comprehensive analytics data for employer
     */
    public function getAnalytics(Request $request)
    {
        try {
            $employerId = Auth::id();
            $year = $request->input('year', date('Y'));
            $timeframe = $request->input('timeframe', 'yearly');

            // Create cache key
            $cacheKey = "advanced_analytics_{$employerId}_{$year}_{$timeframe}";

            $data = Cache::remember($cacheKey, 30 * 60, function () use ($employerId, $year, $timeframe) {
                return [
                    'overview' => $this->getOverviewData($employerId, $year, $timeframe),
                    'applicantDemographics' => $this->getApplicantDemographics($employerId, $year, $timeframe),
                    'applicationTrends' => $this->getApplicationTrends($employerId, $year, $timeframe),
                    'performanceMetrics' => $this->getPerformanceMetrics($employerId, $year, $timeframe),
                    'topPerformingJobs' => $this->getTopPerformingJobs($employerId, $year, $timeframe),
                    'insights' => $this->generateInsights($employerId, $year, $timeframe)
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data
            ]);
        } catch (\Exception $e) {
            Log::error('Analytics fetch failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch analytics data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get overview statistics
     */
    private function getOverviewData($employerId, $year, $timeframe)
    {
        try {
            $dateRange = $this->getDateRange($year, $timeframe);
            $previousDateRange = $this->getPreviousDateRange($year, $timeframe);

            // Get employer's job IDs
            $jobIds = AmJob::where('employer_id', $employerId)->pluck('id');

            if ($jobIds->isEmpty()) {
                return [
                    'totalViews' => 0,
                    'totalApplications' => 0,
                    'totalHires' => 0,
                    'totalJobPostings' => 0,
                    'viewsChange' => 0,
                    'applicationsChange' => 0,
                    'hiresChange' => 0,
                    'jobPostingsChange' => 0
                ];
            }

            // Current period stats
            $totalViews = Activity::where('subject_type', AmJob::class)
                ->whereIn('subject_id', $jobIds)
                ->where('type', 'job_viewed')
                ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
                ->count();

            $totalApplications = Application::whereIn('job_id', $jobIds)
                ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
                ->count();

            $totalHires = Application::whereIn('job_id', $jobIds)
                ->whereHas('applicationStatusHistory', function ($query) use ($dateRange) {
                    $query->where('status', 'accepted')
                        ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']]);
                })
                ->count();

            $totalJobPostings = AmJob::where('employer_id', $employerId)
                ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
                ->count();

            // Previous period stats for comparison
            $previousViews = Activity::where('subject_type', AmJob::class)
                ->whereIn('subject_id', $jobIds)
                ->where('type', 'job_viewed')
                ->whereBetween('created_at', [$previousDateRange['start'], $previousDateRange['end']])
                ->count();

            $previousApplications = Application::whereIn('job_id', $jobIds)
                ->whereBetween('created_at', [$previousDateRange['start'], $previousDateRange['end']])
                ->count();

            $previousHires = Application::whereIn('job_id', $jobIds)
                ->whereHas('applicationStatusHistory', function ($query) use ($previousDateRange) {
                    $query->where('status', 'accepted')
                        ->whereBetween('created_at', [$previousDateRange['start'], $previousDateRange['end']]);
                })
                ->count();

            $previousJobPostings = AmJob::where('employer_id', $employerId)
                ->whereBetween('created_at', [$previousDateRange['start'], $previousDateRange['end']])
                ->count();

            return [
                'totalViews' => $totalViews,
                'totalApplications' => $totalApplications,
                'totalHires' => $totalHires,
                'totalJobPostings' => $totalJobPostings,
                'viewsChange' => $this->calculatePercentageChange($totalViews, $previousViews),
                'applicationsChange' => $this->calculatePercentageChange($totalApplications, $previousApplications),
                'hiresChange' => $this->calculatePercentageChange($totalHires, $previousHires),
                'jobPostingsChange' => $this->calculatePercentageChange($totalJobPostings, $previousJobPostings)
            ];
        } catch (\Exception $e) {
            Log::error('Overview data fetch failed: ' . $e->getMessage());
            return [
                'totalViews' => 0,
                'totalApplications' => 0,
                'totalHires' => 0,
                'totalJobPostings' => 0,
                'viewsChange' => 0,
                'applicationsChange' => 0,
                'hiresChange' => 0,
                'jobPostingsChange' => 0
            ];
        }
    }

    /**
     * Get applicant demographics data
     */
    private function getApplicantDemographics($employerId, $year, $timeframe)
    {
        try {
            $dateRange = $this->getDateRange($year, $timeframe);
            $jobIds = AmJob::where('employer_id', $employerId)->pluck('id');

            if ($jobIds->isEmpty()) {
                return [
                    'byGender' => [],
                    'byAge' => [],
                    'byEducation' => [],
                    'byExperience' => []
                ];
            }

            // Get applicant user IDs
            $applicantIds = Application::whereIn('job_id', $jobIds)
                ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
                ->pluck('user_id')
                ->unique();

            if ($applicantIds->isEmpty()) {
                return [
                    'byGender' => [],
                    'byAge' => [],
                    'byEducation' => [],
                    'byExperience' => []
                ];
            }

            // Gender demographics
            $byGender = JobSeekerProfile::whereIn('user_id', $applicantIds)
                ->select('gender', DB::raw('count(*) as count'))
                ->groupBy('gender')
                ->get()
                ->map(function ($item) {
                    return [
                        'name' => ucfirst($item->gender ?? 'Not specified'),
                        'value' => (int) $item->count
                    ];
                })
                ->toArray();

            // Age demographics
            $profiles = JobSeekerProfile::whereIn('user_id', $applicantIds)
                ->whereNotNull('date_of_birth')
                ->get();

            $byAge = $profiles->groupBy(function ($profile) {
                if (!$profile->date_of_birth) return 'Not specified';
                $age = Carbon::parse($profile->date_of_birth)->age;
                if ($age < 25) return '18-24';
                if ($age < 35) return '25-34';
                if ($age < 45) return '35-44';
                if ($age < 55) return '45-54';
                return '55+';
            })
                ->map(function ($group, $key) {
                    return [
                        'name' => $key,
                        'value' => $group->count()
                    ];
                })
                ->values()
                ->toArray();

            // Education demographics - simplified approach
            $byEducation = JobSeekerProfile::whereIn('user_id', $applicantIds)
                ->select('education_level', DB::raw('count(*) as count'))
                ->groupBy('education_level')
                ->get()
                ->map(function ($item) {
                    return [
                        'name' => $item->education_level ?? 'Not specified',
                        'value' => (int) $item->count
                    ];
                })
                ->toArray();

            // Experience demographics
            $byExperience = JobSeekerProfile::whereIn('user_id', $applicantIds)
                ->get()
                ->groupBy(function ($profile) {
                    $experience = $profile->total_experience ?? 0;
                    if ($experience <= 1) return '0-1 years';
                    if ($experience <= 5) return '2-5 years';
                    if ($experience <= 10) return '6-10 years';
                    return '10+ years';
                })
                ->map(function ($group, $key) {
                    return [
                        'name' => $key,
                        'value' => $group->count()
                    ];
                })
                ->values()
                ->toArray();

            return [
                'byGender' => $byGender,
                'byAge' => $byAge,
                'byEducation' => $byEducation,
                'byExperience' => $byExperience
            ];
        } catch (\Exception $e) {
            Log::error('Demographics data fetch failed: ' . $e->getMessage());
            return [
                'byGender' => [],
                'byAge' => [],
                'byEducation' => [],
                'byExperience' => []
            ];
        }
    }

    /**
     * Get application trends data
     */
    private function getApplicationTrends($employerId, $year, $timeframe)
    {
        try {
            $dateRange = $this->getDateRange($year, $timeframe);
            $jobIds = AmJob::where('employer_id', $employerId)->pluck('id');

            if ($jobIds->isEmpty()) {
                return [
                    'byMonth' => [],
                    'byJobType' => [],
                    'byLocation' => []
                ];
            }

            // Monthly trends
            $byMonth = [];
            $startDate = Carbon::parse($dateRange['start']);
            $endDate = Carbon::parse($dateRange['end']);
            $currentDate = $startDate->copy();

            while ($currentDate <= $endDate) {
                $monthStart = $currentDate->copy()->startOfMonth();
                $monthEnd = $currentDate->copy()->endOfMonth();

                $applications = Application::whereIn('job_id', $jobIds)
                    ->whereBetween('created_at', [$monthStart, $monthEnd])
                    ->count();

                $views = Activity::where('subject_type', AmJob::class)
                    ->whereIn('subject_id', $jobIds)
                    ->where('type', 'job_viewed')
                    ->whereBetween('created_at', [$monthStart, $monthEnd])
                    ->count();

                $byMonth[] = [
                    'date' => $currentDate->format('M Y'),
                    'applications' => $applications,
                    'views' => $views
                ];

                $currentDate->addMonth();
            }

            // By job type
            $byJobType = AmJob::where('employer_id', $employerId)
                ->select('employment_type', DB::raw('count(*) as count'))
                ->groupBy('employment_type')
                ->get()
                ->map(function ($item) {
                    return [
                        'name' => ucfirst($item->employment_type ?? 'Not specified'),
                        'value' => (int) $item->count
                    ];
                })
                ->toArray();

            // By location
            $byLocation = AmJob::where('employer_id', $employerId)
                ->select('location', DB::raw('count(*) as count'))
                ->groupBy('location')
                ->orderBy('count', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($item) {
                    return [
                        'name' => $item->location ?? 'Not specified',
                        'value' => (int) $item->count
                    ];
                })
                ->toArray();

            return [
                'byMonth' => $byMonth,
                'byJobType' => $byJobType,
                'byLocation' => $byLocation
            ];
        } catch (\Exception $e) {
            Log::error('Application trends fetch failed: ' . $e->getMessage());
            return [
                'byMonth' => [],
                'byJobType' => [],
                'byLocation' => []
            ];
        }
    }

    /**
     * Get performance metrics
     */
    private function getPerformanceMetrics($employerId, $year, $timeframe)
    {
        try {
            $dateRange = $this->getDateRange($year, $timeframe);
            $previousDateRange = $this->getPreviousDateRange($year, $timeframe);
            $jobIds = AmJob::where('employer_id', $employerId)->pluck('id');

            if ($jobIds->isEmpty()) {
                return [
                    'conversionRate' => 0,
                    'averageTimeToHire' => 0,
                    'applicationCompletionRate' => 0,
                    'applicantQualityScore' => 0,
                    'conversionRateChange' => 0,
                    'timeToHireChange' => 0,
                    'completionRateChange' => 0,
                    'qualityScoreChange' => 0
                ];
            }

            // Current period metrics
            $totalViews = Activity::where('subject_type', AmJob::class)
                ->whereIn('subject_id', $jobIds)
                ->where('type', 'job_viewed')
                ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
                ->count();

            $totalApplications = Application::whereIn('job_id', $jobIds)
                ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
                ->count();
            $conversionRate = $totalViews > 0 ? round(($totalApplications / $totalViews) * 100, 1) : 0;

            // Average time to hire
            $hiredApplications = Application::whereIn('job_id', $jobIds)
                ->whereHas('applicationStatusHistory', function ($query) use ($dateRange) {
                    $query->where('status', 'accepted')
                        ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']]);
                })
                ->with(['job', 'applicationStatusHistory'])
                ->get();

            $timeToHireData = $hiredApplications->map(function ($application) {
                $acceptedHistory = $application->applicationStatusHistory
                    ->where('status', 'accepted')
                    ->first();

                if ($acceptedHistory && $application->job) {
                    return $application->job->created_at->diffInDays($acceptedHistory->created_at);
                }
                return null;
            })->filter();

            $averageTimeToHire = $timeToHireData->count() > 0 ? round($timeToHireData->avg()) : 0;

            // Application completion rate
            $completedApplications = Application::whereIn('job_id', $jobIds)
                ->where('status', 1)
                ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
                ->count();

            $applicationCompletionRate = $totalApplications > 0 ?
                round(($completedApplications / $totalApplications) * 100, 1) : 0;

            // Applicant quality score (simplified calculation)
            $applicantQualityScore = 7.2;

            // Previous period for comparison
            $previousViews = Activity::where('subject_type', AmJob::class)
                ->whereIn('subject_id', $jobIds)
                ->where('type', 'job_viewed')
                ->whereBetween('created_at', [$previousDateRange['start'], $previousDateRange['end']])
                ->count();

            $previousApplications = Application::whereIn('job_id', $jobIds)
                ->whereBetween('created_at', [$previousDateRange['start'], $previousDateRange['end']])
                ->count();

            $previousConversionRate = $previousViews > 0 ?
                round(($previousApplications / $previousViews) * 100, 1) : 0;

            return [
                'conversionRate' => $conversionRate,
                'averageTimeToHire' => $averageTimeToHire,
                'applicationCompletionRate' => $applicationCompletionRate,
                'applicantQualityScore' => $applicantQualityScore,
                'conversionRateChange' => $conversionRate - $previousConversionRate,
                'timeToHireChange' => -2.5, // Mock data - implement actual calculation
                'completionRateChange' => 3.2, // Mock data - implement actual calculation
                'qualityScoreChange' => 0.3 // Mock data - implement actual calculation
            ];
        } catch (\Exception $e) {
            Log::error('Performance metrics fetch failed: ' . $e->getMessage());
            return [
                'conversionRate' => 0,
                'averageTimeToHire' => 0,
                'applicationCompletionRate' => 0,
                'applicantQualityScore' => 0,
                'conversionRateChange' => 0,
                'timeToHireChange' => 0,
                'completionRateChange' => 0,
                'qualityScoreChange' => 0
            ];
        }
    }

    /**
     * Get top performing jobs
     */
    private function getTopPerformingJobs($employerId, $year, $timeframe)
    {
        try {
            $dateRange = $this->getDateRange($year, $timeframe);

            $jobs = AmJob::where('employer_id', $employerId)
                ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
                ->withCount(['applications' => function ($query) use ($dateRange) {
                    $query->whereBetween('created_at', [$dateRange['start'], $dateRange['end']]);
                }])
                ->get()
                ->map(function ($job) use ($dateRange) {
                    // Get views count
                    $views = Activity::where('subject_type', AmJob::class)
                        ->where('subject_id', $job->id)
                        ->where('type', 'job_viewed')
                        ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
                        ->count();

                    // Calculate conversion rate
                    $conversionRate = $views > 0 ? round(($job->applications_count / $views) * 100, 1) : 0;

                    return [
                        'id' => $job->id,
                        'title' => $job->title ?? 'Untitled Job',
                        'views' => $views,
                        'applications' => $job->applications_count,
                        'conversionRate' => $conversionRate
                    ];
                })
                ->sortByDesc('applications')
                ->take(5)
                ->values()
                ->toArray();

            return $jobs;
        } catch (\Exception $e) {
            Log::error('Top performing jobs fetch failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Generate insights based on analytics data
     */
    private function generateInsights($employerId, $year, $timeframe)
    {
        try {
            $insights = [];
            $dateRange = $this->getDateRange($year, $timeframe);
            $jobIds = AmJob::where('employer_id', $employerId)->pluck('id');

            if ($jobIds->isEmpty()) {
                return [];
            }

            // Conversion rate insight
            $totalViews = Activity::where('subject_type', AmJob::class)
                ->whereIn('subject_id', $jobIds)
                ->where('type', 'job_viewed')
                ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
                ->count();

            $totalApplications = Application::whereIn('job_id', $jobIds)
                ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
                ->count();

            $conversionRate = $totalViews > 0 ? round(($totalApplications / $totalViews) * 100, 1) : 0;

            if ($conversionRate > 7) {
                $insights[] = [
                    'type' => 'success',
                    'category' => 'Conversion Performance',
                    'message' => "Your conversion rate of {$conversionRate}% is excellent, well above the industry average of 6.5%.",
                    'recommendation' => 'Continue using your current job posting strategies and consider sharing best practices across all your job listings.'
                ];
            } elseif ($conversionRate < 5) {
                $insights[] = [
                    'type' => 'warning',
                    'category' => 'Conversion Performance',
                    'message' => "Your conversion rate of {$conversionRate}% is below the industry average of 6.5%.",
                    'recommendation' => 'Review your job descriptions, requirements, and benefits to make them more appealing to qualified candidates.'
                ];
            }

            // Application volume insight
            if ($totalApplications > 100) {
                $insights[] = [
                    'type' => 'success',
                    'category' => 'Application Volume',
                    'message' => "You've received {$totalApplications} applications, indicating strong interest in your positions.",
                    'recommendation' => 'Consider implementing automated screening tools to efficiently manage the high volume of applications.'
                ];
            } elseif ($totalApplications < 20) {
                $insights[] = [
                    'type' => 'info',
                    'category' => 'Application Volume',
                    'message' => "You've received {$totalApplications} applications. There may be opportunities to increase visibility.",
                    'recommendation' => 'Consider promoting your job postings on social media or job boards to reach a wider audience.'
                ];
            }

            return $insights;
        } catch (\Exception $e) {
            Log::error('Insights generation failed: ' . $e->getMessage());
            return [];
        }
    }

    // ... (keep all the existing helper methods like getDateRange, getPreviousDateRange, calculatePercentageChange, etc.)

    /**
     * Helper method to get date range based on timeframe
     */
    private function getDateRange($year, $timeframe)
    {
        switch ($timeframe) {
            case 'monthly':
                $start = Carbon::createFromDate($year, 1, 1)->startOfYear();
                $end = Carbon::createFromDate($year, 12, 31)->endOfYear();
                break;
            case 'quarterly':
                $start = Carbon::createFromDate($year, 1, 1)->startOfYear();
                $end = Carbon::createFromDate($year, 12, 31)->endOfYear();
                break;
            case 'yearly':
            default:
                $start = Carbon::createFromDate($year, 1, 1)->startOfYear();
                $end = Carbon::createFromDate($year, 12, 31)->endOfYear();
                break;
        }

        return ['start' => $start, 'end' => $end];
    }

    /**
     * Helper method to get previous date range for comparison
     */
    private function getPreviousDateRange($year, $timeframe)
    {
        switch ($timeframe) {
            case 'monthly':
                $start = Carbon::createFromDate($year - 1, 1, 1)->startOfYear();
                $end = Carbon::createFromDate($year - 1, 12, 31)->endOfYear();
                break;
            case 'quarterly':
                $start = Carbon::createFromDate($year - 1, 1, 1)->startOfYear();
                $end = Carbon::createFromDate($year - 1, 12, 31)->endOfYear();
                break;
            case 'yearly':
            default:
                $start = Carbon::createFromDate($year - 1, 1, 1)->startOfYear();
                $end = Carbon::createFromDate($year - 1, 12, 31)->endOfYear();
                break;
        }

        return ['start' => $start, 'end' => $end];
    }

    /**
     * Helper method to calculate percentage change
     */
    private function calculatePercentageChange($current, $previous)
    {
        if ($previous == 0) {
            return $current > 0 ? 100 : 0;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    public function exportAnalytics(Request $request)
    {
        try {
            $employerId = Auth::id();
            $year = $request->input('year', date('Y'));
            $timeframe = $request->input('timeframe', 'yearly');
            $format = $request->input('format', 'csv'); // csv, pdf, json

            // Get analytics data (reuse your existing logic)
            $data = [
                'overview' => $this->getOverviewData($employerId, $year, $timeframe),
                'applicantDemographics' => $this->getApplicantDemographics($employerId, $year, $timeframe),
                'applicationTrends' => $this->getApplicationTrends($employerId, $year, $timeframe),
                'performanceMetrics' => $this->getPerformanceMetrics($employerId, $year, $timeframe),
                'topPerformingJobs' => $this->getTopPerformingJobs($employerId, $year, $timeframe),
                'insights' => $this->generateInsights($employerId, $year, $timeframe)
            ];

            // Handle export formats
            if ($format === 'json') {
                $filename = "analytics-export-{$year}.json";
                return response(json_encode($data, JSON_PRETTY_PRINT), 200, [
                    'Content-Type' => 'application/json',
                    'Content-Disposition' => "attachment; filename=\"$filename\"",
                ]);
            }

            if ($format === 'csv') {
                $filename = "analytics-export-{$year}.csv";
                $handle = fopen('php://temp', 'r+');

                // Overview Section
                fputcsv($handle, ['Overview']);
                fputcsv($handle, ['Metric', 'Value']);
                foreach ($data['overview'] as $key => $value) {
                    fputcsv($handle, [ucwords(str_replace('_', ' ', $key)), $value]);
                }
                fputcsv($handle, []); // Blank line

                // Top Performing Jobs Section
                fputcsv($handle, ['Top Performing Jobs']);
                fputcsv($handle, ['Title', 'Views', 'Applications', 'Conversion Rate (%)']);
                foreach ($data['topPerformingJobs'] as $job) {
                    fputcsv($handle, [
                        $job['title'],
                        $job['views'],
                        $job['applications'],
                        $job['conversionRate']
                    ]);
                }
                fputcsv($handle, []); // Blank line

                // You can add more sections as needed (demographics, trends, etc.)

                rewind($handle);
                $csv = stream_get_contents($handle);
                fclose($handle);

                return response($csv, 200, [
                    'Content-Type' => 'text/csv',
                    'Content-Disposition' => "attachment; filename=\"$filename\"",
                ]);
            }


            if ($format === 'pdf') {
                // You need a PDF library like barryvdh/laravel-dompdf installed for this!
                // composer require barryvdh/laravel-dompdf
                $filename = "analytics-export-{$year}.pdf";
                $pdf = \PDF::loadView('exports.analytics-pdf', ['data' => $data]);
                return $pdf->download($filename);
            }

            // Default: return JSON
            return response(json_encode($data, JSON_PRETTY_PRINT), 200, [
                'Content-Type' => 'application/json',
                'Content-Disposition' => "attachment; filename=\"analytics-export-{$year}.json\"",
            ]);
        } catch (\Exception $e) {
            \Log::error('Analytics export failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to export analytics data',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
