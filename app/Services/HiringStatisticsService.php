<?php

namespace App\Services;

use App\Models\Application;
use App\Models\AmJob;
use App\Models\User;
use App\Models\Activity;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class HiringStatisticsService
{
    /**
     * Calculate application rate statistics
     */
    public function getApplicationRateStats($employerId = null, $startDate = null, $endDate = null)
    {
        $startDate = $startDate ? Carbon::parse($startDate) : Carbon::now()->subDays(30);
        $endDate = $endDate ? Carbon::parse($endDate) : Carbon::now();

        $query = AmJob::query();
        
        if ($employerId) {
            $query->where('employer_id', $employerId);
        }

        $jobs = $query->whereBetween('created_at', [$startDate, $endDate])->get();
        
        $totalJobs = $jobs->count();
        $totalApplications = Application::whereIn('job_id', $jobs->pluck('id'))
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        $totalViews = Activity::where('subject_type', AmJob::class)
            ->whereIn('subject_id', $jobs->pluck('id'))
            ->where('type', 'job_viewed')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();

        return [
            'total_jobs' => $totalJobs,
            'total_applications' => $totalApplications,
            'total_views' => $totalViews,
            'application_rate' => $totalViews > 0 ? round(($totalApplications / $totalViews) * 100, 2) : 0,
            'applications_per_job' => $totalJobs > 0 ? round($totalApplications / $totalJobs, 2) : 0,
            'views_per_job' => $totalJobs > 0 ? round($totalViews / $totalJobs, 2) : 0,
        ];
    }

    /**
     * Calculate time to hire statistics
     */
    public function getTimeToHireStats($employerId = null, $startDate = null, $endDate = null)
    {
        $startDate = $startDate ? Carbon::parse($startDate) : Carbon::now()->subDays(90);
        $endDate = $endDate ? Carbon::parse($endDate) : Carbon::now();

        $query = Application::with(['job'])
            ->where('status', 'accepted')
            ->whereBetween('updated_at', [$startDate, $endDate]);

        if ($employerId) {
            $query->whereHas('job', function($q) use ($employerId) {
                $q->where('employer_id', $employerId);
            });
        }

        $acceptedApplications = $query->get();

        $timeToHireData = $acceptedApplications->map(function($application) {
            $jobPostedDate = $application->job->created_at;
            $hiredDate = $application->updated_at;
            return $jobPostedDate->diffInDays($hiredDate);
        });

        return [
            'total_hires' => $acceptedApplications->count(),
            'average_time_to_hire' => $timeToHireData->count() > 0 ? round($timeToHireData->avg(), 1) : 0,
            'median_time_to_hire' => $timeToHireData->count() > 0 ? $timeToHireData->median() : 0,
            'min_time_to_hire' => $timeToHireData->count() > 0 ? $timeToHireData->min() : 0,
            'max_time_to_hire' => $timeToHireData->count() > 0 ? $timeToHireData->max() : 0,
            'time_ranges' => [
                '0-7_days' => $timeToHireData->filter(fn($days) => $days <= 7)->count(),
                '8-14_days' => $timeToHireData->filter(fn($days) => $days > 7 && $days <= 14)->count(),
                '15-30_days' => $timeToHireData->filter(fn($days) => $days > 14 && $days <= 30)->count(),
                '31-60_days' => $timeToHireData->filter(fn($days) => $days > 30 && $days <= 60)->count(),
                '60+_days' => $timeToHireData->filter(fn($days) => $days > 60)->count(),
            ]
        ];
    }

    /**
     * Calculate cost per hire (you'll need to add cost tracking)
     */
    public function getCostPerHireStats($employerId = null, $startDate = null, $endDate = null)
    {
        $startDate = $startDate ? Carbon::parse($startDate) : Carbon::now()->subDays(90);
        $endDate = $endDate ? Carbon::parse($endDate) : Carbon::now();

        // Base costs (you can make these configurable)
        $platformCostPerJob = 100; // Cost to post a job
        $recruitmentCostPerHour = 50; // HR cost per hour
        $averageHoursPerHire = 20; // Average hours spent per hire

        $query = Application::with(['job'])
            ->where('status', 'accepted')
            ->whereBetween('updated_at', [$startDate, $endDate]);

        if ($employerId) {
            $query->whereHas('job', function($q) use ($employerId) {
                $q->where('employer_id', $employerId);
            });
        }

        $totalHires = $query->count();
        $uniqueJobs = $query->distinct('job_id')->count('job_id');

        $totalCost = ($uniqueJobs * $platformCostPerJob) + 
                    ($totalHires * $recruitmentCostPerHour * $averageHoursPerHire);

        return [
            'total_hires' => $totalHires,
            'total_cost' => $totalCost,
            'cost_per_hire' => $totalHires > 0 ? round($totalCost / $totalHires, 2) : 0,
            'cost_breakdown' => [
                'platform_costs' => $uniqueJobs * $platformCostPerJob,
                'recruitment_costs' => $totalHires * $recruitmentCostPerHour * $averageHoursPerHour,
            ]
        ];
    }

    /**
     * Get source effectiveness statistics
     */
    public function getSourceEffectivenessStats($employerId = null, $startDate = null, $endDate = null)
    {
        $startDate = $startDate ? Carbon::parse($startDate) : Carbon::now()->subDays(30);
        $endDate = $endDate ? Carbon::parse($endDate) : Carbon::now();

        $query = Application::with(['job', 'user'])
            ->whereBetween('created_at', [$startDate, $endDate]);

        if ($employerId) {
            $query->whereHas('job', function($q) use ($employerId) {
                $q->where('employer_id', $employerId);
            });
        }

        $applications = $query->get();

        // Group by source (you might need to add a source field to applications)
        $sourceStats = $applications->groupBy(function($application) {
            // This is a placeholder - you might want to track actual sources
            return $application->user->created_at->format('Y-m') === Carbon::now()->format('Y-m') 
                ? 'Direct Application' 
                : 'Organic Search';
        })->map(function($apps, $source) {
            $total = $apps->count();
            $accepted = $apps->where('status', 'accepted')->count();
            
            return [
                'source' => $source,
                'total_applications' => $total,
                'accepted_applications' => $accepted,
                'acceptance_rate' => $total > 0 ? round(($accepted / $total) * 100, 2) : 0,
            ];
        });

        return $sourceStats->values();
    }

    /**
     * Get quality of hire metrics
     */
    public function getQualityOfHireStats($employerId = null, $startDate = null, $endDate = null)
    {
        $startDate = $startDate ? Carbon::parse($startDate) : Carbon::now()->subDays(90);
        $endDate = $endDate ? Carbon::parse($endDate) : Carbon::now();

        $query = Application::with(['job', 'user.jobSeekerProfile'])
            ->whereBetween('created_at', [$startDate, $endDate]);

        if ($employerId) {
            $query->whereHas('job', function($q) use ($employerId) {
                $q->where('employer_id', $employerId);
            });
        }

        $applications = $query->get();

        $qualityMetrics = [
            'total_applications' => $applications->count(),
            'qualified_candidates' => 0,
            'overqualified_candidates' => 0,
            'underqualified_candidates' => 0,
            'experience_match' => [],
        ];

        foreach ($applications as $application) {
            $jobExperience = $application->job->experience_level ?? 'Mid Level';
            $candidateExperience = $application->user->jobSeekerProfile->experience_level ?? 'Entry Level';

            // Simple qualification logic (you can enhance this)
            if ($jobExperience === $candidateExperience) {
                $qualityMetrics['qualified_candidates']++;
            } elseif ($this->getExperienceLevel($candidateExperience) > $this->getExperienceLevel($jobExperience)) {
                $qualityMetrics['overqualified_candidates']++;
            } else {
                $qualityMetrics['underqualified_candidates']++;
            }

            $qualityMetrics['experience_match'][] = [
                'job_experience' => $jobExperience,
                'candidate_experience' => $candidateExperience,
                'match' => $jobExperience === $candidateExperience,
            ];
        }

        return $qualityMetrics;
    }

    /**
     * Helper method to convert experience level to numeric value
     */
    private function getExperienceLevel($level)
    {
        $levels = [
            'Entry Level' => 1,
            'Mid Level' => 2,
            'Senior Level' => 3,
            'Executive' => 4,
        ];

        return $levels[$level] ?? 1;
    }

    /**
     * Get comprehensive dashboard statistics
     */
    public function getDashboardStats($employerId = null, $startDate = null, $endDate = null)
    {
        return [
            'application_rate' => $this->getApplicationRateStats($employerId, $startDate, $endDate),
            'time_to_hire' => $this->getTimeToHireStats($employerId, $startDate, $endDate),
            'cost_per_hire' => $this->getCostPerHireStats($employerId, $startDate, $endDate),
            'source_effectiveness' => $this->getSourceEffectivenessStats($employerId, $startDate, $endDate),
            'quality_of_hire' => $this->getQualityOfHireStats($employerId, $startDate, $endDate),
        ];
    }
}
