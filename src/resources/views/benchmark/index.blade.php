
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>So sánh hiệu suất: Bảng đơn vs. Bảng phân vùng</title>
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
        .markdown-content table {
            width: 100%;
            margin-bottom: 1rem;
            border-collapse: collapse;
        }
        .markdown-content table th,
        .markdown-content table td {
            padding: 0.75rem;
            border: 1px solid #dee2e6;
        }
        .markdown-content table thead th {
            background-color: #f8f9fa;
        }
        .markdown-content h1, 
        .markdown-content h2, 
        .markdown-content h3, 
        .markdown-content h4 {
            margin-top: 1.5rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="container mt-5">
        <h1 class="mb-4">So sánh hiệu suất: Bảng đơn vs. Bảng phân vùng</h1>
        
        @if(isset($benchmarkHtml))
            <div class="card mb-5">
                <div class="card-body markdown-content">
                    {!! $benchmarkHtml !!}
                </div>
            </div>
        @endif

        <h2>Demo API So sánh hiệu suất</h2>
        <p class="lead">
            Các API endpoint dưới đây cung cấp các demo minh họa hiệu suất trong thực tế khi sử dụng 
            bảng phân vùng so với bảng đơn.
        </p>

        <div class="alert alert-info">
            <strong>Lưu ý:</strong> Tất cả API endpoints dưới đây trả về kết quả dạng JSON. Mở trực tiếp URL trong trình duyệt để xem kết quả chi tiết.
        </div>

        <div class="endpoint">
            <h3>1. So sánh hiệu suất truy vấn cho một member</h3>
            <p>Minh họa hiệu suất khi truy vấn dữ liệu của một member cụ thể trong khoảng thời gian.</p>
            <h5>URL:</h5>
            <pre>/demo-perfor/single-member?member_id=1&days=30</pre>
            <p>Ví dụ: <a href="/demo-perfor/single-member?member_id=1&days=30" target="_blank">/demo-perfor/single-member?member_id=1&days=30</a></p>
            <p>Mô tả: So sánh hiệu suất khi truy vấn dữ liệu của một member cụ thể trong 30 ngày gần đây.</p>
        </div>

        <div class="endpoint">
            <h3>2. So sánh hiệu suất tổng hợp dữ liệu theo customer</h3>
            <p>Minh họa hiệu suất khi tổng hợp dữ liệu theo tháng cho một customer.</p>
            <h5>URL:</h5>
            <pre>/demo-perfor/customer-aggregation?customer_id=1&months=6</pre>
            <p>Ví dụ: <a href="/demo-perfor/customer-aggregation?customer_id=1&months=6" target="_blank">/demo-perfor/customer-aggregation?customer_id=1&months=6</a></p>
            <p>Mô tả: So sánh hiệu suất khi tổng hợp dữ liệu theo tháng cho một customer trong 6 tháng gần đây.</p>
        </div>

        <div class="endpoint">
            <h3>3. So sánh hiệu suất thêm mới dữ liệu</h3>
            <p>Minh họa hiệu suất khi thêm mới dữ liệu vào bảng đơn so với bảng phân vùng.</p>
            <h5>URL:</h5>
            <pre>/demo-perfor/insertion-test</pre>
            <p>Ví dụ: <a href="/demo-perfor/insertion-test" target="_blank">/demo-perfor/insertion-test</a></p>
            <p>Mô tả: So sánh hiệu suất khi thêm nhiều bản ghi mới vào bảng đơn so với bảng phân vùng.</p>
        </div>

        <div class="endpoint">
            <h3>4. Phân tích so sánh tổng thể</h3>
            <p>Cung cấp phân tích tổng thể về hiệu suất, bảo trì, và các thách thức giữa hai phương pháp.</p>
            <h5>URL:</h5>
            <pre>/demo-perfor/comparative</pre>
            <p>Ví dụ: <a href="/demo-perfor/comparative" target="_blank">/demo-perfor/comparative</a></p>
            <p>Mô tả: Phân tích so sánh tổng thể giữa bảng đơn và bảng phân vùng với nhiều yếu tố khác nhau.</p>
        </div>

        <div class="mt-5">
            <h2>Kết luận</h2>
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Phân vùng bảng theo customer_id và năm</h5>
                    <p class="card-text">
                        Phương pháp phân vùng bảng theo customer_id và năm (member_point_log_{customer_id}_{year}) mang lại cải thiện hiệu 
                        suất đáng kể cho hầu hết các trường hợp sử dụng. Lợi ích này càng rõ rệt khi:
                    </p>
                    <ul>
                        <li>Dữ liệu lớn (> 10 triệu bản ghi)</li>
                        <li>Truy vấn thường xuyên lọc theo customer_id và thời gian</li>
                        <li>Cần thực hiện các truy vấn tổng hợp (aggregation) trên dữ liệu lớn</li>
                        <li>Cần xóa dữ liệu cũ một cách hiệu quả</li>
                    </ul>
                    <p class="card-text">
                        Mặc dù phương pháp này có độ phức tạp cao hơn về mặt code, nhưng lợi ích về hiệu suất (thường cải thiện 85-95%) 
                        là rất đáng kể, đặc biệt là khi dữ liệu tăng trưởng theo thời gian.
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

