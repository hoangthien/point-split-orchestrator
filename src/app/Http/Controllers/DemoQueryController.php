
<?php

namespace App\Http\Controllers;

use App\Models\MemberPointLog;
use App\Models\Member;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use App\Traits\UsesPartitionedTables;

class DemoQueryController extends Controller
{
    use UsesPartitionedTables;

    /**
     * Hiển thị trang demo các loại query
     */
    public function index()
    {
        return view('demo-query.index');
    }

    /**
     * Demo 1: Query đơn giản trên một customer trong một năm
     */
    public function simpleQuery($customerId = 1, $year = null)
    {
        if ($year === null) {
            $year = date('Y');
        }

        $model = new MemberPointLog();
        $tableName = $model->getPartitionedTableName($customerId, $year);

        // Kiểm tra xem table có tồn tại không
        if (!DB::getSchemaBuilder()->hasTable($tableName)) {
            return response()->json([
                'error' => "Bảng $tableName không tồn tại",
                'message' => 'Hãy chọn customer_id và năm khác để thử lại'
            ]);
        }

        // Truy vấn đơn giản trên 1 bảng
        $results = DB::table($tableName)
            ->select([
                "$tableName.id",
                "$tableName.points",
                "$tableName.created_at",
                'members.name as member_name'
            ])
            ->join('members', "$tableName.member_id", '=', 'members.id')
            ->orderBy("$tableName.created_at", 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'title' => "Truy vấn đơn giản trên bảng $tableName",
            'description' => "Lấy 10 bản ghi mới nhất từ customer $customerId trong năm $year",
            'code' => "DB::table('$tableName')
                ->select(['$tableName.id', '$tableName.points', '$tableName.created_at', 'members.name as member_name'])
                ->join('members', '$tableName.member_id', '=', 'members.id')
                ->orderBy('$tableName.created_at', 'desc')
                ->limit(10)
                ->get()",
            'results' => $results
        ]);
    }

    /**
     * Demo 2: Query trên nhiều bảng của cùng một customer qua nhiều năm
     */
    public function customerMultiYearQuery($customerId = 1, $startYear = null, $endYear = null)
    {
        if ($startYear === null) {
            $startYear = date('Y') - 1;
        }
        
        if ($endYear === null) {
            $endYear = date('Y');
        }

        // Danh sách các bảng cần truy vấn
        $tables = [];
        $model = new MemberPointLog();
        
        // Tạo danh sách các bảng cần truy vấn
        for ($year = $startYear; $year <= $endYear; $year++) {
            $tableName = $model->getPartitionedTableName($customerId, $year);
            if (DB::getSchemaBuilder()->hasTable($tableName)) {
                $tables[] = $tableName;
            }
        }

        if (empty($tables)) {
            return response()->json([
                'error' => "Không tìm thấy bảng nào cho customer $customerId từ năm $startYear đến $endYear",
                'message' => 'Hãy chọn customer_id và khoảng năm khác để thử lại'
            ]);
        }

        // Thực hiện truy vấn UNION qua nhiều bảng
        $firstTable = array_shift($tables);
        $query = DB::table($firstTable)
            ->select([
                "$firstTable.id",
                "$firstTable.points",
                "$firstTable.created_at",
                "$firstTable.description",
                'members.name as member_name',
                'customers.name as customer_name'
            ])
            ->join('members', "$firstTable.member_id", '=', 'members.id')
            ->join('customers', "$firstTable.customer_id", '=', 'customers.id');

        // Thêm UNION cho các bảng còn lại
        foreach ($tables as $table) {
            $query->union(
                DB::table($table)
                    ->select([
                        "$table.id",
                        "$table.points",
                        "$table.created_at",
                        "$table.description",
                        'members.name as member_name',
                        'customers.name as customer_name'
                    ])
                    ->join('members', "$table.member_id", '=', 'members.id')
                    ->join('customers', "$table.customer_id", '=', 'customers.id')
            );
        }

        // Sắp xếp kết quả và giới hạn số lượng
        $results = $query->orderBy('created_at', 'desc')
            ->limit(15)
            ->get();

        return response()->json([
            'title' => "Truy vấn nhiều bảng của customer $customerId từ năm $startYear đến $endYear",
            'description' => "Sử dụng UNION để kết hợp dữ liệu từ nhiều bảng theo năm",
            'tables' => $tables,
            'code' => "Sử dụng DB::table() với UNION để kết hợp dữ liệu từ các bảng: " . implode(', ', array_merge([$firstTable], $tables)),
            'results' => $results
        ]);
    }

    /**
     * Demo 3: Query phức tạp trên nhiều customer, nhiều năm với điều kiện lọc
     */
    public function complexMultiQuery(Request $request)
    {
        // Lấy tham số từ request
        $customerIds = $request->input('customer_ids', [1, 2, 3]);
        if (!is_array($customerIds)) {
            $customerIds = [$customerIds];
        }
        
        $startDate = $request->input('start_date', Carbon::now()->subYear()->toDateString());
        $endDate = $request->input('end_date', Carbon::now()->toDateString());
        $minPoints = $request->input('min_points', 0);
        
        // Xác định các năm cần truy vấn
        $startYear = Carbon::parse($startDate)->year;
        $endYear = Carbon::parse($endDate)->year;
        
        $model = new MemberPointLog();
        $tables = [];
        
        // Tạo danh sách các bảng cần truy vấn
        foreach ($customerIds as $customerId) {
            for ($year = $startYear; $year <= $endYear; $year++) {
                $tableName = $model->getPartitionedTableName($customerId, $year);
                if (DB::getSchemaBuilder()->hasTable($tableName)) {
                    $tables[] = $tableName;
                }
            }
        }
        
        if (empty($tables)) {
            return response()->json([
                'error' => "Không tìm thấy bảng nào phù hợp với điều kiện",
                'message' => 'Hãy chọn điều kiện lọc khác để thử lại'
            ]);
        }

        // Xây dựng truy vấn phức tạp trên nhiều bảng
        // Bắt đầu với bảng đầu tiên
        $firstTable = array_shift($tables);
        $query = DB::table($firstTable)
            ->select([
                "$firstTable.id",
                "$firstTable.points",
                "$firstTable.created_at",
                "$firstTable.type",
                "$firstTable.description",
                "$firstTable.member_id",
                "$firstTable.customer_id",
                'members.name as member_name',
                'customers.name as customer_name'
            ])
            ->join('members', "$firstTable.member_id", '=', 'members.id')
            ->join('customers', "$firstTable.customer_id", '=', 'customers.id')
            ->where("$firstTable.points", '>=', $minPoints)
            ->whereBetween("$firstTable.created_at", [$startDate, $endDate]);

        // Thêm UNION cho các bảng còn lại
        foreach ($tables as $table) {
            $query->union(
                DB::table($table)
                    ->select([
                        "$table.id",
                        "$table.points",
                        "$table.created_at",
                        "$table.type",
                        "$table.description",
                        "$table.member_id",
                        "$table.customer_id",
                        'members.name as member_name',
                        'customers.name as customer_name'
                    ])
                    ->join('members', "$table.member_id", '=', 'members.id')
                    ->join('customers', "$table.customer_id", '=', 'customers.id')
                    ->where("$table.points", '>=', $minPoints)
                    ->whereBetween("$table.created_at", [$startDate, $endDate])
            );
        }
        
        // Thêm sub-query và aggregation
        $results = DB::query()
            ->fromSub($query, 'combined_results')
            ->select([
                'customer_id',
                'customer_name',
                DB::raw('COUNT(*) as total_transactions'),
                DB::raw('SUM(points) as total_points'),
                DB::raw('AVG(points) as average_points')
            ])
            ->groupBy(['customer_id', 'customer_name'])
            ->orderBy('total_points', 'desc')
            ->get();

        // Lấy một số bản ghi chi tiết để minh họa
        $detailSample = $query->limit(5)->orderBy('created_at', 'desc')->get();

        return response()->json([
            'title' => "Truy vấn phức tạp trên nhiều customer và nhiều năm",
            'description' => "Tổng hợp điểm theo customer từ " . count($tables) + 1 . " bảng khác nhau",
            'parameters' => [
                'customer_ids' => $customerIds,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'min_points' => $minPoints
            ],
            'tables_used' => array_merge([$firstTable], $tables),
            'aggregated_results' => $results,
            'sample_detail_records' => $detailSample
        ]);
    }

    /**
     * Demo 4: Sử dụng trait UsesPartitionedTables để đơn giản hóa các truy vấn
     */
    public function traitBasedQuery(Request $request)
    {
        $customerId = $request->input('customer_id');
        $memberId = $request->input('member_id');
        $startDate = $request->input('start_date', Carbon::now()->subMonths(6)->toDateString());
        $endDate = $request->input('end_date', Carbon::now()->toDateString());
        
        $startYear = Carbon::parse($startDate)->year;
        $endYear = Carbon::parse($endDate)->year;
        
        $model = new MemberPointLog();
        $query = null;
        
        // Cách 1: Dùng queryCustomerYears từ trait
        if ($customerId) {
            $query = $model->queryCustomerYears($customerId, $startYear, $endYear);
            $description = "Truy vấn sử dụng phương thức queryCustomerYears từ trait";
        } 
        // Cách 2: Sử dụng forMember từ model
        elseif ($memberId) {
            $query = $model->forMember($memberId);
            $description = "Truy vấn sử dụng phương thức forMember từ model";
        } 
        // Cách 3: Sử dụng forDateRange từ model
        else {
            $query = $model->forDateRange($startDate, $endDate);
            $description = "Truy vấn sử dụng phương thức forDateRange từ model";
        }
        
        if ($query) {
            // Áp dụng điều kiện lọc và join
            $results = $query
                ->join('members', 'members.id', '=', 'member_id')
                ->join('customers', 'customers.id', '=', 'customer_id')
                ->select([
                    'member_id',
                    'customer_id',
                    'points',
                    'created_at',
                    'members.name as member_name',
                    'customers.name as customer_name'
                ])
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();
                
            return response()->json([
                'title' => "Truy vấn sử dụng trait UsesPartitionedTables",
                'description' => $description,
                'code' => "Sử dụng phương thức từ trait và model để đơn giản hóa việc truy vấn nhiều bảng",
                'parameters' => [
                    'customer_id' => $customerId,
                    'member_id' => $memberId,
                    'start_date' => $startDate,
                    'end_date' => $endDate
                ],
                'results' => $results
            ]);
        }
        
        return response()->json([
            'error' => "Không tìm thấy dữ liệu phù hợp",
            'message' => 'Hãy chọn điều kiện khác để thử lại'
        ]);
    }

    /**
     * Demo 5: Tạo báo cáo với các truy vấn tổng hợp theo ngày/tháng/năm
     */
    public function aggregationReport(Request $request)
    {
        $groupBy = $request->input('group_by', 'month'); // day, month, year
        $startDate = $request->input('start_date', Carbon::now()->subYear()->toDateString());
        $endDate = $request->input('end_date', Carbon::now()->toDateString());
        
        // Xác định định dạng thời gian dựa trên loại groupBy
        $dateFormat = match($groupBy) {
            'day' => '%Y-%m-%d',
            'month' => '%Y-%m',
            'year' => '%Y',
            default => '%Y-%m'
        };
        
        $startYear = Carbon::parse($startDate)->year;
        $endYear = Carbon::parse($endDate)->year;
        
        // Lấy tất cả customer
        $customers = Customer::pluck('id')->toArray();
        
        // Tìm tất cả các bảng liên quan
        $model = new MemberPointLog();
        $tables = [];
        
        foreach ($customers as $customerId) {
            for ($year = $startYear; $year <= $endYear; $year++) {
                $tableName = $model->getPartitionedTableName($customerId, $year);
                if (DB::getSchemaBuilder()->hasTable($tableName)) {
                    $tables[] = $tableName;
                }
            }
        }
        
        if (empty($tables)) {
            return response()->json([
                'error' => "Không tìm thấy dữ liệu trong khoảng thời gian đã chọn",
                'message' => 'Hãy chọn khoảng thời gian khác để thử lại'
            ]);
        }
        
        // Chuẩn bị truy vấn tổng hợp theo thời gian
        $results = [];
        
        foreach ($tables as $tableName) {
            // Trích xuất customer_id từ tên bảng
            preg_match('/member_point_log_(\d+)_\d{4}$/', $tableName, $matches);
            $customerId = $matches[1] ?? null;
            
            if (!$customerId) continue;
            
            // Thực hiện truy vấn tổng hợp trên từng bảng
            $tableData = DB::table($tableName)
                ->select([
                    DB::raw("DATE_FORMAT(created_at, '$dateFormat') as period"),
                    DB::raw('SUM(points) as total_points'),
                    DB::raw('COUNT(*) as transaction_count')
                ])
                ->where('customer_id', $customerId)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->groupBy(DB::raw("DATE_FORMAT(created_at, '$dateFormat')"))
                ->get();
            
            // Thêm customer_id và customer_name vào kết quả
            $customer = Customer::find($customerId);
            foreach ($tableData as $item) {
                $item->customer_id = $customerId;
                $item->customer_name = $customer ? $customer->name : "Customer #$customerId";
            }
            
            // Gộp kết quả
            $results = array_merge($results, $tableData->toArray());
        }
        
        // Sắp xếp kết quả theo thời gian
        usort($results, function($a, $b) {
            return $a->period <=> $b->period;
        });
        
        // Tính tổng và trung bình
        $totalPoints = array_sum(array_column($results, 'total_points'));
        $totalTransactions = array_sum(array_column($results, 'transaction_count'));
        $averagePoints = $totalTransactions > 0 ? $totalPoints / $totalTransactions : 0;
        
        return response()->json([
            'title' => "Báo cáo tổng hợp theo " . match($groupBy) {
                'day' => 'ngày',
                'month' => 'tháng',
                'year' => 'năm',
                default => 'tháng'
            },
            'description' => "Tổng hợp điểm và số lượng giao dịch theo thời gian từ $startDate đến $endDate",
            'parameters' => [
                'group_by' => $groupBy,
                'start_date' => $startDate,
                'end_date' => $endDate
            ],
            'summary' => [
                'total_points' => $totalPoints,
                'total_transactions' => $totalTransactions,
                'average_points_per_transaction' => $averagePoints
            ],
            'tables_used' => $tables,
            'detailed_results' => $results
        ]);
    }
}
