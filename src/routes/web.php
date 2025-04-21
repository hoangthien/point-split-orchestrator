
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MemberPointLogController;
use App\Http\Controllers\ExampleUsageController;
use App\Http\Controllers\DemoQueryController;

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
