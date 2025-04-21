
<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MemberPointLogApiController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Member Point Log API Routes
Route::prefix('point-logs')->group(function () {
    Route::get('/', [MemberPointLogApiController::class, 'index']);
    Route::post('/', [MemberPointLogApiController::class, 'store']);
    Route::get('/statistics', [MemberPointLogApiController::class, 'statistics']);
    Route::get('/customer/{customerId}', [MemberPointLogApiController::class, 'customerSummary']);
    Route::get('/member/{memberId}', [MemberPointLogApiController::class, 'memberHistory']);
});
