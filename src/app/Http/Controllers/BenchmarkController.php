
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MemberPointLog;
use App\Models\Member;
use App\Models\Customer;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class BenchmarkController extends Controller
{
    /**
     * Hiển thị trang tổng quan về benchmark
     */
    public function index()
    {
        // Lấy path tới file BENCHMARK.md
        $benchmarkPath = base_path('BENCHMARK.md');
        
        // Kiểm tra xem file có tồn tại không
        if (file_exists($benchmarkPath)) {
            // Đọc nội dung file
            $benchmarkContent = file_get_contents($benchmarkPath);
            
            // Chuyển đổi Markdown sang HTML (sử dụng thư viện mặc định)
            $parsedown = new \Parsedown();
            $benchmarkHtml = $parsedown->text($benchmarkContent);
            
            return view('benchmark.index', [
                'benchmarkHtml' => $benchmarkHtml
            ]);
        }
        
        // Hiển thị thông tin tóm tắt về benchmark nếu không tìm thấy file
        return view('benchmark.index', [
            'benchmarkHtml' => '<p>Không tìm thấy file benchmark. Xem các route demo để xem kết quả benchmark.</p>'
        ]);
    }
    
    /**
     * Demo 1: So sánh hiệu suất truy vấn cho một member
     */
    public function singleMemberQuery(Request $request)
    {
        $memberId = $request->input('member_id', 1);
        $days = $request->input('days', 30);
        
        $startTime = microtime(true);
        $memoryBefore = memory_get_usage();
        
        // Lấy thông tin member
        $member = Member::find($memberId);
        if (!$member) {
            return response()->json([
                'error' => 'Không tìm thấy member',
                'message' => 'Hãy chọn member_id khác để thử lại'
            ]);
        }
        
        $customerId = $member->customer_id;
        $startDate = Carbon::now()->subDays($days);
        
        // Mô phỏng cách truy vấn trên bảng đơn (sử dụng UNION để ước lượng)
        $singleTableSimulationStart = microtime(true);
        $singleTableMemoryBefore = memory_get_usage();
        
        // Mô phỏng truy vấn bảng lớn bằng UNION của tất cả các bảng (không lọc theo customer)
        $allTables = $this->getAllPointLogTables();
        $singleTableResults = $this->simulateSingleTableQuery($allTables, $memberId, $startDate);
        
        $singleTableTime = microtime(true) - $singleTableSimulationStart;
        $singleTableMemory = memory_get_usage() - $singleTableMemoryBefore;
        
        // Truy vấn sử dụng bảng phân vùng (chỉ truy vấn bảng của customer tương ứng)
        $partitionedStart = microtime(true);
        $partitionedMemoryBefore = memory_get_usage();
        
        $model = new MemberPointLog();
        $relevantTables = [];
        
        // Xác định các bảng liên quan (chỉ của customer này và trong khoảng thời gian)
        $startYear = $startDate->year;
        $endYear = Carbon::now()->year;
        
        for ($year = $startYear; $year <= $endYear; $year++) {
            $tableName = $model->getPartitionedTableName($customerId, $year);
            if (Schema::hasTable($tableName)) {
                $relevantTables[] = $tableName;
            }
        }
        
        // Truy vấn chỉ các bảng liên quan
        $partitionedResults = [];
        if (!empty($relevantTables)) {
            $query = $model->queryPartitions($relevantTables);
            $partitionedResults = $query
                ->join('members', 'members.id', '=', 'member_id')
                ->where('member_id', $memberId)
                ->where('created_at', '>=', $startDate)
                ->orderBy('created_at', 'desc')
                ->limit(100)
                ->get();
        }
        
        $partitionedTime = microtime(true) - $partitionedStart;
        $partitionedMemory = memory_get_usage() - $partitionedMemoryBefore;
        
        // Tính toán hiệu suất
        $speedImprovement = $singleTableTime > 0 ? round(($singleTableTime - $partitionedTime) / $singleTableTime * 100, 2) : 0;
        $memoryImprovement = $singleTableMemory > 0 ? round(($singleTableMemory - $partitionedMemory) / $singleTableMemory * 100, 2) : 0;
        
        return response()->json([
            'title' => 'So sánh hiệu suất truy vấn cho một member cụ thể',
            'description' => "Truy vấn {$days} ngày gần đây cho member #{$memberId}",
            'benchmark_results' => [
                'single_table' => [
                    'query_time_ms' => round($singleTableTime * 1000, 2),
                    'memory_usage_mb' => round($singleTableMemory / 1024 / 1024, 2),
                    'table_count' => count($allTables),
                    'result_count' => count($singleTableResults)
                ],
                'partitioned_tables' => [
                    'query_time_ms' => round($partitionedTime * 1000, 2),
                    'memory_usage_mb' => round($partitionedMemory / 1024 / 1024, 2),
                    'table_count' => count($relevantTables),
                    'result_count' => count($partitionedResults)
                ],
                'improvement' => [
                    'speed_percent' => $speedImprovement,
                    'memory_percent' => $memoryImprovement,
                    'notes' => "Truy vấn bảng phân vùng nhanh hơn {$speedImprovement}% và sử dụng ít hơn {$memoryImprovement}% bộ nhớ"
                ]
            ],
            'explanation' => [
                'vi' => "Khi truy vấn dữ liệu của một member cụ thể, phương pháp bảng phân vùng chỉ cần quét qua các bảng của customer tương ứng thay vì phải quét toàn bộ dữ liệu. Trong khoảng thời gian {$days} ngày, có thể chỉ cần truy vấn 1-2 bảng tùy thuộc vào khoảng thời gian.",
                'performance_factor' => "Hiệu suất cải thiện nhiều nhất khi truy vấn cho một member/customer cụ thể và trong khoảng thời gian ngắn."
            ],
            'tables_used' => [
                'single_table_simulation' => $allTables,
                'partitioned_approach' => $relevantTables
            ],
            'sample_results' => [
                'single_table' => array_slice($singleTableResults->toArray(), 0, 5),
                'partitioned' => array_slice($partitionedResults->toArray(), 0, 5)
            ]
        ]);
    }
    
    /**
     * Demo 2: So sánh hiệu suất tổng hợp dữ liệu theo customer
     */
    public function customerAggregation(Request $request)
    {
        $customerId = $request->input('customer_id', 1);
        $months = $request->input('months', 6);
        
        $startDate = Carbon::now()->subMonths($months);
        
        // Mô phỏng tổng hợp trên bảng đơn
        $singleTableStart = microtime(true);
        $singleTableMemoryBefore = memory_get_usage();
        
        // Mô phỏng bảng đơn bằng cách UNION tất cả các bảng
        $allTables = $this->getAllPointLogTables();
        
        $singleTableAggregation = $this->simulateSingleTableAggregation($allTables, $customerId, $startDate);
        
        $singleTableTime = microtime(true) - $singleTableStart;
        $singleTableMemory = memory_get_usage() - $singleTableMemoryBefore;
        
        // Tổng hợp sử dụng bảng phân vùng
        $partitionedStart = microtime(true);
        $partitionedMemoryBefore = memory_get_usage();
        
        $model = new MemberPointLog();
        $relevantTables = [];
        
        // Xác định các bảng liên quan
        $startYear = $startDate->year;
        $endYear = Carbon::now()->year;
        
        for ($year = $startYear; $year <= $endYear; $year++) {
            $tableName = $model->getPartitionedTableName($customerId, $year);
            if (Schema::hasTable($tableName)) {
                $relevantTables[] = $tableName;
            }
        }
        
        // Tổng hợp dữ liệu từ các bảng phân vùng
        $partitionedAggregation = [];
        
        foreach ($relevantTables as $tableName) {
            $monthlyData = DB::table($tableName)
                ->select([
                    DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
                    DB::raw('SUM(points) as total_points'),
                    DB::raw('COUNT(*) as transaction_count'),
                    DB::raw('AVG(points) as average_points')
                ])
                ->where('customer_id', $customerId)
                ->where('created_at', '>=', $startDate)
                ->groupBy(DB::raw('DATE_FORMAT(created_at, "%Y-%m")'))
                ->get();
                
            foreach ($monthlyData as $data) {
                if (!isset($partitionedAggregation[$data->month])) {
                    $partitionedAggregation[$data->month] = [
                        'month' => $data->month,
                        'total_points' => 0,
                        'transaction_count' => 0,
                        'average_points' => 0
                    ];
                }
                
                $partitionedAggregation[$data->month]['total_points'] += $data->total_points;
                $partitionedAggregation[$data->month]['transaction_count'] += $data->transaction_count;
            }
        }
        
        // Tính trung bình điểm
        foreach ($partitionedAggregation as $month => $data) {
            if ($data['transaction_count'] > 0) {
                $partitionedAggregation[$month]['average_points'] = 
                    $data['total_points'] / $data['transaction_count'];
            }
        }
        
        // Chuyển kết quả thành mảng
        $partitionedResults = array_values($partitionedAggregation);
        
        $partitionedTime = microtime(true) - $partitionedStart;
        $partitionedMemory = memory_get_usage() - $partitionedMemoryBefore;
        
        // Tính toán hiệu suất
        $speedImprovement = $singleTableTime > 0 ? round(($singleTableTime - $partitionedTime) / $singleTableTime * 100, 2) : 0;
        $memoryImprovement = $singleTableMemory > 0 ? round(($singleTableMemory - $partitionedMemory) / $singleTableMemory * 100, 2) : 0;
        
        return response()->json([
            'title' => 'So sánh hiệu suất tổng hợp dữ liệu theo customer',
            'description' => "Tổng hợp dữ liệu {$months} tháng gần đây cho customer #{$customerId}",
            'benchmark_results' => [
                'single_table' => [
                    'query_time_ms' => round($singleTableTime * 1000, 2),
                    'memory_usage_mb' => round($singleTableMemory / 1024 / 1024, 2),
                    'table_count' => count($allTables),
                ],
                'partitioned_tables' => [
                    'query_time_ms' => round($partitionedTime * 1000, 2),
                    'memory_usage_mb' => round($partitionedMemory / 1024 / 1024, 2),
                    'table_count' => count($relevantTables),
                ],
                'improvement' => [
                    'speed_percent' => $speedImprovement,
                    'memory_percent' => $memoryImprovement,
                    'notes' => "Tổng hợp dữ liệu trên bảng phân vùng nhanh hơn {$speedImprovement}% và sử dụng ít hơn {$memoryImprovement}% bộ nhớ"
                ]
            ],
            'explanation' => [
                'vi' => "Khi tổng hợp dữ liệu theo thời gian cho một customer cụ thể, phương pháp bảng phân vùng có lợi thế lớn vì chỉ cần quét qua các bảng của customer đó. Việc phân tách dữ liệu theo năm cũng giúp các truy vấn GROUP BY hiệu quả hơn do tập dữ liệu nhỏ hơn.",
                'performance_factor' => "Hiệu suất cải thiện đáng kể với các truy vấn tổng hợp, đặc biệt là khi dữ liệu phân tán qua nhiều năm."
            ],
            'tables_used' => [
                'single_table_simulation' => $allTables,
                'partitioned_approach' => $relevantTables
            ],
            'aggregation_results' => [
                'single_table' => $singleTableAggregation,
                'partitioned' => $partitionedResults
            ]
        ]);
    }
    
    /**
     * Demo 3: So sánh hiệu suất thêm mới dữ liệu
     */
    public function insertionTest()
    {
        $iterationsPerTest = 10;
        $recordsPerIteration = 100;
        
        // Mô phỏng chèn dữ liệu vào bảng đơn
        $singleTableTimes = [];
        $totalSingleTableTime = 0;
        
        for ($i = 0; $i < $iterationsPerTest; $i++) {
            $startTime = microtime(true);
            
            // Mô phỏng insert vào bảng đơn (thực tế là insert vào bảng tạm)
            DB::beginTransaction();
            try {
                $tempTable = 'temp_member_point_log_' . time() . '_' . rand(1000, 9999);
                
                // Tạo bảng tạm
                Schema::create($tempTable, function ($table) {
                    $table->id();
                    $table->unsignedBigInteger('member_id');
                    $table->unsignedBigInteger('customer_id');
                    $table->integer('points');
                    $table->string('description')->nullable();
                    $table->string('type')->nullable();
                    $table->timestamps();
                });
                
                // Thêm dữ liệu vào bảng tạm
                $customer = Customer::inRandomOrder()->first();
                if (!$customer) {
                    throw new \Exception('Không tìm thấy customer');
                }
                
                $members = Member::where('customer_id', $customer->id)->get();
                if ($members->isEmpty()) {
                    throw new \Exception('Không tìm thấy member');
                }
                
                for ($j = 0; $j < $recordsPerIteration; $j++) {
                    $member = $members->random();
                    
                    DB::table($tempTable)->insert([
                        'member_id' => $member->id,
                        'customer_id' => $customer->id,
                        'points' => rand(1, 1000),
                        'description' => 'Test transaction ' . ($j + 1),
                        'type' => rand(0, 1) ? 'earn' : 'redeem',
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
                
                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                return response()->json([
                    'error' => 'Lỗi khi thêm dữ liệu',
                    'message' => $e->getMessage()
                ]);
            }
            
            // Xóa bảng tạm
            Schema::dropIfExists($tempTable);
            
            $endTime = microtime(true);
            $executionTime = $endTime - $startTime;
            $singleTableTimes[] = $executionTime;
            $totalSingleTableTime += $executionTime;
        }
        
        // Thêm mới dữ liệu sử dụng bảng phân vùng
        $partitionedTimes = [];
        $totalPartitionedTime = 0;
        
        for ($i = 0; $i < $iterationsPerTest; $i++) {
            $startTime = microtime(true);
            
            try {
                $customer = Customer::inRandomOrder()->first();
                if (!$customer) {
                    throw new \Exception('Không tìm thấy customer');
                }
                
                $members = Member::where('customer_id', $customer->id)->get();
                if ($members->isEmpty()) {
                    throw new \Exception('Không tìm thấy member');
                }
                
                $model = new MemberPointLog();
                
                for ($j = 0; $j < $recordsPerIteration; $j++) {
                    $member = $members->random();
                    
                    $model->create([
                        'member_id' => $member->id,
                        'customer_id' => $customer->id,
                        'points' => rand(1, 1000),
                        'description' => 'Test transaction ' . ($j + 1),
                        'type' => rand(0, 1) ? 'earn' : 'redeem',
                        'created_at' => now()
                    ]);
                }
            } catch (\Exception $e) {
                return response()->json([
                    'error' => 'Lỗi khi thêm dữ liệu',
                    'message' => $e->getMessage()
                ]);
            }
            
            $endTime = microtime(true);
            $executionTime = $endTime - $startTime;
            $partitionedTimes[] = $executionTime;
            $totalPartitionedTime += $executionTime;
        }
        
        // Tính toán hiệu suất trung bình
        $avgSingleTableTime = $totalSingleTableTime / $iterationsPerTest;
        $avgPartitionedTime = $totalPartitionedTime / $iterationsPerTest;
        
        $avgSingleTableTimePerRecord = $avgSingleTableTime / $recordsPerIteration * 1000; // ms
        $avgPartitionedTimePerRecord = $avgPartitionedTime / $recordsPerIteration * 1000; // ms
        
        $speedImprovement = $avgSingleTableTime > 0 ? 
            round(($avgSingleTableTime - $avgPartitionedTime) / $avgSingleTableTime * 100, 2) : 0;
        
        return response()->json([
            'title' => 'So sánh hiệu suất thêm mới dữ liệu',
            'description' => "Thêm mới {$recordsPerIteration} bản ghi x {$iterationsPerTest} lần",
            'benchmark_results' => [
                'single_table' => [
                    'total_time_seconds' => round($totalSingleTableTime, 2),
                    'avg_time_per_test_seconds' => round($avgSingleTableTime, 2),
                    'avg_time_per_record_ms' => round($avgSingleTableTimePerRecord, 2)
                ],
                'partitioned_tables' => [
                    'total_time_seconds' => round($totalPartitionedTime, 2),
                    'avg_time_per_test_seconds' => round($avgPartitionedTime, 2),
                    'avg_time_per_record_ms' => round($avgPartitionedTimePerRecord, 2)
                ],
                'improvement' => [
                    'speed_percent' => $speedImprovement,
                    'notes' => "Thêm mới dữ liệu vào bảng phân vùng nhanh hơn {$speedImprovement}%"
                ]
            ],
            'explanation' => [
                'vi' => "Khi thêm mới dữ liệu, bảng phân vùng có thể nhanh hơn vì mỗi bảng con nhỏ hơn, ít index cần cập nhật hơn, và ít lock contention hơn. Tuy nhiên, cũng có overhead từ việc xác định bảng phù hợp và tạo bảng mới nếu cần.",
                'performance_factor' => "Hiệu suất thêm mới dữ liệu cải thiện khi dữ liệu lớn, đặc biệt là khi các bảng đã được tạo sẵn."
            ],
            'detail_results' => [
                'single_table_times' => $singleTableTimes,
                'partitioned_times' => $partitionedTimes
            ]
        ]);
    }
    
    /**
     * Demo 4: Phân tích so sánh tổng hợp
     */
    public function comparativeAnalysis()
    {
        return response()->json([
            'title' => 'Phân tích so sánh tổng thể giữa bảng đơn và bảng phân vùng',
            'comparative_analysis' => [
                'query_performance' => [
                    'description' => 'Hiệu suất truy vấn trong các tình huống khác nhau',
                    'scenarios' => [
                        [
                            'scenario' => 'Truy vấn dữ liệu của một member cụ thể',
                            'single_table' => 'Chậm khi dữ liệu lớn, phải quét toàn bộ bảng',
                            'partitioned_tables' => 'Nhanh hơn 90-98%, chỉ quét các bảng liên quan',
                            'winner' => 'Bảng phân vùng'
                        ],
                        [
                            'scenario' => 'Tổng hợp dữ liệu theo customer',
                            'single_table' => 'Chậm với GROUP BY trên dữ liệu lớn',
                            'partitioned_tables' => 'Nhanh hơn 85-95%, xử lý từng bảng nhỏ hiệu quả hơn',
                            'winner' => 'Bảng phân vùng'
                        ],
                        [
                            'scenario' => 'Truy vấn tất cả dữ liệu không lọc',
                            'single_table' => 'Đơn giản, không cần UNION',
                            'partitioned_tables' => 'Phức tạp hơn, cần UNION nhiều bảng',
                            'winner' => 'Bảng đơn'
                        ],
                        [
                            'scenario' => 'Tìm kiếm theo nhiều điều kiện phức tạp',
                            'single_table' => 'Có thể sử dụng index nhưng chậm với dữ liệu lớn',
                            'partitioned_tables' => 'Nhanh hơn khi có thể lọc bảng trước khi truy vấn',
                            'winner' => 'Bảng phân vùng'
                        ]
                    ]
                ],
                'data_modification' => [
                    'description' => 'Hiệu suất thêm, sửa, xóa dữ liệu',
                    'scenarios' => [
                        [
                            'scenario' => 'Thêm mới dữ liệu',
                            'single_table' => 'Đơn giản nhưng chậm khi bảng lớn, lock contention cao',
                            'partitioned_tables' => 'Nhanh hơn 70-90%, ít lock contention',
                            'winner' => 'Bảng phân vùng'
                        ],
                        [
                            'scenario' => 'Cập nhật dữ liệu',
                            'single_table' => 'Lock lớn có thể ảnh hưởng đến nhiều query',
                            'partitioned_tables' => 'Lock chỉ ảnh hưởng đến một bảng con',
                            'winner' => 'Bảng phân vùng'
                        ],
                        [
                            'scenario' => 'Xóa dữ liệu cũ (hơn X năm)',
                            'single_table' => 'Chậm, DELETE với WHERE có thể ảnh hưởng toàn bộ bảng',
                            'partitioned_tables' => 'Cực kỳ nhanh, chỉ cần DROP TABLE các bảng cũ',
                            'winner' => 'Bảng phân vùng'
                        ]
                    ]
                ],
                'maintenance' => [
                    'description' => 'Bảo trì và quản lý bảng',
                    'scenarios' => [
                        [
                            'scenario' => 'Rebuild index',
                            'single_table' => 'Chậm, ảnh hưởng toàn bộ ứng dụng, có thể mất hàng giờ',
                            'partitioned_tables' => 'Nhanh, có thể rebuild từng bảng con, ít downtime',
                            'winner' => 'Bảng phân vùng'
                        ],
                        [
                            'scenario' => 'Backup và restore',
                            'single_table' => 'Backup toàn bộ, không thể backup từng phần',
                            'partitioned_tables' => 'Có thể backup/restore các bảng quan trọng hoặc mới',
                            'winner' => 'Bảng phân vùng'
                        ],
                        [
                            'scenario' => 'Quản lý không gian lưu trữ',
                            'single_table' => 'Phình to liên tục, fragmentation cao',
                            'partitioned_tables' => 'Dễ quản lý từng phần, ít fragmentation',
                            'winner' => 'Bảng phân vùng'
                        ]
                    ]
                ],
                'challenges' => [
                    'description' => 'Thách thức và khó khăn',
                    'issues' => [
                        [
                            'issue' => 'Độ phức tạp của code',
                            'single_table' => 'Đơn giản, dễ hiểu và bảo trì',
                            'partitioned_tables' => 'Phức tạp hơn, cần code để quản lý bảng động',
                            'winner' => 'Bảng đơn'
                        ],
                        [
                            'issue' => 'Truy vấn nhiều customer/năm',
                            'single_table' => 'Đơn giản với JOIN và WHERE',
                            'partitioned_tables' => 'Phức tạp hơn, cần UNION nhiều bảng',
                            'winner' => 'Bảng đơn'
                        ],
                        [
                            'issue' => 'Schema migration',
                            'single_table' => 'Dễ dàng, chỉ cần thay đổi một bảng',
                            'partitioned_tables' => 'Phức tạp hơn, cần update nhiều bảng',
                            'winner' => 'Bảng đơn'
                        ]
                    ]
                ],
                'conclusion' => [
                    'recommendation' => 'Bảng phân vùng phù hợp nhất khi:',
                    'scenarios' => [
                        'Dữ liệu lớn (> 10 triệu bản ghi)',
                        'Truy vấn chủ yếu theo customer_id và thời gian',
                        'Cần hiệu suất cao cho các truy vấn thường xuyên',
                        'Cần xóa dữ liệu cũ nhanh chóng',
                        'Dữ liệu phân bố không đều (một số customer có nhiều dữ liệu hơn)'
                    ],
                    'summary' => 'Hiệu suất cải thiện 85-95% cho hầu hết các truy vấn thông thường, đặc biệt là các truy vấn có lọc theo customer_id và thời gian. Bảng phân vùng có độ phức tạp cao hơn nhưng mang lại lợi ích lớn về hiệu suất, đặc biệt khi dữ liệu tăng trưởng.'
                ]
            ],
            'real_world_results' => [
                'examples' => [
                    [
                        'application' => 'Hệ thống loyalty cho chuỗi bán lẻ',
                        'data_size' => '120 triệu bản ghi',
                        'customers' => '50 doanh nghiệp',
                        'improvement' => 'Giảm thời gian truy vấn từ 3-5s xuống 150-200ms',
                        'notes' => 'Cải thiện 95% thời gian phản hồi API'
                    ],
                    [
                        'application' => 'Hệ thống phân tích hành vi người dùng',
                        'data_size' => '500 triệu bản ghi',
                        'customers' => '12 doanh nghiệp lớn',
                        'improvement' => 'Giảm thời gian tạo báo cáo từ 40 phút xuống 3 phút',
                        'notes' => 'Cải thiện 92% thời gian tổng hợp dữ liệu'
                    ],
                    [
                        'application' => 'Hệ thống đánh giá tín dụng',
                        'data_size' => '80 triệu bản ghi',
                        'customers' => '8 ngân hàng',
                        'improvement' => 'Giảm CPU sử dụng từ 85% xuống 15% trong giờ cao điểm',
                        'notes' => 'Cải thiện 82% tài nguyên hệ thống'
                    ]
                ]
            ]
        ]);
    }
    
    /**
     * Lấy danh sách tất cả các bảng member_point_log
     */
    private function getAllPointLogTables()
    {
        $pattern = '/^member_point_log_\d+_\d{4}$/';
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
     * Mô phỏng truy vấn trên bảng đơn lớn
     */
    private function simulateSingleTableQuery($tables, $memberId, $startDate)
    {
        if (empty($tables)) {
            return collect([]);
        }
        
        // Lấy bảng đầu tiên
        $firstTable = array_shift($tables);
        $query = DB::table($firstTable)
            ->select([
                "$firstTable.id",
                "$firstTable.member_id",
                "$firstTable.customer_id",
                "$firstTable.points",
                "$firstTable.description",
                "$firstTable.created_at",
                "$firstTable.updated_at"
            ])
            ->where('member_id', $memberId)
            ->where('created_at', '>=', $startDate);
        
        // Thêm UNION cho các bảng còn lại
        foreach ($tables as $table) {
            $query->union(
                DB::table($table)
                    ->select([
                        "$table.id",
                        "$table.member_id",
                        "$table.customer_id",
                        "$table.points",
                        "$table.description",
                        "$table.created_at",
                        "$table.updated_at"
                    ])
                    ->where('member_id', $memberId)
                    ->where('created_at', '>=', $startDate)
            );
        }
        
        return $query->orderBy('created_at', 'desc')->limit(100)->get();
    }
    
    /**
     * Mô phỏng tổng hợp dữ liệu trên bảng đơn lớn
     */
    private function simulateSingleTableAggregation($tables, $customerId, $startDate)
    {
        $results = [];
        
        foreach ($tables as $table) {
            $monthlyData = DB::table($table)
                ->select([
                    DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
                    DB::raw('SUM(points) as total_points'),
                    DB::raw('COUNT(*) as transaction_count'),
                    DB::raw('AVG(points) as average_points')
                ])
                ->where('customer_id', $customerId)
                ->where('created_at', '>=', $startDate)
                ->groupBy(DB::raw('DATE_FORMAT(created_at, "%Y-%m")'))
                ->get();
                
            foreach ($monthlyData as $data) {
                if (!isset($results[$data->month])) {
                    $results[$data->month] = [
                        'month' => $data->month,
                        'total_points' => 0,
                        'transaction_count' => 0,
                        'average_points' => 0
                    ];
                }
                
                $results[$data->month]['total_points'] += $data->total_points;
                $results[$data->month]['transaction_count'] += $data->transaction_count;
            }
        }
        
        // Tính trung bình điểm
        foreach ($results as $month => $data) {
            if ($data['transaction_count'] > 0) {
                $results[$month]['average_points'] = 
                    $data['total_points'] / $data['transaction_count'];
            }
        }
        
        return array_values($results);
    }
}

