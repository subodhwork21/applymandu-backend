<?php

namespace App\Jobs;

use App\Models\AmJob;
use App\Services\JobAlertMatchingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessJobAlertMatching implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $jobModel; // Renamed from $job to $jobModel

    /**
     * Create a new job instance.
     *
     * @param AmJob $job
     * @return void
     */
    public function __construct(AmJob $job)
    {
        $this->jobModel = $job; // Use the renamed property
    }

    /**
     * Execute the job.
     *
     * @param JobAlertMatchingService $matchingService
     * @return void
     */
    public function handle(JobAlertMatchingService $matchingService)
    {
        try {
            Log::info('Processing job alert matching from queue', ['job_id' => $this->jobModel->id]);
            $matchingService->matchJobWithAlerts($this->jobModel);
        } catch (\Exception $e) {
            Log::error('Error processing job alert matching from queue', [
                'job_id' => $this->jobModel->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Determine if the job should be retried
            if ($this->attempts() < 3) {
                // Release the job back to the queue to be retried after 30 seconds
                $this->release(30);
            }
        }
    }
}
