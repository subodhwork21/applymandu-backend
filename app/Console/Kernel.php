<?php

namespace App\Console;

use App\Models\Application;
use App\Models\ApplicationStatusHistory;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Other scheduled tasks...
        
        // Permanently delete applications where the restore period has expired
        $schedule->call(function () {
            Application::onlyTrashed()
                ->where('restore_until', '<', now())
                ->each(function ($application) {
                    // Permanently delete related status history
                    ApplicationStatusHistory::where('application_id', $application->id)->delete();
                    // Force delete the application
                    $application->forceDelete();
                });
        })->daily();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
