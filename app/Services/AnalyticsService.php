<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\AmJob;
use App\Models\Application;
use App\Models\JobSeekerProfile;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class AnalyticsService
{
    /**
     * Calculate advanced conversion metrics
     */
    public function calculateConversionMetrics($employerId, $dateRange)
    {
        $jobIds = AmJob::where('employer_id', $employerId)->pluck('id');
        
        $metrics = Cache::remember(
            "conversion_metrics_{$employerId}_{$dateRange['start']}_{$dateRange['end']}", 
            30 * 60, 
            function () use ($jobIds, $dateRange) {
                $totalViews = Activity::where('subject_type', AmJob::class)
                    ->whereIn('subject_id', $jobIds)
                    ->where('type', 'job_viewed')
                    ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
                    ->count();
                
                $uniqueViews = Activity::where('subject_type', AmJob::class)
                    ->whereIn('subject_id', $jobIds)
                    ->where('type', 'job_viewed')
                    ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
                    ->distinct('user_id')
                    ->count('user_id');
                
                $totalApplications = Application::whereIn('job_id', $jobIds)
                    ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
                    ->count();
                
                $uniqueApplicants = Application::whereIn('job_id', $jobIds)
                    ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
                    ->distinct('user_id')
                    ->count('user_id');
                
                return [
                    'view_to_application_rate' => $totalViews > 0 ? round(($totalApplications / $totalViews) * 100, 2) : 0,
                    'unique_view_to_application_rate' => $uniqueViews > 0 ? round(($uniqueApplicants / $uniqueViews) * 100, 2) : 0,
                    'application_completion_rate' => $this->calculateApplicationCompletionRate($jobIds, $dateRange),
                    'return_visitor_rate' => $totalViews > 0 ? round((($totalViews - $uniqueViews) / $totalViews) * 100, 2) : 0
                ];
            }
        );
        
        return $metrics;
    }
    
    /**
     * Calculate application completion rate
     */
    private function calculateApplicationCompletionRate($jobIds, $dateRange)
    {
        // Applications that were started (assuming there's a started_at field)
        $startedApplications = Application::whereIn('job_id', $jobIds)
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->count();
        
        // Applications that were completed (status = 1 or completed)
        $completedApplications = Application::whereIn('job_id', $jobIds)
            ->where('status', 1)
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->count();
        
        return $startedApplications > 0 ? round(($completedApplications / $startedApplications) * 100, 2) : 0;
    }
    
    /**
     * Calculate engagement metrics
     */
    public function calculateEngagementMetrics($employerId, $dateRange)
    {
        $jobIds = AmJob::where('employer_id', $employerId)->pluck('id');
        
        return Cache::remember(
            "engagement_metrics_{$employerId}_{$dateRange['start']}_{$dateRange['end']}", 
            30 * 60, 
            function () use ($jobIds, $dateRange) {
                // Average time spent on job pages (if you track this)
                $avgTimeOnPage = Activity::where('subject_type', AmJob::class)
                    ->whereIn('subject_id', $jobIds)
                    ->where('type', 'job_viewed')
                    ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
                    ->avg('duration') ?? 0; // Assuming you have a duration field
                
                // Bounce rate (single page views)
                $totalSessions = Activity::where('subject_type', AmJob::class)
                    ->whereIn('subject_id', $jobIds)
                    ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
                    ->distinct('session_id')
                    ->count('session_id');
                
                $bounceSessions = Activity::where('subject_type', AmJob::class)
                    ->whereIn('subject_id', $jobIds)
                    ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
                    ->select('session_id')
                    ->groupBy('session_id')
                    ->havingRaw('COUNT(*) = 1')
                    ->get()
                    ->count();
                
                $bounceRate = $totalSessions > 0 ? round(($bounceSessions / $totalSessions) * 100, 2) : 0;
                
                return [
                    'average_time_on_page' => round($avgTimeOnPage, 2),
                    'bounce_rate' => $bounceRate,
                    'pages_per_session' => $totalSessions > 0 ? round(Activity::where('subject_type', AmJob::class)
                        ->whereIn('subject_id', $jobIds)
                        ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
                        ->count() / $totalSessions, 2) : 0
                ];
            }
        );
    }
    
    /**
     * Generate insights and recommendations
     */
    public function generateInsights($analyticsData)
    {
        $insights = [];
        
        // Conversion rate insights
        if ($analyticsData['performanceMetrics']['conversionRate'] < 5) {
            $insights[] = [
                'type' => 'warning',
                'category' => 'conversion',
                'message' => 'Your conversion rate is below industry average. Consider improving job descriptions and requirements clarity.',
                'recommendation' => 'Review top-performing job postings and apply similar strategies to underperforming ones.'
            ];
        } elseif ($analyticsData['performanceMetrics']['conversionRate'] > 8) {
            $insights[] = [
                'type' => 'success',
                'category' => 'conversion',
                'message' => 'Excellent conversion rate! Your job postings are effectively attracting qualified candidates.',
                'recommendation' => 'Consider sharing your successful job posting strategies across all positions.'
            ];
        }
        
        // Time to hire insights
        if ($analyticsData['performanceMetrics']['averageTimeToHire'] > 45) {
            $insights[] = [
                'type' => 'warning',
                'category' => 'efficiency',
                'message' => 'Your hiring process is taking longer than industry average.',
                'recommendation' => 'Consider streamlining your interview process and implementing automated screening tools.'
            ];
        }
        
        // Demographics insights
        $genderData = $analyticsData['applicantDemographics']['byGender'];
        if (count($genderData) > 0) {
            $maleCount = collect($genderData)->where('name', 'Male')->first()['value'] ?? 0;
            $femaleCount = collect($genderData)->where('name', 'Female')->first()['value'] ?? 0;
            $total = $maleCount + $femaleCount;
            
            if ($total > 0) {
                $genderRatio = $total > 0 ? ($maleCount / $total) * 100 : 50;
                
                if ($genderRatio > 70 || $genderRatio < 30) {
                    $insights[] = [
                        'type' => 'info',
                        'category' => 'diversity',
                        'message' => 'Your applicant pool shows gender imbalance.',
                        'recommendation' => 'Consider reviewing job descriptions for inclusive language and posting on diverse job boards.'
                    ];
                }
            }
        }
        
        return $insights;
    }
    
    /**
     * Calculate seasonal trends
     */
    public function calculateSeasonalTrends($employerId, $years = 2)
    {
        $jobIds = AmJob::where('employer_id', $employerId)->pluck('id');
        
        return Cache::remember(
            "seasonal_trends_{$employerId}_{$years}", 
            60 * 60, // Cache for 1 hour
            function () use ($jobIds, $years) {
                $startDate = Carbon::now()->subYears($years);
                $endDate = Carbon::now();
                
                $monthlyData = Application::whereIn('job_id', $jobIds)
                    ->whereBetween('created_at', [$startDate, $endDate])
                    ->select(
                        DB::raw('MONTH(created_at) as month'),
                        DB::raw('YEAR(created_at) as year'),
                        DB::raw('COUNT(*) as applications')
                    )
                    ->groupBy('year', 'month')
                    ->orderBy('year')
                    ->orderBy('month')
                    ->get();
                
                // Calculate average applications per month across years
                $monthlyAverages = [];
                for ($month = 1; $month <= 12; $month++) {
                    $monthData = $monthlyData->where('month', $month);
                    $average = $monthData->avg('applications') ?? 0;
                    $monthlyAverages[] = [
                        'month' => Carbon::create()->month($month)->format('M'),
                        'average_applications' => round($average, 1),
                        'trend' => $this->calculateMonthTrend($monthData)
                    ];
                }
                
                return [
                    'monthly_averages' => $monthlyAverages,
                    'peak_months' => $this->identifyPeakMonths($monthlyAverages),
                    'seasonal_recommendations' => $this->generateSeasonalRecommendations($monthlyAverages)
                ];
            }
        );
    }
    
    /**
     * Calculate month trend
     */
    private function calculateMonthTrend($monthData)
    {
        if ($monthData->count() < 2) return 'stable';
        
        $sorted = $monthData->sortBy('year');
        $first = $sorted->first()['applications'];
        $last = $sorted->last()['applications'];
        
        if ($last > $first * 1.1) return 'increasing';
        if ($last < $first * 0.9) return 'decreasing';
        return 'stable';
    }
    
    /**
     * Identify peak months
     */
    private function identifyPeakMonths($monthlyAverages)
    {
        $sorted = collect($monthlyAverages)->sortByDesc('average_applications');
        return $sorted->take(3)->values()->toArray();
    }
    
    /**
     * Generate seasonal recommendations
     */
    private function generateSeasonalRecommendations($monthlyAverages)
    {
        $recommendations = [];
        $avgApplications = collect($monthlyAverages)->avg('average_applications');
        
        foreach ($monthlyAverages as $month) {
            if ($month['average_applications'] > $avgApplications * 1.2) {
                $recommendations[] = "Consider posting more jobs in {$month['month']} as it's a high-activity month";
            } elseif ($month['average_applications'] < $avgApplications * 0.8) {
                $recommendations[] = "Focus on employer branding in {$month['month']} to boost applications";
            }
        }
        
        return $recommendations;
    }
    
    /**
     * Calculate source attribution
     */
    public function calculateSourceAttribution($employerId, $dateRange)
    {
        $jobIds = AmJob::where('employer_id', $employerId)->pluck('id');
        
        return Cache::remember(
            "source_attribution_{$employerId}_{$dateRange['start']}_{$dateRange['end']}", 
            30 * 60,
            function () use ($jobIds, $dateRange) {
                // Application sources
                $applicationSources = Application::whereIn('job_id', $jobIds)
                    ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
                    ->select('source', DB::raw('COUNT(*) as count'))
                    ->groupBy('source')
                    ->orderBy('count', 'desc')
                    ->get();
                
                // View sources (if tracked)
                $viewSources = Activity::where('subject_type', AmJob::class)
                    ->whereIn('subject_id', $jobIds)
                    ->where('type', 'job_viewed')
                    ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
                    ->select('source', DB::raw('COUNT(*) as count'))
                    ->groupBy('source')
                    ->orderBy('count', 'desc')
                    ->get();
                
                // Calculate conversion rates by source
                $sourceConversions = [];
                foreach ($viewSources as $viewSource) {
                    $applicationCount = $applicationSources->where('source', $viewSource->source)->first()->count ?? 0;
                    $conversionRate = $viewSource->count > 0 ? round(($applicationCount / $viewSource->count) * 100, 2) : 0;
                    
                    $sourceConversions[] = [
                        'source' => $viewSource->source ?? 'Direct',
                        'views' => $viewSource->count,
                        'applications' => $applicationCount,
                        'conversion_rate' => $conversionRate
                    ];
                }
                
                return [
                    'application_sources' => $applicationSources,
                    'view_sources' => $viewSources,
                    'source_conversions' => $sourceConversions,
                    'top_converting_source' => collect($sourceConversions)->sortByDesc('conversion_rate')->first()
                ];
            }
        );
    }
    
    /**
     * Calculate cost per hire by source
     */
    public function calculateCostPerHire($employerId, $dateRange)
    {
        $jobIds = AmJob::where('employer_id', $employerId)->pluck('id');
        
        // This would need to be customized based on your cost tracking
        $costs = [
            'job_board_posting' => 100, // Cost per job posting
            'recruiter_time' => 50, // Cost per hour
            'interview_time' => 75, // Cost per interview hour
            'background_checks' => 25, // Cost per background check
        ];
        
        $totalHires = Application::whereIn('job_id', $jobIds)
            ->whereHas('applicationStatusHistory', function ($query) use ($dateRange) {
                $query->where('status', 'accepted')
                      ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']]);
            })
            ->count();
        
        $totalJobs = AmJob::where('employer_id', $employerId)
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->count();
        
        $totalInterviews = Application::whereIn('job_id', $jobIds)
            ->whereHas('applicationStatusHistory', function ($query) use ($dateRange) {
                $query->where('status', 'interviewed')
                      ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']]);
            })
            ->count();
        
        $totalCost = ($totalJobs * $costs['job_board_posting']) + 
                    ($totalHires * $costs['recruiter_time'] * 20) + // 20 hours per hire
                    ($totalInterviews * $costs['interview_time'] * 2) + // 2 hours per interview
                    ($totalHires * $costs['background_checks']);
        
        return [
            'total_cost' => $totalCost,
            'cost_per_hire' => $totalHires > 0 ? round($totalCost / $totalHires, 2) : 0,
            'cost_breakdown' => [
                'job_postings' => $totalJobs * $costs['job_board_posting'],
                'recruiter_time' => $totalHires * $costs['recruiter_time'] * 20,
                'interviews' => $totalInterviews * $costs['interview_time'] * 2,
                'background_checks' => $totalHires * $costs['background_checks']
            ],
            'industry_benchmark' => 4000, // Industry average cost per hire
            'performance' => $totalHires > 0 ? 
                (($totalCost / $totalHires) < 4000 ? 'below_average' : 'above_average') : 'no_data'
        ];
    }
    
    /**
     * Generate predictive analytics
     */
    public function generatePredictiveAnalytics($employerId)
    {
        $jobIds = AmJob::where('employer_id', $employerId)->pluck('id');
        
        // Get historical data for the last 12 months
        $historicalData = Application::whereIn('job_id', $jobIds)
            ->where('created_at', '>=', Carbon::now()->subMonths(12))
            ->select(
                DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
                DB::raw('COUNT(*) as applications')
            )
            ->groupBy('month')
            ->orderBy('month')
            ->get();
        
        if ($historicalData->count() < 3) {
            return [
                'prediction' => 'insufficient_data',
                'message' => 'Need at least 3 months of data for predictions'
            ];
        }
        
        // Simple linear regression for trend prediction
        $trend = $this->calculateTrend($historicalData);
        $nextMonthPrediction = $this->predictNextMonth($historicalData, $trend);
        
        return [
            'trend' => $trend,
            'next_month_prediction' => $nextMonthPrediction,
            'confidence_level' => $this->calculateConfidenceLevel($historicalData),
            'recommendations' => $this->generatePredictiveRecommendations($trend, $nextMonthPrediction)
        ];
    }
    
    /**
     * Calculate trend using simple linear regression
     */
    private function calculateTrend($data)
    {
        $n = $data->count();
        $sumX = 0;
        $sumY = 0;
        $sumXY = 0;
        $sumX2 = 0;
        
        foreach ($data as $index => $point) {
            $x = $index + 1;
            $y = $point->applications;
            
            $sumX += $x;
            $sumY += $y;
            $sumXY += $x * $y;
            $sumX2 += $x * $x;
        }
        
        $slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumX2 - $sumX * $sumX);
        $intercept = ($sumY - $slope * $sumX) / $n;
        
        return [
            'slope' => round($slope, 2),
            'intercept' => round($intercept, 2),
            'direction' => $slope > 0 ? 'increasing' : ($slope < 0 ? 'decreasing' : 'stable')
        ];
    }
    
    /**
     * Predict next month applications
     */
    private function predictNextMonth($data, $trend)
    {
        $nextX = $data->count() + 1;
        $prediction = $trend['slope'] * $nextX + $trend['intercept'];
        
        return [
            'applications' => max(0, round($prediction)),
            'range' => [
                'min' => max(0, round($prediction * 0.8)),
                'max' => round($prediction * 1.2)
            ]
        ];
    }
    
    /**
     * Calculate confidence level
     */
    private function calculateConfidenceLevel($data)
    {
        // Simple confidence calculation based on data consistency
        $applications = $data->pluck('applications')->toArray();
        $mean = array_sum($applications) / count($applications);
        $variance = array_sum(array_map(function($x) use ($mean) { return pow($x - $mean, 2); }, $applications)) / count($applications);
        $stdDev = sqrt($variance);
        
        $coefficientOfVariation = $mean > 0 ? ($stdDev / $mean) : 1;
        
        if ($coefficientOfVariation < 0.2) return 'high';
        if ($coefficientOfVariation < 0.5) return 'medium';
        return 'low';
    }
    
    /**
     * Generate predictive recommendations
     */
    private function generatePredictiveRecommendations($trend, $prediction)
    {
        $recommendations = [];
        
        if ($trend['direction'] === 'decreasing') {
            $recommendations[] = 'Applications are trending downward. Consider refreshing job descriptions or expanding to new job boards.';
            $recommendations[] = 'Review your employer branding and company benefits to attract more candidates.';
        } elseif ($trend['direction'] === 'increasing') {
            $recommendations[] = 'Applications are growing! Prepare your hiring team for increased volume.';
            $recommendations[] = 'Consider implementing automated screening tools to handle the increased applications efficiently.';
        }
        
        if ($prediction['applications'] > 100) {
            $recommendations[] = 'High application volume predicted. Ensure your ATS can handle the load.';
        } elseif ($prediction['applications'] < 20) {
            $recommendations[] = 'Low application volume predicted. Consider increasing job posting frequency or improving job visibility.';
        }
        
        return $recommendations;
    }
}
