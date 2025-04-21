
<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Member;
use App\Models\MemberPointLog;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class MemberPointLogController extends Controller
{
    /**
     * Display a listing of member point logs
     */
    public function index(Request $request)
    {
        $customerId = $request->input('customer_id');
        $memberId = $request->input('member_id');
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date') ?? now()->toDateString();
        
        if (!$startDate) {
            // Default to last 30 days if no date provided
            $startDate = now()->subDays(30)->toDateString();
        }
        
        $startYear = Carbon::parse($startDate)->year;
        $endYear = Carbon::parse($endDate)->year;
        
        $memberPointLog = new MemberPointLog();
        $query = null;
        
        // Query based on provided filters
        if ($memberId) {
            // Get the member's customer ID
            $member = Member::findOrFail($memberId);
            $customerIds = [$member->customer_id];
            $whereCallback = function($q) use ($memberId) {
                $q->where('member_id', $memberId);
            };
        } elseif ($customerId) {
            $customerIds = [$customerId];
            $whereCallback = function($q) use ($customerId) {
                $q->where('customer_id', $customerId);
            };
        } else {
            // If no specific filter, get all customers
            $customerIds = Customer::pluck('id')->toArray();
            $whereCallback = function($q) {};
        }
        
        // Prepare query across multiple tables
        foreach ($customerIds as $cId) {
            for ($year = $startYear; $year <= $endYear; $year++) {
                $tableName = $memberPointLog->getPartitionedTableName($cId, $year);
                
                // Check if table exists before querying
                if (!DB::getSchemaBuilder()->hasTable($tableName)) {
                    continue;
                }
                
                $tableQuery = DB::table($tableName)
                    ->where($whereCallback)
                    ->whereBetween('created_at', [$startDate, $endDate]);
                
                if ($query === null) {
                    $query = $tableQuery;
                } else {
                    $query->union($tableQuery);
                }
            }
        }
        
        // If no tables found, return empty collection
        if ($query === null) {
            $results = collect([]);
        } else {
            $results = $query->orderBy('created_at', 'desc')->paginate(15);
        }
        
        // Get related data for display
        $members = Member::pluck('name', 'id');
        $customers = Customer::pluck('name', 'id');
        
        return view('member-point-logs.index', compact('results', 'members', 'customers'));
    }
    
    /**
     * Show the form for creating a new point log
     */
    public function create()
    {
        $members = Member::all();
        $customers = Customer::all();
        
        return view('member-point-logs.create', compact('members', 'customers'));
    }
    
    /**
     * Store a newly created point log
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'member_id' => 'required|exists:members,id',
            'points' => 'required|integer',
            'description' => 'nullable|string',
            'type' => 'nullable|string',
        ]);
        
        // Get customer_id from member
        $member = Member::findOrFail($request->member_id);
        $validated['customer_id'] = $member->customer_id;
        
        // Create point log in the appropriate table
        MemberPointLog::create($validated);
        
        return redirect()->route('member-point-logs.index')
            ->with('success', 'Point log created successfully');
    }
    
    /**
     * Display analytics dashboard
     */
    public function analytics()
    {
        // Example of complex query across multiple tables
        // Here we'll get sum of points by customer and year
        
        $results = collect();
        $memberPointLog = new MemberPointLog();
        $customers = Customer::all();
        $currentYear = Carbon::now()->year;
        
        // Get data for the last 5 years
        $years = range($currentYear - 4, $currentYear);
        
        foreach ($customers as $customer) {
            $customerData = [
                'customer_name' => $customer->name,
                'customer_id' => $customer->id,
                'yearly_points' => []
            ];
            
            foreach ($years as $year) {
                $tableName = $memberPointLog->getPartitionedTableName($customer->id, $year);
                
                // Check if table exists
                if (!DB::getSchemaBuilder()->hasTable($tableName)) {
                    $customerData['yearly_points'][$year] = 0;
                    continue;
                }
                
                // Sum points for this customer and year
                $sum = DB::table($tableName)->sum('points');
                $customerData['yearly_points'][$year] = $sum;
            }
            
            $results->push($customerData);
        }
        
        return view('member-point-logs.analytics', compact('results', 'years'));
    }
}
