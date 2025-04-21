
<?php

namespace App\Http\Controllers;

use App\Facades\PointLog;
use App\Models\MemberPointLog;
use Illuminate\Http\Request;
use Carbon\Carbon;

/**
 * Example controller that demonstrates various ways to use the partitioned tables
 */
class ExampleUsageController extends Controller
{
    /**
     * Example of creating a point log
     */
    public function createExample()
    {
        // Method 1: Using the model directly with create (most backward compatible)
        $log1 = MemberPointLog::create([
            'member_id' => 1,
            'customer_id' => 1, 
            'points' => 100,
            'description' => 'Purchase reward',
            'type' => 'reward'
        ]);
        
        // Method 2: Using the Facade with create
        $log2 = PointLog::create([
            'member_id' => 1,
            'customer_id' => 1,
            'points' => 200,
            'description' => 'Bonus points',
            'type' => 'bonus'
        ]);
        
        // Method 3: Using the model with date specified
        $log3 = MemberPointLog::create([
            'member_id' => 1,
            'customer_id' => 1,
            'points' => 300,
            'description' => 'Special promotion',
            'type' => 'bonus',
            'created_at' => '2022-06-15' // Will be saved to 2022 partition table
        ]);
        
        return [
            'log1' => $log1,
            'log2' => $log2,
            'log3' => $log3
        ];
    }
    
    /**
     * Example of querying point logs for a member
     */
    public function memberExample($memberId)
    {
        // Method 1: Using the model instance method
        $memberPointLog = new MemberPointLog();
        $logs1 = $memberPointLog->forMember($memberId)->get();
        
        // Method 2: Using the Facade
        $logs2 = PointLog::forMember($memberId)->get();
        
        // Method 3: Using the Service directly with automatic relation loading
        $pointLogService = app(\App\Services\MemberPointLogService::class);
        $logs3 = $pointLogService->getPointLogsWithRelations([
            'member_id' => $memberId,
            'start_date' => Carbon::now()->subYear()->toDateString(),
            'end_date' => Carbon::now()->toDateString()
        ]);
        
        return [
            'method1_count' => count($logs1),
            'method2_count' => count($logs2),
            'method3_count' => $logs3->count(),
            'method3_data' => $logs3 // This one includes member and customer names
        ];
    }
    
    /**
     * Example of querying point logs for a date range across all customers
     */
    public function dateRangeExample(Request $request)
    {
        $startDate = $request->input('start_date', Carbon::now()->subMonths(3)->toDateString());
        $endDate = $request->input('end_date', Carbon::now()->toDateString());
        
        // Get logs across all customers for the date range
        $logs = PointLog::forDateRange($startDate, $endDate)
            ->orderBy('created_at', 'desc')
            ->limit(100)
            ->get();
            
        return [
            'date_range' => [
                'start' => $startDate,
                'end' => $endDate
            ],
            'log_count' => count($logs),
            'logs' => $logs
        ];
    }
    
    /**
     * Example of a complex operation that spans multiple customers and years
     */
    public function complexExample()
    {
        // Define a date range spanning multiple years
        $startDate = '2021-01-01';
        $endDate = '2023-12-31';
        $startYear = Carbon::parse($startDate)->year;
        $endYear = Carbon::parse($endDate)->year;
        
        // Get all customers
        $customers = \App\Models\Customer::all();
        
        $results = [];
        
        // Process each customer
        foreach ($customers as $customer) {
            $customerData = [
                'customer_id' => $customer->id,
                'customer_name' => $customer->name,
                'yearly_totals' => [],
                'total_points' => 0
            ];
            
            // Get all partitions for this customer within the date range
            $pointLog = new MemberPointLog();
            
            // Process each year
            for ($year = $startYear; $year <= $endYear; $year++) {
                $tableName = $pointLog->getPartitionedTableName($customer->id, $year);
                
                // Skip if table doesn't exist (no data for this year)
                if (!\Illuminate\Support\Facades\Schema::hasTable($tableName)) {
                    $customerData['yearly_totals'][$year] = 0;
                    continue;
                }
                
                // Get sum for this year (using the specific partition table directly)
                $yearStart = "$year-01-01";
                $yearEnd = "$year-12-31";
                
                $total = \Illuminate\Support\Facades\DB::table($tableName)
                    ->whereBetween('created_at', [$yearStart, $yearEnd])
                    ->sum('points');
                
                $customerData['yearly_totals'][$year] = $total;
                $customerData['total_points'] += $total;
            }
            
            $results[] = $customerData;
        }
        
        return [
            'date_range' => [
                'start' => $startDate,
                'end' => $endDate
            ],
            'results' => $results
        ];
    }
    
    /**
     * Example of finding a specific record across partitions
     */
    public function findExample($id, Request $request)
    {
        $customerId = $request->input('customer_id');
        
        // If customer ID is provided, we can narrow the search to specific partitions
        if ($customerId) {
            $log = PointLog::findAcrossPartitions($id, $customerId);
        } else {
            // Search across all partitions (slower)
            $log = PointLog::findAcrossPartitions($id);
        }
        
        if (!$log) {
            return response()->json([
                'message' => "Point log with ID $id not found"
            ], 404);
        }
        
        return $log;
    }
}
