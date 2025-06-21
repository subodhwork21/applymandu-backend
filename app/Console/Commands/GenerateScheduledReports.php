<?php

namespace App\Console\Commands;

use App\Jobs\GenerateAnalyticsReportJob;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GenerateScheduledReports extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'analytics:generate-scheduled-reports {--type=monthly}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate scheduled analytics reports for employers';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $reportType = $this->option('type');
        
        $this->info("Starting generation of {$reportType} analytics reports...");
        
        try {
            // Get all employers who have opted in for scheduled reports
            $employers = User::role('employer')
                ->whereHas('employerProfile', function ($query) {
                    $query->where('receive_analytics_reports', true);
                })
                ->where('email_verified_at', '!=', null)
                ->get();
            
            $this->info("Found {$employers->count()} employers eligible for reports");
            
            $successCount = 0;
            $errorCount = 0;
            
            foreach ($employers as $employer) {
                try {
                    // Check if employer has sufficient data
                    if ($this->hasMinimumData($employer)) {
                        $parameters = $this->getReportParameters($reportType);
                        
                        GenerateAnalyticsReportJob::dispatch(
                            $employer->id,
                            $reportType,
                            $parameters
                        );
                        
                        $successCount++;
                        $this->line("✓ Queued report for {$employer->email}");
                    } else {
                        $this->line("⚠ Skipped {$employer->email} - insufficient data");
                    }
                } catch (\Exception $e) {
                    $errorCount++;
                    $this->error("✗ Failed to queue report for {$employer->email}: {$e->getMessage()}");
                    Log::error("Failed to queue analytics report for employer {$employer->id}: {$e->getMessage()}");
                }
            }
            
            $this->info("\nReport generation summary:");
            $this->info("✓ Successfully queued: {$successCount}");
            if ($errorCount > 0) {
                $this->error("✗ Errors: {$errorCount}");
            }
            
        } catch (\Exception $e) {
            $this->error("Failed to generate scheduled reports: {$e->getMessage()}");
            Log::error("Scheduled analytics report generation failed: {$e->getMessage()}");
            return 1;
        }
        
        return 0;
    }
    
    /**
     * Check if employer has minimum data for meaningful report
     */
    private function hasMinimumData($employer)
    {
        $jobCount = \App\Models\AmJob::where('employer_id', $employer->id)->count();
        $applicationCount = \App\Models\Application::whereHas('job', function ($query) use ($employer) {
            $query->where('employer_id', $employer->id);
        })->count();
        
        // Require at least 1 job and 5 applications for meaningful analytics
        return $jobCount >= 1 && $applicationCount >= 5;
    }
    
    /**
     * Get report parameters based on type
     */
    private function getReportParameters($type)
    {
        switch ($type) {
            case 'weekly':
                return [
                    'start_date' => now()->subWeek()->startOfWeek(),
                    'end_date' => now()->subWeek()->endOfWeek(),
                ];
            case 'monthly':
                return [
                    'month' => now()->subMonth()->month,
                    'year' => now()->subMonth()->year,
                ];
            case 'quarterly':
                return [
                    'quarter' => now()->subQuarter()->quarter,
                    'year' => now()->subQuarter()->year,
                ];
            default:
                return [];
        }
    }
}
