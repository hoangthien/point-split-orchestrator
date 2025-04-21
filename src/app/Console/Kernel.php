
<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Automatically create partition tables for the next year in December
        $schedule->command('pointlog:create-partitions ' . (now()->year + 1))
                 ->monthly()
                 ->when(function () {
                     return now()->month === 12;
                 })
                 ->description('Create point log partition tables for next year');
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
