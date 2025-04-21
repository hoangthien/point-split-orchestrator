
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Extend query builder to support partitioned queries
        Builder::macro('fromPartitioned', function ($baseTableName, $partitions, $alias = null) {
            $query = $this->newQuery();
            
            if (empty($partitions)) {
                // If no partitions, return empty
                return $query;
            }
            
            // Start with first partition
            $firstPartition = array_shift($partitions);
            $from = $alias ? "{$firstPartition} as {$alias}" : $firstPartition;
            $query->from($from);
            
            // Union with remaining partitions
            foreach ($partitions as $partition) {
                $unionQuery = $this->newQuery()->from($partition);
                $query->union($unionQuery);
            }
            
            return $query;
        });
    }
}
