<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\AnalyticsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class GenerateAnalyticsReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $employerId;
    protected $reportType;
    protected $parameters;

    /**
     * Create a new job instance.
     */
    public function __construct($employerId, $reportType, $parameters = [])
    {
        $this->employerId = $employerId;
        $this->reportType = $reportType;
        $this->parameters = $parameters;
    }

    /**
     * Execute the job.
     */
    public function handle(AnalyticsService $analyticsService)
    {
        $employer = User::find($this->employerId);
        
        if (!$employer) {
            return;
        }

        try {
            switch ($this->reportType) {
                               case 'comprehensive':
                    $this->generateComprehensiveReport($employer, $analyticsService);
                    break;
                case 'monthly':
                    $this->generateMonthlyReport($employer, $analyticsService);
                    break;
                case 'custom':
                    $this->generateCustomReport($employer, $analyticsService);
                    break;
                default:
                    throw new \Exception('Invalid report type');
            }
        } catch (\Exception $e) {
            \Log::error('Analytics report generation failed: ' . $e->getMessage());
            $this->notifyReportFailure($employer, $e->getMessage());
        }
    }

    /**
     * Generate comprehensive analytics report
     */
    private function generateComprehensiveReport($employer, $analyticsService)
    {
        $year = $this->parameters['year'] ?? date('Y');
        $timeframe = $this->parameters['timeframe'] ?? 'yearly';
        
        $dateRange = $this->getDateRange($year, $timeframe);
        
        // Gather all analytics data
        $reportData = [
            'employer' => [
                'name' => $employer->first_name . ' ' . $employer->last_name,
                'company' => $employer->employerProfile->company_name ?? 'N/A',
                'email' => $employer->email
            ],
            'report_period' => [
                'year' => $year,
                'timeframe' => $timeframe,
                'start_date' => $dateRange['start']->format('Y-m-d'),
                'end_date' => $dateRange['end']->format('Y-m-d')
            ],
            'overview' => $this->getOverviewData($employer->id, $dateRange),
            'conversion_metrics' => $analyticsService->calculateConversionMetrics($employer->id, $dateRange),
            'engagement_metrics' => $analyticsService->calculateEngagementMetrics($employer->id, $dateRange),
            'seasonal_trends' => $analyticsService->calculateSeasonalTrends($employer->id),
            'source_attribution' => $analyticsService->calculateSourceAttribution($employer->id, $dateRange),
            'cost_analysis' => $analyticsService->calculateCostPerHire($employer->id, $dateRange),
            'predictive_analytics' => $analyticsService->generatePredictiveAnalytics($employer->id),
            'insights' => $analyticsService->generateInsights($this->getBasicAnalyticsData($employer->id, $dateRange))
        ];
        
        // Generate PDF report
        $pdfPath = $this->generatePdfReport($reportData, 'comprehensive');
        
        // Send email with report
        $this->sendReportEmail($employer, $pdfPath, 'Comprehensive Analytics Report');
    }

    /**
     * Generate monthly analytics report
     */
    private function generateMonthlyReport($employer, $analyticsService)
    {
        $month = $this->parameters['month'] ?? date('n');
        $year = $this->parameters['year'] ?? date('Y');
        
        $startDate = \Carbon\Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endDate = \Carbon\Carbon::createFromDate($year, $month, 1)->endOfMonth();
        $dateRange = ['start' => $startDate, 'end' => $endDate];
        
        $reportData = [
            'employer' => [
                'name' => $employer->first_name . ' ' . $employer->last_name,
                'company' => $employer->employerProfile->company_name ?? 'N/A',
                'email' => $employer->email
            ],
            'report_period' => [
                'month' => $startDate->format('F Y'),
                'start_date' => $startDate->format('Y-m-d'),
                'end_date' => $endDate->format('Y-m-d')
            ],
            'monthly_summary' => $this->getMonthlyData($employer->id, $dateRange),
            'top_jobs' => $this->getTopJobsForPeriod($employer->id, $dateRange),
            'application_trends' => $this->getApplicationTrendsForPeriod($employer->id, $dateRange),
            'recommendations' => $this->getMonthlyRecommendations($employer->id, $dateRange)
        ];
        
        $pdfPath = $this->generatePdfReport($reportData, 'monthly');
        $this->sendReportEmail($employer, $pdfPath, 'Monthly Analytics Report - ' . $startDate->format('F Y'));
    }

    /**
     * Generate custom analytics report
     */
    private function generateCustomReport($employer, $analyticsService)
    {
        $reportData = [
            'employer' => [
                'name' => $employer->first_name . ' ' . $employer->last_name,
                'company' => $employer->employerProfile->company_name ?? 'N/A',
                'email' => $employer->email
            ],
            'custom_parameters' => $this->parameters,
            'data' => []
        ];
        
        // Add requested data sections
        if (in_array('demographics', $this->parameters['sections'] ?? [])) {
            $reportData['data']['demographics'] = $this->getApplicantDemographics($employer->id, $this->parameters);
        }
        
        if (in_array('performance', $this->parameters['sections'] ?? [])) {
            $reportData['data']['performance'] = $this->getPerformanceMetrics($employer->id, $this->parameters);
        }
        
        if (in_array('trends', $this->parameters['sections'] ?? [])) {
            $reportData['data']['trends'] = $analyticsService->calculateSeasonalTrends($employer->id);
        }
        
        $pdfPath = $this->generatePdfReport($reportData, 'custom');
        $this->sendReportEmail($employer, $pdfPath, 'Custom Analytics Report');
    }

    /**
     * Generate PDF report
     */
    private function generatePdfReport($data, $type)
    {
        // This would use a PDF library like DomPDF or TCPDF
        // For now, we'll create a JSON file as placeholder
        
        $filename = "analytics_report_{$type}_" . date('Y-m-d_H-i-s') . '.json';
        $path = "reports/{$filename}";
        
        Storage::disk('local')->put($path, json_encode($data, JSON_PRETTY_PRINT));
        
        return $path;
    }

    /**
     * Send report email
     */
    private function sendReportEmail($employer, $reportPath, $subject)
    {
        try {
            Mail::send('emails.analytics-report', [
                'employer' => $employer,
                'subject' => $subject
            ], function ($message) use ($employer, $reportPath, $subject) {
                $message->to($employer->email)
                        ->subject($subject)
                        ->attach(Storage::disk('local')->path($reportPath));
            });
        } catch (\Exception $e) {
            \Log::error('Failed to send analytics report email: ' . $e->getMessage());
        }
    }

    /**
     * Notify report failure
     */
    private function notifyReportFailure($employer, $error)
    {
        try {
            Mail::send('emails.report-failure', [
                'employer' => $employer,
                'error' => $error
            ], function ($message) use ($employer) {
                $message->to($employer->email)
                        ->subject('Analytics Report Generation Failed');
            });
        } catch (\Exception $e) {
            \Log::error('Failed to send report failure notification: ' . $e->getMessage());
        }
    }

    /**
     * Helper methods for data gathering
     */
    private function getDateRange($year, $timeframe)
    {
        switch ($timeframe) {
            case 'monthly':
                return [
                    'start' => \Carbon\Carbon::createFromDate($year, 1, 1)->startOfYear(),
                    'end' => \Carbon\Carbon::createFromDate($year, 12, 31)->endOfYear()
                ];
            case 'quarterly':
                return [
                    'start' => \Carbon\Carbon::createFromDate($year, 1, 1)->startOfYear(),
                    'end' => \Carbon\Carbon::createFromDate($year, 12, 31)->endOfYear()
                ];
            case 'yearly':
            default:
                return [
                    'start' => \Carbon\Carbon::createFromDate($year, 1, 1)->startOfYear(),
                    'end' => \Carbon\Carbon::createFromDate($year, 12, 31)->endOfYear()
                ];
        }
    }

    private function getOverviewData($employerId, $dateRange)
    {
        $jobIds = \App\Models\AmJob::where('employer_id', $employerId)->pluck('id');
        
        return [
            'total_jobs' => \App\Models\AmJob::where('employer_id', $employerId)
                ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
                ->count(),
            'total_applications' => \App\Models\Application::whereIn('job_id', $jobIds)
                ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
                ->count(),
            'total_views' => \App\Models\Activity::where('subject_type', \App\Models\AmJob::class)
                ->whereIn('subject_id', $jobIds)
                ->where('type', 'job_viewed')
                ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
                ->count(),
            'total_hires' => \App\Models\Application::whereIn('job_id', $jobIds)
                ->whereHas('applicationStatusHistory', function ($query) use ($dateRange) {
                    $query->where('status', 'accepted')
                          ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']]);
                })
                ->count()
        ];
    }

    private function getBasicAnalyticsData($employerId, $dateRange)
    {
        // Return basic analytics structure for insights generation
        $overview = $this->getOverviewData($employerId, $dateRange);
        
        return [
            'overview' => $overview,
            'performanceMetrics' => [
                'conversionRate' => $overview['total_views'] > 0 ? 
                    round(($overview['total_applications'] / $overview['total_views']) * 100, 2) : 0,
                'averageTimeToHire' => 30, // Placeholder
                'applicationCompletionRate' => 85, // Placeholder
                'applicantQualityScore' => 7.5 // Placeholder
            ],
            'applicantDemographics' => [
                'byGender' => [
                    ['name' => 'Male', 'value' => 60],
                    ['name' => 'Female', 'value' => 40]
                ]
            ]
        ];
    }

    private function getMonthlyData($employerId, $dateRange)
    {
        return $this->getOverviewData($employerId, $dateRange);
    }

    private function getTopJobsForPeriod($employerId, $dateRange)
    {
        return \App\Models\AmJob::where('employer_id', $employerId)
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->withCount(['applications' => function ($query) use ($dateRange) {
                $query->whereBetween('created_at', [$dateRange['start'], $dateRange['end']]);
            }])
            ->orderBy('applications_count', 'desc')
            ->limit(5)
            ->get(['id', 'title', 'applications_count']);
    }

    private function getApplicationTrendsForPeriod($employerId, $dateRange)
    {
        $jobIds = \App\Models\AmJob::where('employer_id', $employerId)->pluck('id');
        
        return \App\Models\Application::whereIn('job_id', $jobIds)
            ->whereBetween('created_at', [$dateRange['start'], $dateRange['end']])
            ->select(
                \DB::raw('DATE(created_at) as date'),
                \DB::raw('COUNT(*) as applications')
            )
            ->groupBy('date')
            ->orderBy('date')
            ->get();
    }

    private function getMonthlyRecommendations($employerId, $dateRange)
    {
        // Generate basic recommendations based on monthly data
        $overview = $this->getMonthlyData($employerId, $dateRange);
        $recommendations = [];
        
        if ($overview['total_applications'] < 50) {
            $recommendations[] = 'Consider increasing job posting frequency or improving job descriptions to attract more applications.';
        }
        
        if ($overview['total_views'] > 0 && ($overview['total_applications'] / $overview['total_views']) < 0.05) {
            $recommendations[] = 'Your conversion rate is low. Review job requirements and application process for improvements.';
        }
        
        return $recommendations;
    }

    private function getApplicantDemographics($employerId, $parameters)
    {
        // Placeholder implementation
        return [
            'byGender' => [
                ['name' => 'Male', 'value' => 60],
                ['name' => 'Female', 'value' => 40]
            ],
            'byAge' => [
                ['name' => '18-24', 'value' => 20],
                ['name' => '25-34', 'value' => 45],
                ['name' => '35-44', 'value' => 25],
                ['name' => '45+', 'value' => 10]
            ]
        ];
    }

    private function getPerformanceMetrics($employerId, $parameters)
    {
        // Placeholder implementation
        return [
            'conversionRate' => 6.8,
            'averageTimeToHire' => 28,
            'applicationCompletionRate' => 78.5,
            'applicantQualityScore' => 7.2
        ];
    }
}
