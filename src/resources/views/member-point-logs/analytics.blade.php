
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Points Analytics</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="container mt-4">
        <div class="row mb-4">
            <div class="col">
                <h1>Points Analytics</h1>
                <p class="text-muted">Data aggregated from partitioned tables by customer and year</p>
            </div>
            <div class="col-auto">
                <a href="{{ route('member-point-logs.index') }}" class="btn btn-secondary">Back to List</a>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header">
                <h5>Total Points by Customer and Year</h5>
            </div>
            <div class="card-body">
                <canvas id="pointsChart" height="300"></canvas>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h5>Points Data Table</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Customer</th>
                                @foreach($years as $year)
                                    <th>{{ $year }}</th>
                                @endforeach
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($results as $data)
                                <tr>
                                    <td>{{ $data['customer_name'] }}</td>
                                    @foreach($years as $year)
                                        <td>{{ number_format($data['yearly_points'][$year]) }}</td>
                                    @endforeach
                                    <td>
                                        {{ number_format(array_sum($data['yearly_points'])) }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Prepare data for the chart
        const chartData = {
            labels: {!! json_encode($years) !!},
            datasets: [
                @foreach($results as $index => $data)
                {
                    label: '{{ $data['customer_name'] }}',
                    data: [
                        @foreach($years as $year)
                            {{ $data['yearly_points'][$year] }},
                        @endforeach
                    ],
                    borderColor: getColor({{ $index }}),
                    backgroundColor: getColorWithOpacity({{ $index }}, 0.5),
                    borderWidth: 2,
                    tension: 0.1
                },
                @endforeach
            ]
        };
        
        // Function to generate colors
        function getColor(index) {
            const colors = [
                '#4285F4', '#EA4335', '#FBBC05', '#34A853', '#FF6D01',
                '#46BDC6', '#7B1FA2', '#C2185B', '#3949AB', '#00897B'
            ];
            return colors[index % colors.length];
        }
        
        function getColorWithOpacity(index, opacity) {
            const color = getColor(index);
            return color + Math.round(opacity * 255).toString(16).padStart(2, '0');
        }
        
        // Create the chart
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('pointsChart').getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: chartData,
                options: {
                    responsive: true,
                    plugins: {
                        title: {
                            display: true,
                            text: 'Points Accumulated by Year'
                        },
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Total Points'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Year'
                            }
                        }
                    }
                }
            });
        });
    </script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
