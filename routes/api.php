<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\SyncController;
use App\Http\Controllers\Api\StockByWhController;
use App\Http\Controllers\Api\WarehouseOrderController;
use App\Http\Controllers\Api\WarehouseOrderLineController;
use App\Http\Controllers\Api\ProdHeaderController;
use App\Http\Controllers\Api\SoInvoiceLineController;
use App\Http\Controllers\Api\SoInvoiceLine2Controller;
use App\Http\Controllers\Api\ReceiptPurchaseController;
use App\Http\Controllers\Api\SyncLogController;
use App\Http\Controllers\Api\Dashboard1Controller;
use App\Http\Controllers\Api\Dashboard2Controller;
use App\Http\Controllers\Api\Dashboard3Controller;
use App\Http\Controllers\Api\Dashboard4Controller;
use App\Http\Controllers\Api\Dashboard5Controller;
use App\Http\Controllers\Api\Dashboard6Controller;
use App\Http\Controllers\Api\Dashboard7Controller;
use App\Http\Controllers\Api\Dashboard8Controller;

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
    'invoice-lines-2' => SoInvoiceLine2Controller::class,
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

// Dashboard API Routes
Route::prefix('dashboard')->group(function () {
    // Dashboard 1: Inventory Management & Stock Control
    Route::prefix('inventory')->group(function () {
        Route::get('/stock-level-overview', [Dashboard1Controller::class, 'stockLevelOverview']);
        Route::get('/stock-health-by-warehouse', [Dashboard1Controller::class, 'stockHealthByWarehouse']);
        Route::get('/top-critical-items', [Dashboard1Controller::class, 'topCriticalItems']);
        Route::get('/stock-distribution-by-product-type', [Dashboard1Controller::class, 'stockDistributionByProductType']);
        Route::get('/stock-by-customer', [Dashboard1Controller::class, 'stockByCustomer']);
        Route::get('/inventory-availability-vs-demand', [Dashboard1Controller::class, 'inventoryAvailabilityVsDemand']);
        Route::get('/stock-movement-trend', [Dashboard1Controller::class, 'stockMovementTrend']);
        Route::get('/debug-stock-count', [Dashboard1Controller::class, 'debugStockCount']);
        Route::get('/all-data', [Dashboard1Controller::class, 'getAllData']);
    });

    // Dashboard 2: Warehouse Operations
    Route::prefix('warehouse')->group(function () {
        Route::get('/order-summary', [Dashboard2Controller::class, 'warehouseOrderSummary']);
        Route::get('/order-flow-analysis', [Dashboard2Controller::class, 'orderFlowAnalysis']);
        Route::get('/delivery-performance', [Dashboard2Controller::class, 'deliveryPerformance']);
        Route::get('/order-status-distribution', [Dashboard2Controller::class, 'orderStatusDistribution']);
        Route::get('/daily-order-volume', [Dashboard2Controller::class, 'dailyOrderVolume']);
        Route::get('/order-fulfillment-rate', [Dashboard2Controller::class, 'orderFulfillmentRate']);
        Route::get('/top-items-moved', [Dashboard2Controller::class, 'topItemsMoved']);
        Route::get('/order-timeline', [Dashboard2Controller::class, 'warehouseOrderTimeline']);
        Route::get('/order-timeline/filters', [Dashboard2Controller::class, 'warehouseOrderTimelineFilters']);
        Route::get('/order-timeline/{orderNo}', [Dashboard2Controller::class, 'warehouseOrderTimelineDetail']);
        Route::get('/all-data', [Dashboard2Controller::class, 'getAllData']);
    });

    // Dashboard 3: Production Planning & Monitoring
    Route::prefix('production')->group(function () {
        Route::get('/kpi-summary', [Dashboard3Controller::class, 'productionKpiSummary']);
        Route::get('/status-distribution', [Dashboard3Controller::class, 'productionStatusDistribution']);
        Route::get('/by-customer', [Dashboard3Controller::class, 'productionByCustomer']);
        Route::get('/by-model', [Dashboard3Controller::class, 'productionByModel']);
        Route::get('/schedule-timeline', [Dashboard3Controller::class, 'productionScheduleTimeline']);
        Route::get('/outstanding-analysis', [Dashboard3Controller::class, 'productionOutstandingAnalysis']);
        Route::get('/by-division', [Dashboard3Controller::class, 'productionByDivision']);
        Route::get('/trend', [Dashboard3Controller::class, 'productionTrend']);
        Route::get('/outstanding-trend', [Dashboard3Controller::class, 'outstandingTrend']);
        Route::get('/all-data', [Dashboard3Controller::class, 'getAllData']);
    });

    // Dashboard 4: Sales & Shipment Analysis
    Route::prefix('sales')->group(function () {
        Route::get('/overview-kpi', [Dashboard4Controller::class, 'salesOverviewKpi']);
        Route::get('/revenue-trend', [Dashboard4Controller::class, 'revenueTrend']);
        Route::get('/top-customers-by-revenue', [Dashboard4Controller::class, 'topCustomersByRevenue']);
        Route::get('/by-product-type', [Dashboard4Controller::class, 'salesByProductType']);
        Route::get('/shipment-status-tracking', [Dashboard4Controller::class, 'shipmentStatusTracking']);
        Route::get('/delivery-performance', [Dashboard4Controller::class, 'deliveryPerformance']);
        Route::get('/invoice-status-distribution', [Dashboard4Controller::class, 'invoiceStatusDistribution']);
        Route::get('/order-fulfillment', [Dashboard4Controller::class, 'salesOrderFulfillment']);
        Route::get('/top-selling-products', [Dashboard4Controller::class, 'topSellingProducts']);
        Route::get('/revenue-by-currency', [Dashboard4Controller::class, 'revenueByCurrency']);
        Route::get('/monthly-sales-comparison', [Dashboard4Controller::class, 'monthlySalesComparison']);
        Route::get('/all-data', [Dashboard4Controller::class, 'getAllData']);
    });

    // Dashboard 5: Procurement & Receipt Analysis
    Route::prefix('procurement')->group(function () {
        Route::get('/kpi', [Dashboard5Controller::class, 'procurementKpi']);
        Route::get('/receipt-performance', [Dashboard5Controller::class, 'receiptPerformance']);
        Route::get('/top-suppliers-by-value', [Dashboard5Controller::class, 'topSuppliersByValue']);
        Route::get('/receipt-trend', [Dashboard5Controller::class, 'receiptTrend']);
        Route::get('/supplier-delivery-performance', [Dashboard5Controller::class, 'supplierDeliveryPerformance']);
        Route::get('/receipt-by-item-group', [Dashboard5Controller::class, 'receiptByItemGroup']);
        Route::get('/po-vs-invoice-status', [Dashboard5Controller::class, 'poVsInvoiceStatus']);
        Route::get('/outstanding-po-analysis', [Dashboard5Controller::class, 'outstandingPoAnalysis']);
        Route::get('/receipt-approval-rate-by-supplier', [Dashboard5Controller::class, 'receiptApprovalRateBySupplier']);
        Route::get('/purchase-price-trend', [Dashboard5Controller::class, 'purchasePriceTrend']);
        Route::get('/payment-status-tracking', [Dashboard5Controller::class, 'paymentStatusTracking']);
        Route::get('/all-data', [Dashboard5Controller::class, 'getAllData']);
    });

    // Dashboard 6: Supply Chain Integration
    Route::prefix('supply-chain')->group(function () {
        Route::get('/kpi', [Dashboard6Controller::class, 'supplyChainKpi']);
        Route::get('/order-to-cash-flow', [Dashboard6Controller::class, 'orderToCashFlow']);
        Route::get('/procure-to-pay-flow', [Dashboard6Controller::class, 'procureToPayFlow']);
        Route::get('/demand-vs-supply-analysis', [Dashboard6Controller::class, 'demandVsSupplyAnalysis']);
        Route::get('/lead-time-analysis', [Dashboard6Controller::class, 'leadTimeAnalysis']);
        Route::get('/material-availability-for-production', [Dashboard6Controller::class, 'materialAvailabilityForProduction']);
        Route::get('/backorder-analysis', [Dashboard6Controller::class, 'backorderAnalysis']);
        Route::get('/supply-chain-cycle-time-trend', [Dashboard6Controller::class, 'supplyChainCycleTimeTrend']);
        Route::get('/all-data', [Dashboard6Controller::class, 'getAllData']);
    });

    // Dashboard 7: Financial Overview
    Route::prefix('financial')->group(function () {
        Route::get('/kpi', [Dashboard7Controller::class, 'financialKpi']);
        Route::get('/revenue-vs-cost-trend', [Dashboard7Controller::class, 'revenueVsCostTrend']);
        Route::get('/revenue-by-customer-segment', [Dashboard7Controller::class, 'revenueByCustomerSegment']);
        Route::get('/cost-analysis-by-category', [Dashboard7Controller::class, 'costAnalysisByCategory']);
        Route::get('/margin-analysis-by-product', [Dashboard7Controller::class, 'marginAnalysisByProduct']);
        Route::get('/outstanding-receivables-aging', [Dashboard7Controller::class, 'outstandingReceivablesAging']);
        Route::get('/outstanding-payables-aging', [Dashboard7Controller::class, 'outstandingPayablesAging']);
        Route::get('/cash-flow-projection', [Dashboard7Controller::class, 'cashFlowProjection']);
        Route::get('/top-profitable-products', [Dashboard7Controller::class, 'topProfitableProducts']);
        Route::get('/revenue-by-currency-exchange-impact', [Dashboard7Controller::class, 'revenueByCurrencyExchangeImpact']);
        Route::get('/all-data', [Dashboard7Controller::class, 'getAllData']);
    });

    // Dashboard 8: Executive Summary
    Route::prefix('executive')->group(function () {
        Route::get('/overall-business-health', [Dashboard8Controller::class, 'overallBusinessHealth']);
        Route::get('/key-metrics-trend', [Dashboard8Controller::class, 'keyMetricsTrend']);
        Route::get('/inventory-health-summary', [Dashboard8Controller::class, 'inventoryHealthSummary']);
        Route::get('/production-performance', [Dashboard8Controller::class, 'productionPerformance']);
        Route::get('/sales-performance', [Dashboard8Controller::class, 'salesPerformance']);
        Route::get('/operational-efficiency', [Dashboard8Controller::class, 'operationalEfficiency']);
        Route::get('/financial-summary', [Dashboard8Controller::class, 'financialSummary']);
        Route::get('/critical-alerts-actions', [Dashboard8Controller::class, 'criticalAlertsActions']);
        Route::get('/department-performance-comparison', [Dashboard8Controller::class, 'departmentPerformanceComparison']);
        Route::get('/monthly-business-overview', [Dashboard8Controller::class, 'monthlyBusinessOverview']);
        Route::get('/all-data', [Dashboard8Controller::class, 'getAllData']);
    });
});
