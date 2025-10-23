<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\SyncController;
use App\Http\Controllers\Api\StockByWhController;
use App\Http\Controllers\Api\WarehouseOrderController;
use App\Http\Controllers\Api\WarehouseOrderLineController;
use App\Http\Controllers\Api\ProdHeaderController;
use App\Http\Controllers\Api\SoInvoiceLineController;
use App\Http\Controllers\Api\ReceiptPurchaseController;
use App\Http\Controllers\Api\SyncLogController;

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

// Public API Routes (add auth middleware if needed)
Route::apiResources([
    'stocks' => StockByWhController::class,
    'warehouse-orders' => WarehouseOrderController::class,
    'warehouse-order-lines' => WarehouseOrderLineController::class,
    'production-headers' => ProdHeaderController::class,
    'invoice-lines' => SoInvoiceLineController::class,
    'receipt-purchases' => ReceiptPurchaseController::class,
    'sync-logs' => SyncLogController::class,
], ['only' => ['index', 'show']]);

// ERP Sync API Routes
Route::prefix('sync')->group(function () {
    // Start manual sync
    Route::post('/start', [SyncController::class, 'startManualSync']);

    // Get sync status
    Route::get('/status', [SyncController::class, 'getSyncStatus']);

    // Get sync logs with pagination
    Route::get('/logs', [SyncController::class, 'getSyncLogs']);

    // Get sync statistics
    Route::get('/statistics', [SyncController::class, 'getSyncStatistics']);

    // Cancel running sync
    Route::post('/cancel', [SyncController::class, 'cancelSync']);
});
