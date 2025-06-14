<?php

namespace App\Traits;

use App\Models\Activity;
use Illuminate\Support\Facades\Auth;

trait ActivityTrait
{
    public function recordActivity(string $type, $subject, ?string $description = null, ?int $userId = null)
    {
        $userId = $userId ?? Auth::id();

        if (!$userId) {
            throw new \Exception('No user ID provided or authenticated for activity recording');
        }

        if(Activity::where("user_id", $userId)->where("subject_type", get_class($subject))->where("subject_id", $subject->id)->where("type", $type)->exists()){
            return null;
        }

        Activity::create([
            'user_id' => $userId,
            'subject_type' => get_class($subject),
            'subject_id' => $subject->id,   
            'type' => $type,
            'description' => $description
        ]);
    }


    public function deleteActivity(string $type, $subject, ?string $description = null, ?int $userId = null)
    {
        $userId = $userId ?? Auth::id();

        if (!$userId) {
            throw new \Exception('No user ID provided or authenticated for activity deletion');
        }

        return Activity::where("user_id", $userId)->where("subject_type", get_class($subject))->where("subject_id", $subject->id)->where("type", $type)->delete();
    }

    /**
     * Record a profile view activity
     *
     * @param \App\Models\User $viewedBy Who viewed the profile
     * @param \App\Models\User $profileOwner Owner of the profile (who receives the activity)
     * @return \App\Models\Activity
     */
    public function recordProfileView($viewedBy, $profileOwner)
    {
        $companyName = $viewedBy->user->name ?? $viewedBy->name;
        
        return $this->recordActivity(
            'profile_viewed',
            $viewedBy,
            "Profile viewed by {$companyName}",
            $profileOwner->id
        );
    }

    /**
     * Record an application status change
     *
     * @param \App\Models\Application $application
     * @param string $oldStatus
     * @param string $newStatus
     * @return \App\Models\Activity
     */
    public function recordApplicationStatusChange($application, $oldStatus, $newStatus)
    {
        return $this->recordActivity(
            'application_status_update',
            $application,
            $oldStatus != null ? "Application status updated from {$oldStatus} to {$newStatus}" : "Application status updated to {$newStatus}",
            $application->user_id
        );
    }

    /**
     * Record a job match found
     *
     * @param \App\Models\Job $job
     * @param \App\Models\User $user
     * @return \App\Models\Activity
     */
    public function recordJobMatch($job, $user)
    {
        return $this->recordActivity(
            'job_match',
            $job,
            "New job match found based on your profile: {$job->title} at {$job->employer->name}",
            $user->id
        );
    }

    

    public function recordJobSave($job, $user)
    {
        return $this->recordActivity(
            'job_saved',
            $job,
            "Job saved: {$job->title}",
            $user->id
        );
    }

    public function recordViewJob($job, $user)
    {
        return $this->recordActivity(
            'job_viewed',
            $job,
            "Job viewed: {$job->title}",
            $user->id
        );
    }

    public function recordJobUnSave($job, $user)
    {
        return $this->deleteActivity(
            'job_saved',
            $job,
            "Job saved: {$job->title}",
            $user->id
        );
    }

    /**
     * Record a general activity based on the common types in system
     *
     * @param string $type One of: profile_viewed, application_status_update, job_match, etc.
     * @param mixed $subject The related model
     * @param int|null $userId User ID who receives this activity
     * @return \App\Models\Activity
     */
    public function recordActivityByType($type, $subject, ?int $userId = null)
    {
        $userId = $userId ?? Auth::id();
        $description = null;
        
        switch ($type) {
            case "job_viewed":
                $description = "Job viewed: {$subject->title}";
                break;
            case "resume_downloaded":
                $description = "Resume downloaded by {$subject->company_name}";
                break;

            case 'profile_viewed':
                $companyName = $subject->company_name;
                $description = "Profile viewed by {$companyName}";
                break;
                
            case 'application_status_update':
                $description = "Application status updated for {$subject->title}";
                break;
                
            case 'job_match':
                $description = "New job match found based on your profile: {$subject->title} at {$subject->user->name}";
                break;
                
            case 'application_submitted':
                $description = "Application submitted for {$subject->title}";
                break;
                
            case "job_saved":
                $description = "Job saved: {$subject->title}";
                break;
                
            case "application_viewed":
                $companyName =  $subject->employer->company_name;
                $description = "Your application for {$subject->title} at {$companyName} was viewed";
                break;
                
            case "interview_scheduled":
                $companyName =  $subject->employer->company_name;
                $description = "Interview scheduled for {$subject->title} position";
                break;
                
            case "application_rejected":
                $companyName =  $subject->employer->company_name;
                $description = "Your application for {$subject->title} at {$companyName} was not selected";
                break;
                
            case "application_accepted":
                $companyName =  $subject->employer->company_name;
                $description = "Congratulations! Your application for {$subject->title} at {$companyName} was accepted";
                break;
                
            case "message_received":
                $senderName = $subject->sender->name ?? "A recruiter";
                $description = "New message received from {$senderName}";
                break;
                
            // Add more types as needed
        }
        
        return $this->recordActivity($type, $subject, $description, $userId);
    }
}
