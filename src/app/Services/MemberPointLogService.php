
<?php

namespace App\Services;

use App\Models\MemberPointLog;
use App\Models\Member;
use App\Models\Customer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MemberPointLogService
{
    /**
     * Get point logs with pagination and joins to get member and customer names
     */
    public function getPointLogsWithRelations($filters, $perPage = 15)
    {
        $customerId = $filters['customer_id'] ?? null;
        $memberId = $filters['member_id'] ?? null;
        $startDate = $filters['start_date'] ?? Carbon::now()->subDays(30)->toDateString();
        $endDate = $filters['end_date'] ?? Carbon::now()->toDateString();
        
        $startYear = Carbon::parse($startDate)->year;
        $endYear = Carbon::parse($endDate)->year;
        
        // Get the list of tables to query
        $tables = $this->getRelevantTables($customerId, $memberId, $startYear, $endYear);
        
        if (empty($tables)) {
            return collect([])->paginate($perPage);
        }
        
        // Build the union query
        $firstTable = array_shift($tables);
        $query = DB::table($firstTable)
            ->select([
                "$firstTable.id",
                "$firstTable.member_id",
                "$firstTable.customer_id",
                "$firstTable.points",
                "$firstTable.type",
                "$firstTable.description",
                "$firstTable.created_at",
                'members.name as member_name',
                'customers.name as customer_name'
            ])
            ->join('members', "$firstTable.member_id", '=', 'members.id')
            ->join('customers', "$firstTable.customer_id", '=', 'customers.id');
        
        // Apply filters
        if ($memberId) {
            $query->where("$firstTable.member_id", $memberId);
        }
        
        if ($customerId) {
            $query->where("$firstTable.customer_id", $customerId);
        }
        
        $query->whereBetween("$firstTable.created_at", [$startDate, $endDate]);
        
        // Add unions for other tables
        foreach ($tables as $table) {
            $unionQuery = DB::table($table)
                ->select([
                    "$table.id",
                    "$table.member_id",
                    "$table.customer_id",
                    "$table.points",
                    "$table.type",
                    "$table.description",
                    "$table.created_at",
                    'members.name as member_name',
                    'customers.name as customer_name'
                ])
                ->join('members', "$table.member_id", '=', 'members.id')
                ->join('customers', "$table.customer_id", '=', 'customers.id');
            
            if ($memberId) {
                $unionQuery->where("$table.member_id", $memberId);
            }
            
            if ($customerId) {
                $unionQuery->where("$table.customer_id", $customerId);
            }
            
            $unionQuery->whereBetween("$table.created_at", [$startDate, $endDate]);
            
            $query->union($unionQuery);
        }
        
        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }
    
    /**
     * Get list of relevant tables for a query
     */
    private function getRelevantTables($customerId = null, $memberId = null, $startYear = null, $endYear = null)
    {
        $model = new MemberPointLog();
        $tables = [];
        
        // Default to current year if not specified
        if ($startYear === null) $startYear = Carbon::now()->year;
        if ($endYear === null) $endYear = Carbon::now()->year;
        
        // Get customer IDs to query
        if ($memberId) {
            $member = Member::find($memberId);
            if ($member) {
                $customerIds = [$member->customer_id];
            } else {
                return [];
            }
        } elseif ($customerId) {
            $customerIds = [$customerId];
        } else {
            $customerIds = Customer::pluck('id')->toArray();
        }
        
        // Find all relevant tables
        foreach ($customerIds as $cId) {
            for ($year = $startYear; $year <= $endYear; $year++) {
                $tableName = $model->getPartitionedTableName($cId, $year);
                
                if (Schema::hasTable($tableName)) {
                    $tables[] = $tableName;
                }
            }
        }
        
        return $tables;
    }
    
    /**
     * Migrate data from the original table to partitioned tables
     */
    public function migrateDataToPartitions($originalTable = 'member_point_log')
    {
        if (!Schema::hasTable($originalTable)) {
            return [
                'success' => false,
                'message' => "Original table $originalTable does not exist."
            ];
        }
        
        $model = new MemberPointLog();
        $count = 0;
        
        // Process in chunks to avoid memory issues
        DB::table($originalTable)->orderBy('id')->chunk(1000, function ($logs) use ($model, &$count) {
            foreach ($logs as $log) {
                // Convert to array
                $data = (array) $log;
                
                // Ensure table exists
                $year = Carbon::parse($data['created_at'])->year;
                $model->ensureTableExists($data['customer_id'], $year);
                
                // Insert into partitioned table
                $tableName = $model->getPartitionedTableName($data['customer_id'], $year);
                DB::table($tableName)->insert($data);
                
                $count++;
            }
        });
        
        return [
            'success' => true,
            'message' => "Successfully migrated $count records to partitioned tables."
        ];
    }
}
