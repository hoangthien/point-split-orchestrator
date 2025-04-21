<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MemberPointLogController;
use App\Http\Controllers\ExampleUsageController;
use App\Http\Controllers\DemoQueryController;
use App\Http\Controllers\BenchmarkController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return view('welcome');
});

Route::get('/member-point-logs', [MemberPointLogController::class, 'index'])->name('member-point-logs.index');
Route::get('/member-point-logs/create', [MemberPointLogController::class, 'create'])->name('member-point-logs.create');
Route::post('/member-point-logs', [MemberPointLogController::class, 'store'])->name('member-point-logs.store');
Route::get('/member-point-logs/analytics', [MemberPointLogController::class, 'analytics'])->name('member-point-logs.analytics');

// Example usage routes
Route::prefix('examples')->group(function () {
    Route::get('/create', [ExampleUsageController::class, 'createExample']);
    Route::get('/member/{memberId}', [ExampleUsageController::class, 'memberExample']);
    Route::get('/date-range', [ExampleUsageController::class, 'dateRangeExample']);
    Route::get('/complex', [ExampleUsageController::class, 'complexExample']);
    Route::get('/find/{id}', [ExampleUsageController::class, 'findExample']);
});

// Demo Query routes với các ví dụ query phức tạp
Route::prefix('demo-query')->group(function () {
    Route::get('/', [DemoQueryController::class, 'index']);
    Route::get('/simple/{customerId?}/{year?}', [DemoQueryController::class, 'simpleQuery']);
    Route::get('/customer-years/{customerId?}/{startYear?}/{endYear?}', [DemoQueryController::class, 'customerMultiYearQuery']);
    Route::get('/complex', [DemoQueryController::class, 'complexMultiQuery']);
    Route::get('/trait', [DemoQueryController::class, 'traitBasedQuery']);
    Route::get('/report', [DemoQueryController::class, 'aggregationReport']);
});

// Benchmark Performance routes
Route::prefix('demo-perfor')->group(function () {
    Route::get('/', [BenchmarkController::class, 'index']);
    Route::get('/single-member', [BenchmarkController::class, 'singleMemberQuery']);
    Route::get('/customer-aggregation', [BenchmarkController::class, 'customerAggregation']);
    Route::get('/insertion-test', [BenchmarkController::class, 'insertionTest']);
    Route::get('/comparative', [BenchmarkController::class, 'comparativeAnalysis']);
    Route::get('/union-vs-join', [BenchmarkController::class, 'unionVsJoinDemo'])->name('demo-perfor.union-vs-join');
});
