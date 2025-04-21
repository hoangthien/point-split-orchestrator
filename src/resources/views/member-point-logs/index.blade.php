
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member Point Logs</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <div class="row mb-4">
            <div class="col">
                <h1>Member Point Logs</h1>
                <p class="text-muted">Data is stored in partitioned tables by customer and year</p>
            </div>
            <div class="col-auto">
                <a href="{{ route('member-point-logs.create') }}" class="btn btn-primary">Create New Log</a>
                <a href="{{ route('member-point-logs.analytics') }}" class="btn btn-info">Analytics</a>
            </div>
        </div>
        
        <div class="card mb-4">
            <div class="card-header">
                <h5>Filter Logs</h5>
            </div>
            <div class="card-body">
                <form action="{{ route('member-point-logs.index') }}" method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label for="customer_id" class="form-label">Customer</label>
                        <select name="customer_id" id="customer_id" class="form-select">
                            <option value="">All Customers</option>
                            @foreach($customers as $id => $name)
                                <option value="{{ $id }}" {{ request('customer_id') == $id ? 'selected' : '' }}>
                                    {{ $name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="member_id" class="form-label">Member</label>
                        <select name="member_id" id="member_id" class="form-select">
                            <option value="">All Members</option>
                            @foreach($members as $id => $name)
                                <option value="{{ $id }}" {{ request('member_id') == $id ? 'selected' : '' }}>
                                    {{ $name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label for="start_date" class="form-label">Start Date</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" 
                               value="{{ request('start_date') }}">
                    </div>
                    <div class="col-md-3">
                        <label for="end_date" class="form-label">End Date</label>
                        <input type="date" class="form-control" id="end_date" name="end_date"
                               value="{{ request('end_date') }}">
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                        <a href="{{ route('member-point-logs.index') }}" class="btn btn-secondary">Reset</a>
                    </div>
                </form>
            </div>
        </div>
        
        @if(session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif
        
        <div class="card">
            <div class="card-header">
                <h5>Point Logs</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Customer</th>
                                <th>Member</th>
                                <th>Points</th>
                                <th>Type</th>
                                <th>Description</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($results as $log)
                                <tr>
                                    <td>{{ $log->id }}</td>
                                    <td>{{ $customers[$log->customer_id] ?? 'Unknown' }}</td>
                                    <td>{{ $members[$log->member_id] ?? 'Unknown' }}</td>
                                    <td>{{ $log->points }}</td>
                                    <td>{{ $log->type }}</td>
                                    <td>{{ $log->description }}</td>
                                    <td>{{ $log->created_at }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center">No point logs found</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                
                @if($results->count() > 0)
                    <div class="d-flex justify-content-center mt-4">
                        {{ $results->appends(request()->query())->links() }}
                    </div>
                @endif
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
