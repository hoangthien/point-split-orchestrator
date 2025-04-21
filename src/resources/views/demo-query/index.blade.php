
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Demo các loại Query với Partitioned Tables</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        pre {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            max-height: 400px;
            overflow-y: auto;
        }
        .endpoint {
            margin-bottom: 30px;
            padding: 20px;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            background-color: #fff;
        }
        body {
            padding-bottom: 50px;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <h1 class="mb-4">Demo các loại Query trên Partitioned Tables</h1>
        <p class="lead">
            Trang này giải thích và demo các cách truy vấn dữ liệu trên hệ thống bảng phân vùng theo customer_id và năm.
            Mỗi endpoint dưới đây minh họa một loại truy vấn khác nhau.
        </p>

        <div class="alert alert-info">
            <strong>Lưu ý:</strong> Tất cả API endpoints dưới đây trả về kết quả dạng JSON. Bạn có thể mở trực tiếp URL trong trình duyệt để xem kết quả.
        </div>

        <div class="endpoint">
            <h3>1. Truy vấn đơn giản trên một bảng</h3>
            <p>Minh họa cách truy vấn đơn giản trên một bảng theo customer_id và năm.</p>
            <h5>URL:</h5>
            <pre>/demo-query/simple/{customer_id?}/{year?}</pre>
            <p>Ví dụ: <a href="/demo-query/simple/1/2023" target="_blank">/demo-query/simple/1/2023</a></p>
            <p>Mô tả: Lấy 10 bản ghi gần nhất từ một bảng cụ thể, kèm theo tên thành viên.</p>
        </div>

        <div class="endpoint">
            <h3>2. Truy vấn nhiều bảng của cùng một customer qua nhiều năm</h3>
            <p>Minh họa cách kết hợp dữ liệu từ nhiều bảng theo các năm khác nhau của cùng một customer.</p>
            <h5>URL:</h5>
            <pre>/demo-query/customer-years/{customer_id?}/{start_year?}/{end_year?}</pre>
            <p>Ví dụ: <a href="/demo-query/customer-years/1/2022/2023" target="_blank">/demo-query/customer-years/1/2022/2023</a></p>
            <p>Mô tả: Kết hợp dữ liệu từ nhiều bảng theo năm sử dụng UNION.</p>
        </div>

        <div class="endpoint">
            <h3>3. Truy vấn phức tạp trên nhiều customer, nhiều năm</h3>
            <p>Minh họa cách thực hiện truy vấn phức tạp kết hợp nhiều bảng và áp dụng các điều kiện lọc.</p>
            <h5>URL:</h5>
            <pre>/demo-query/complex</pre>
            <p>Tham số: customer_ids, start_date, end_date, min_points</p>
            <p>Ví dụ: <a href="/demo-query/complex?customer_ids[]=1&customer_ids[]=2&start_date=2022-01-01&end_date=2023-12-31&min_points=10" target="_blank">/demo-query/complex?customer_ids[]=1&customer_ids[]=2&start_date=2022-01-01&end_date=2023-12-31&min_points=10</a></p>
            <p>Mô tả: Kết hợp dữ liệu từ nhiều bảng và tạo báo cáo tổng hợp theo customer.</p>
        </div>

        <div class="endpoint">
            <h3>4. Truy vấn sử dụng trait UsesPartitionedTables</h3>
            <p>Minh họa cách sử dụng các phương thức từ trait để đơn giản hóa việc truy vấn nhiều bảng.</p>
            <h5>URL:</h5>
            <pre>/demo-query/trait</pre>
            <p>Tham số: customer_id hoặc member_id, start_date, end_date</p>
            <p>Ví dụ: <a href="/demo-query/trait?customer_id=1&start_date=2023-01-01&end_date=2023-12-31" target="_blank">/demo-query/trait?customer_id=1&start_date=2023-01-01&end_date=2023-12-31</a></p>
            <p>Mô tả: Sử dụng trait để tự động xác định và truy vấn các bảng phù hợp.</p>
        </div>

        <div class="endpoint">
            <h3>5. Báo cáo tổng hợp theo thời gian</h3>
            <p>Minh họa cách tạo báo cáo tổng hợp dữ liệu theo ngày, tháng hoặc năm trên nhiều bảng.</p>
            <h5>URL:</h5>
            <pre>/demo-query/report</pre>
            <p>Tham số: group_by (day, month, year), start_date, end_date</p>
            <p>Ví dụ: <a href="/demo-query/report?group_by=month&start_date=2022-01-01&end_date=2023-12-31" target="_blank">/demo-query/report?group_by=month&start_date=2022-01-01&end_date=2023-12-31</a></p>
            <p>Mô tả: Tạo báo cáo tổng hợp điểm và số lượng giao dịch theo thời gian.</p>
        </div>

        <div class="mt-5">
            <h2>Giải thích cách hoạt động</h2>
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">1. Truy vấn trên một bảng đơn</h5>
                    <p class="card-text">
                        Khi biết chính xác customer_id và năm, chúng ta có thể xác định chính xác tên bảng cần truy vấn 
                        bằng cách sử dụng <code>getPartitionedTableName()</code>. Sau đó thực hiện truy vấn trực tiếp
                        trên bảng đó với các join bình thường.
                    </p>
                    <pre>$tableName = $model->getPartitionedTableName($customerId, $year);
DB::table($tableName)->join(...)->where(...)->get();</pre>
                </div>
            </div>
            
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">2. Truy vấn trên nhiều bảng (UNION)</h5>
                    <p class="card-text">
                        Khi cần lấy dữ liệu từ nhiều bảng (nhiều năm, nhiều customer), chúng ta sử dụng UNION để kết hợp kết quả:
                    </p>
                    <pre>// Bắt đầu với một bảng
$query = DB::table($firstTable)->select(...);

// Union với các bảng còn lại
foreach ($remainingTables as $table) {
    $query->union(DB::table($table)->select(...));
}</pre>
                </div>
            </div>
            
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">3. Sử dụng trait UsesPartitionedTables</h5>
                    <p class="card-text">
                        Trait <code>UsesPartitionedTables</code> cung cấp các phương thức tiện ích để đơn giản hóa việc 
                        xác định các bảng cần truy vấn và thực hiện truy vấn trên nhiều bảng:
                    </p>
                    <pre>// Lấy danh sách các bảng theo customer và năm
$partitions = $model->getCustomerPartitions($customerId);

// Hoặc theo khoảng năm
$partitions = [];
for ($year = $startYear; $year <= $endYear; $year++) {
    $tableName = $model->getPartitionedTableName($customerId, $year);
    if (Schema::hasTable($tableName)) {
        $partitions[] = $tableName;
    }
}

// Truy vấn trên nhiều bảng
$query = $model->queryPartitions($partitions);</pre>
                </div>
            </div>
            
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">4. Truy vấn với phương thức từ Model</h5>
                    <p class="card-text">
                        Model <code>MemberPointLog</code> cung cấp các phương thức tiện ích để truy vấn dựa trên member, customer 
                        hoặc khoảng thời gian:
                    </p>
                    <pre>// Truy vấn theo member
$logs = $model->forMember($memberId);

// Truy vấn theo customer
$logs = $model->forCustomer($customerId);

// Truy vấn theo khoảng thời gian
$logs = $model->forDateRange($startDate, $endDate);</pre>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
