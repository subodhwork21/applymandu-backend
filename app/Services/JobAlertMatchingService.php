<?php

namespace App\Services;

use App\Models\AmJob;
use App\Models\JobAlert;
use App\Models\User;
use App\Notifications\NewJobMatchesAlert;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class JobAlertMatchingService
{
    /**
     * Match a new job against existing job alerts
     * 
     * @param AmJob $job The newly created job
     * @return void
     */
    public function matchJobWithAlerts(AmJob $job)
    {
        // Get all active job alerts
        $alerts = JobAlert::where('status', 'active')->get();

        $matchedAlerts = [];

        foreach ($alerts as $alert) {
            if ($this->jobMatchesAlert($job, $alert)) {
                // The match percentage is now stored in $alert->match_percentage
                $matchedAlerts[] = $alert;
            }
        }

        // Send notifications for matched alerts
        if (count($matchedAlerts) > 0) {
            $this->sendAlertNotifications($job, $matchedAlerts);
        }

        Log::info('Job alert matching completed', [
            'job_id' => $job->id,
            'matched_alerts_count' => count($matchedAlerts)
        ]);
    }

    /**
     * Check if a job matches the criteria in a job alert
     * 
     * @param AmJob $job
     * @param JobAlert $alert
     * @return bool
     */
  private function jobMatchesAlert(AmJob $job, JobAlert $alert)
{
    // Initialize counters for criteria matching
    $totalCriteria = 0;
    $matchedCriteria = 0;
    $debugInfo = [];

    // Match job category
    $totalCriteria++;
    $jobCategory = strtolower($job->department ?? '');
    $alertCategory = strtolower($alert->job_category ?? '');
    $categoryMatch = empty($alert->job_category) || $jobCategory == $alertCategory;
    if ($categoryMatch) {
        $matchedCriteria++;
    }
    $debugInfo['category'] = ['job' => $jobCategory, 'alert' => $alertCategory, 'matched' => $categoryMatch];

    // Match experience level
    $totalCriteria++;
    $jobExp = strtolower($job->experience_level ?? '');
    $alertExp = strtolower($alert->experience_level ?? '');
    $expMatch = empty($alert->experience_level) || $jobExp == $alertExp;
    if ($expMatch) {
        $matchedCriteria++;
    }
    $debugInfo['experience'] = ['job' => $jobExp, 'alert' => $alertExp, 'matched' => $expMatch];

    // Match salary range - minimum (with null safety)
    $totalCriteria++;
    $salaryMinMatch = empty($alert->salary_min) || 
                     (isset($job->salary_min) && $job->salary_min >= $alert->salary_min);
    if ($salaryMinMatch) {
        $matchedCriteria++;
    }
    $debugInfo['salary_min'] = ['job' => $job->salary_min ?? 'null', 'alert' => $alert->salary_min ?? 'null', 'matched' => $salaryMinMatch];

    // Match salary range - maximum (with null safety)
    $totalCriteria++;
    $salaryMaxMatch = empty($alert->salary_max) || 
                     (isset($job->salary_max) && $job->salary_max <= $alert->salary_max);
    if ($salaryMaxMatch) {
        $matchedCriteria++;
    }
    $debugInfo['salary_max'] = ['job' => $job->salary_max ?? 'null', 'alert' => $alert->salary_max ?? 'null', 'matched' => $salaryMaxMatch];

    // Match location (with null safety)
    $totalCriteria++;
    $jobLocation = strtolower($job->location ?? '');
    $alertLocation = strtolower($alert->location ?? '');
    $locationMatch = empty($alert->location) || str_contains($jobLocation, $alertLocation);
    if ($locationMatch) {
        $matchedCriteria++;
    }
    $debugInfo['location'] = ['job' => $jobLocation, 'alert' => $alertLocation, 'matched' => $locationMatch];

    // Match keywords
    $keywordMatch = true;
    if (!empty($alert->keywords)) {
        $keywords = array_filter(array_map('trim', explode(',', $alert->keywords)));
        $keywordMatches = 0;
        $jobTitle = strtolower($job->title ?? '');
        $jobDescription = strtolower($job->description ?? '');
        
        foreach ($keywords as $keyword) {
            $keyword = strtolower($keyword);
            if (str_contains($jobTitle, $keyword) || str_contains($jobDescription, $keyword)) {
                $keywordMatches++;
            }
        }

        $totalCriteria++;
        $keywordMatch = $keywordMatches > 0;
        if ($keywordMatch) {
            $matchedCriteria++;
        }
        $debugInfo['keywords'] = ['keywords' => $keywords, 'matches' => $keywordMatches, 'matched' => $keywordMatch];
    }

    // Calculate match percentage
    $matchPercentage = ($totalCriteria > 0) ? ($matchedCriteria / $totalCriteria) * 100 : 0;

    // Store the match percentage for potential use in notifications
    $alert->match_percentage = $matchPercentage;

    // Log detailed matching information
    Log::debug('Job alert matching details', [
        'job_id' => $job->id,
        'alert_id' => $alert->id,
        'criteria' => $debugInfo,
        'total_criteria' => $totalCriteria,
        'matched_criteria' => $matchedCriteria,
        'match_percentage' => $matchPercentage
    ]);

    // Return true if match percentage is at least 30%
    return $matchPercentage >= 30;
}





    /**
     * Send notifications to users with matching alerts
     * 
     * @param AmJob $job
     * @param array $alerts
     * @return void
     */
    private function sendAlertNotifications(AmJob $job, array $alerts)
    {
        foreach ($alerts as $alert) {
            $user = User::find($alert->user_id);

            if ($user) {
                // Check alert frequency to determine if we should send now
                // For simplicity, we'll send immediately for all frequencies
                // In a real implementation, you'd queue these based on frequency

                Notification::send($user, new NewJobMatchesAlert(
                    $job,
                    $alert,
                    $alert->match_percentage ?? 100
                ));
            }
        }
    }
}
