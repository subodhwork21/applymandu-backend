<?php

namespace App\Notifications;

use App\Models\AmJob;
use App\Models\JobAlert;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewJobMatchesAlert extends Notification implements ShouldQueue
{
    use Queueable;

    protected $job;
    protected $alert;
        protected $matchPercentage;

    /**
     * Create a new notification instance.
     */
    public function __construct(AmJob $job, JobAlert $alert)
    {
        $this->job = $job;
        $this->alert = $alert;
         $this->matchPercentage = $matchPercentage ?? $alert->match_percentage ?? 100;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return [ 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $jobUrl = config('app.url') . '/jobs/' . $this->job->slug;
        $matchPercentage = round($this->matchPercentage);
        
        return (new MailMessage)
            ->subject("New Job Alert: {$this->job->title} ({$matchPercentage}% match)")
            ->greeting("Hello {$notifiable->name}!")
            ->line("We found a new job that matches your \"{$this->alert->alert_title}\" alert with a {$matchPercentage}% match.")
            ->line("Job Title: {$this->job->title}")
            ->line("Company: {$this->job->employer->company_name}")
            ->line("Location: {$this->job->location}")
            ->line("Salary Range: \${$this->job->salary_min} - \${$this->job->salary_max}")
            ->action('View Job', $jobUrl)
            ->line('Thank you for using Applymandu!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'job_id' => $this->job->id,
            'job_slug' => $this->job->slug,
            'job_title' => $this->job->title,
            'alert_id' => $this->alert->id,
            'alert_title' => $this->alert->alert_title,
            'employer_name' => $this->job->employer->company_name,
            'match_percentage' => round($this->matchPercentage),
        ];
    }
}
