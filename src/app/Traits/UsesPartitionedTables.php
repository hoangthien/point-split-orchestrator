
<?php

namespace App\Traits;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

trait UsesPartitionedTables
{
    /**
     * Get a list of existing partition tables that match the pattern
     */
    public function getExistingPartitions($pattern)
    {
        $tables = [];
        $allTables = DB::select('SHOW TABLES');
        
        foreach ($allTables as $table) {
            $tableName = array_values((array) $table)[0];
            if (preg_match($pattern, $tableName)) {
                $tables[] = $tableName;
            }
        }
        
        return $tables;
    }
    
    /**
     * Get all partition tables for a specific customer
     */
    public function getCustomerPartitions($customerId)
    {
        $pattern = "/^member_point_log_{$customerId}_\d{4}$/";
        return $this->getExistingPartitions($pattern);
    }
    
    /**
     * Get all partition tables for a specific year
     */
    public function getYearPartitions($year)
    {
        $pattern = "/^member_point_log_\d+_{$year}$/";
        return $this->getExistingPartitions($pattern);
    }
    
    /**
     * Get partition table for specific customer and year
     */
    public function getPartition($customerId, $year)
    {
        $tableName = $this->getPartitionedTableName($customerId, $year);
        return Schema::hasTable($tableName) ? $tableName : null;
    }
    
    /**
     * Get a DB query builder that spans multiple partition tables
     */
    public function queryPartitions($partitions)
    {
        if (empty($partitions)) {
            return collect([]);
        }
        
        return DB::query()->fromPartitioned('member_point_log', $partitions);
    }
    
    /**
     * Get a builder for multiple years for a customer
     */
    public function queryCustomerYears($customerId, $startYear, $endYear)
    {
        $partitions = [];
        
        for ($year = $startYear; $year <= $endYear; $year++) {
            $tableName = $this->getPartitionedTableName($customerId, $year);
            if (Schema::hasTable($tableName)) {
                $partitions[] = $tableName;
            }
        }
        
        return $this->queryPartitions($partitions);
    }
    
    /**
     * Custom scope to filter by date range across partitions
     */
    public function scopeInDateRange($query, $startDate, $endDate)
    {
        $startYear = Carbon::parse($startDate)->year;
        $endYear = Carbon::parse($endDate)->year;
        
        // This will need custom implementation in the specific model
        // as it depends on the partition structure
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }
}
