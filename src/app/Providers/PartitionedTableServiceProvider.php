
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Models\MemberPointLog;

class PartitionedTableServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register the MemberPointLog singleton to ensure consistent instance usage
        $this->app->singleton(MemberPointLog::class, function ($app) {
            return new MemberPointLog();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register any partition-related commands or listeners here
    }
}
