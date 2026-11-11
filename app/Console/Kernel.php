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
        // $schedule->command('inspire')->hourly();

        // ملخص يومي بالنواقص والصلاحية + نسخة احتياطية يومية
        // (على Windows يجب تشغيل "php artisan schedule:run" كل دقيقة من Task Scheduler — راجع docs/DEPLOYMENT.md)
        $schedule->command('alerts:daily')->dailyAt(config('backup.alerts_time', '07:30'))->withoutOverlapping();
        $schedule->command('backup:run')->dailyAt(config('backup.time', '23:30'))->withoutOverlapping();
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
