
<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Member;
use App\Models\MemberPointLog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use App\Services\MemberPointLogService;

class MemberPointLogApiController extends Controller
{
    protected $pointLogService;
    
    public function __construct(MemberPointLogService $pointLogService)
    {
        $this->pointLogService = $pointLogService;
    }
    
    /**
     * Get paginated logs with relations for API
     */
    public function index(Request $request)
    {
        // Validate request parameters
        $request->validate([
            'customer_id' => 'nullable|exists:customers,id',
            'member_id' => 'nullable|exists:members,id',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);
        
        $filters = $request->only(['customer_id', 'member_id', 'start_date', 'end_date']);
        $perPage = $request->input('per_page', 15);
        
        // Use service to get data from multiple tables
        $logs = $this->pointLogService->getPointLogsWithRelations($filters, $perPage);
        
        return response()->json([
            'data' => $logs->items(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total()
            ]
        ]);
    }
    
    /**
     * Store point log via API
     */
    public function store(Request $request)
    {
        $request->validate([
            'member_id' => 'required|exists:members,id',
            'points' => 'required|integer',
            'description' => 'nullable|string|max:255',
            'type' => 'nullable|string|max:50',
            'created_at' => 'nullable|date'
        ]);
        
        // Get customer ID from member
        $member = Member::findOrFail($request->member_id);
        
        // Prepare data
        $data = $request->only(['member_id', 'points', 'description', 'type', 'created_at']);
        $data['customer_id'] = $member->customer_id;
        
        // Create log in appropriate partition
        $log = MemberPointLog::create($data);
        
        return response()->json([
            'message' => 'Point log created successfully',
            'data' => $log
        ], 201);
    }
    
    /**
     * Get customer points summary by year
     */
    public function customerSummary($customerId)
    {
        $customer = Customer::findOrFail($customerId);
        $memberPointLog = new MemberPointLog();
        $currentYear = Carbon::now()->year;
        $years = range($currentYear - 4, $currentYear);
        
        $summary = [
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name
            ],
            'yearly_summary' => []
        ];
        
        foreach ($years as $year) {
            $tableName = $memberPointLog->getPartitionedTableName($customer->id, $year);
            
            if (!app('db')->getSchemaBuilder()->hasTable($tableName)) {
                $summary['yearly_summary'][$year] = [
                    'total_points' => 0,
                    'transaction_count' => 0
                ];
                continue;
            }
            
            // Get summary for this year
            $yearSummary = app('db')->table($tableName)
                ->selectRaw('SUM(points) as total_points, COUNT(*) as transaction_count')
                ->first();
            
            $summary['yearly_summary'][$year] = [
                'total_points' => (int) $yearSummary->total_points,
                'transaction_count' => (int) $yearSummary->transaction_count
            ];
        }
        
        return response()->json($summary);
    }
    
    /**
     * Get member point history
     */
    public function memberHistory($memberId, Request $request)
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);
        
        $member = Member::findOrFail($memberId);
        $customerId = $member->customer_id;
        $startDate = $request->input('start_date', now()->subYear()->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());
        $perPage = $request->input('per_page', 15);
        
        $memberPointLog = new MemberPointLog();
        $startYear = Carbon::parse($startDate)->year;
        $endYear = Carbon::parse($endDate)->year;
        
        // Get all relevant partitions
        $partitions = [];
        for ($year = $startYear; $year <= $endYear; $year++) {
            $tableName = $memberPointLog->getPartitionedTableName($customerId, $year);
            if (app('db')->getSchemaBuilder()->hasTable($tableName)) {
                $partitions[] = $tableName;
            }
        }
        
        if (empty($partitions)) {
            return response()->json([
                'data' => [],
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => $perPage,
                    'total' => 0
                ]
            ]);
        }
        
        // Query across partitions
        $query = $memberPointLog->queryPartitions($partitions);
        $query->where('member_id', $memberId)
              ->whereBetween('created_at', [$startDate, $endDate])
              ->orderBy('created_at', 'desc');
        
        $paginator = $query->paginate($perPage);
        
        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total()
            ]
        ]);
    }
    
    /**
     * Generate statistics for a specific time period
     */
    public function statistics(Request $request)
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'group_by' => 'nullable|in:day,week,month,year',
        ]);
        
        $startDate = $request->input('start_date', now()->subMonth()->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());
        $groupBy = $request->input('group_by', 'day');
        
        // Implementation would aggregate data from appropriate partitions
        // based on date range and group by parameter
        
        // This is a simplified example
        $data = $this->generateStatistics($startDate, $endDate, $groupBy);
        
        return response()->json([
            'data' => $data,
            'meta' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'group_by' => $groupBy
            ]
        ]);
    }
    
    /**
     * Example implementation of statistics generator
     */
    private function generateStatistics($startDate, $endDate, $groupBy)
    {
        $memberPointLog = new MemberPointLog();
        $startYear = Carbon::parse($startDate)->year;
        $endYear = Carbon::parse($endDate)->year;
        $customers = Customer::pluck('id')->toArray();
        
        $partitions = [];
        
        // Get all relevant partitions
        foreach ($customers as $customerId) {
            for ($year = $startYear; $year <= $endYear; $year++) {
                $tableName = $memberPointLog->getPartitionedTableName($customerId, $year);
                if (app('db')->getSchemaBuilder()->hasTable($tableName)) {
                    $partitions[] = $tableName;
                }
            }
        }
        
        if (empty($partitions)) {
            return [];
        }
        
        // Format string for grouping by date
        $format = match($groupBy) {
            'day' => '%Y-%m-%d',
            'week' => '%Y-%u',
            'month' => '%Y-%m',
            'year' => '%Y',
            default => '%Y-%m-%d'
        };
        
        // This is a simplification - in a real application, 
        // you'd need to handle the union of multiple tables and aggregation
        // The actual implementation would depend on your database system
        
        // Here's a conceptual example (would need to be adapted for your specific DB)
        $results = [];
        $date = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);
        
        while ($date->lte($end)) {
            $dateKey = match($groupBy) {
                'day' => $date->format('Y-m-d'),
                'week' => $date->format('Y-W'),
                'month' => $date->format('Y-m'),
                'year' => $date->format('Y'),
                default => $date->format('Y-m-d')
            };
            
            // Simulated data
            $results[] = [
                'period' => $dateKey,
                'points_added' => rand(1000, 10000),
                'points_redeemed' => rand(500, 5000),
                'transaction_count' => rand(10, 100)
            ];
            
            // Increment by appropriate period
            $date->add(1, match($groupBy) {
                'day' => 'day',
                'week' => 'week',
                'month' => 'month',
                'year' => 'year',
                default => 'day'
            });
        }
        
        return $results;
    }
}
