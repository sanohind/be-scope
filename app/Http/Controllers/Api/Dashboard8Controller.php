<?php

namespace App\Http\Controllers\Api;

use App\Models\SoInvoiceLine;
use App\Models\ProdHeader;
use App\Models\ReceiptPurchase;
use App\Models\StockByWh;
use App\Models\WarehouseOrder;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class Dashboard8Controller extends ApiController
{
    /**
     * Chart 8.1: Overall Business Health - Scorecard/KPI Grid
     */
    public function overallBusinessHealth(): JsonResponse
    {
        // Financial Metrics
        $currentRevenue = SoInvoiceLine::whereYear('invoice_date', now()->year)
            ->whereMonth('invoice_date', now()->month)
            ->sum('amount_hc');
        
        $previousRevenue = SoInvoiceLine::whereYear('invoice_date', now()->subMonth()->year)
            ->whereMonth('invoice_date', now()->subMonth()->month)
            ->sum('amount_hc');
        
        $revenueGrowth = $previousRevenue > 0 
            ? round((($currentRevenue - $previousRevenue) / $previousRevenue) * 100, 2) 
            : 0;

        $totalRevenue = SoInvoiceLine::sum('amount_hc');
        $totalCost = ReceiptPurchase::sum('receipt_amount');
        $profitMargin = $totalRevenue > 0 
            ? round((($totalRevenue - $totalCost) / $totalRevenue) * 100, 2) 
            : 0;

        // Operations Metrics
        $totalOrders = WarehouseOrder::count();
        $completedOrders = WarehouseOrder::where('status', 'Completed')->count();
        $orderFulfillmentRate = $totalOrders > 0 
            ? round(($completedOrders / $totalOrders) * 100, 2) 
            : 0;

        $onTimeDeliveries = SoInvoiceLine::whereRaw('receipt_date <= delivery_date')->count();
        $totalDeliveries = SoInvoiceLine::whereNotNull('receipt_date')->count();
        $onTimeDeliveryRate = $totalDeliveries > 0 
            ? round(($onTimeDeliveries / $totalDeliveries) * 100, 2) 
            : 0;

        // Production Metrics
        $totalQtyOrder = ProdHeader::sum('qty_order');
        $totalQtyDelivery = ProdHeader::sum('qty_delivery');
        $productionAchievement = $totalQtyOrder > 0 
            ? round(($totalQtyDelivery / $totalQtyOrder) * 100, 2) 
            : 0;

        // Supply Chain Metrics
        $totalItems = StockByWh::distinct('partno')->count('partno');
        $itemsInStock = StockByWh::where('onhand', '>', 0)->distinct('partno')->count('partno');
        $stockAvailability = $totalItems > 0 
            ? round(($itemsInStock / $totalItems) * 100, 2) 
            : 0;

        return response()->json([
            'financial' => [
                'revenue_growth' => [
                    'value' => $revenueGrowth,
                    'target' => 10,
                    'status' => $this->getStatus($revenueGrowth, 10)
                ],
                'profit_margin' => [
                    'value' => $profitMargin,
                    'target' => 25,
                    'status' => $this->getStatus($profitMargin, 25)
                ]
            ],
            'operations' => [
                'order_fulfillment_rate' => [
                    'value' => $orderFulfillmentRate,
                    'target' => 95,
                    'status' => $this->getStatus($orderFulfillmentRate, 95)
                ],
                'on_time_delivery' => [
                    'value' => $onTimeDeliveryRate,
                    'target' => 95,
                    'status' => $this->getStatus($onTimeDeliveryRate, 95)
                ]
            ],
            'production' => [
                'production_achievement' => [
                    'value' => $productionAchievement,
                    'target' => 98,
                    'status' => $this->getStatus($productionAchievement, 98)
                ]
            ],
            'supply_chain' => [
                'stock_availability' => [
                    'value' => $stockAvailability,
                    'target' => 95,
                    'status' => $this->getStatus($stockAvailability, 95)
                ]
            ]
        ]);
    }

    /**
     * Chart 8.2: Key Metrics Trend - Multi-Line Chart
     */
    public function keyMetricsTrend(): JsonResponse
    {
        $months = collect(range(11, 0))->map(function ($monthsAgo) {
            return now()->subMonths($monthsAgo)->format('Y-m');
        });

        $baseMonth = $months->first();

        $data = $months->map(function ($month) use ($baseMonth) {
            $year = substr($month, 0, 4);
            $monthNum = substr($month, 5, 2);

            // Revenue
            $revenue = SoInvoiceLine::whereYear('invoice_date', $year)
                ->whereMonth('invoice_date', $monthNum)
                ->sum('amount_hc');

            // Orders
            $orders = SoInvoiceLine::whereYear('invoice_date', $year)
                ->whereMonth('invoice_date', $monthNum)
                ->distinct('sales_order')
                ->count('sales_order');

            // Production Volume
            $production = ProdHeader::whereYear('planning_date', $year)
                ->whereMonth('planning_date', $monthNum)
                ->sum('qty_order');

            // Shipments
            $shipments = SoInvoiceLine::whereYear('invoice_date', $year)
                ->whereMonth('invoice_date', $monthNum)
                ->distinct('shipment')
                ->count('shipment');

            // Get base values for indexing
            $baseRevenue = SoInvoiceLine::where('invoice_date', 'like', $baseMonth . '%')->sum('amount_hc');
            $baseOrders = SoInvoiceLine::where('invoice_date', 'like', $baseMonth . '%')->distinct('sales_order')->count('sales_order');
            $baseProduction = ProdHeader::where('planning_date', 'like', $baseMonth . '%')->sum('qty_order');
            $baseShipments = SoInvoiceLine::where('invoice_date', 'like', $baseMonth . '%')->distinct('shipment')->count('shipment');

            return [
                'period' => $month,
                'revenue_index' => $baseRevenue > 0 ? round(($revenue / $baseRevenue) * 100, 2) : 100,
                'orders_index' => $baseOrders > 0 ? round(($orders / $baseOrders) * 100, 2) : 100,
                'production_index' => $baseProduction > 0 ? round(($production / $baseProduction) * 100, 2) : 100,
                'shipments_index' => $baseShipments > 0 ? round(($shipments / $baseShipments) * 100, 2) : 100
            ];
        });

        return response()->json($data);
    }

    /**
     * Chart 8.3: Inventory Health Summary - Bullet Chart
     */
    public function inventoryHealthSummary(): JsonResponse
    {
        // Stock Coverage Days (simplified calculation)
        $totalOnhand = StockByWh::sum('onhand');
        $avgDailyDemand = SoInvoiceLine::sum('delivered_qty') / 365;
        $stockCoverageDays = $avgDailyDemand > 0 
            ? round($totalOnhand / $avgDailyDemand, 2) 
            : 0;

        // Stock Value Efficiency
        $currentInventoryValue = $totalOnhand * 1000; // Simplified
        $targetInventoryValue = $currentInventoryValue * 0.9;

        // Stockout Rate
        $totalItems = StockByWh::distinct('partno')->count('partno');
        $itemsBelowSafety = StockByWh::whereRaw('onhand < safety_stock')
            ->distinct('partno')
            ->count('partno');
        $stockoutRate = $totalItems > 0 
            ? round(($itemsBelowSafety / $totalItems) * 100, 2) 
            : 0;

        return response()->json([
            'stock_coverage_days' => [
                'actual' => $stockCoverageDays,
                'target' => 45,
                'good_range' => [30, 60],
                'warning_range' => [15, 90],
                'status' => $this->getBulletStatus($stockCoverageDays, 45, [30, 60])
            ],
            'stock_value_efficiency' => [
                'actual' => $currentInventoryValue,
                'target' => $targetInventoryValue,
                'status' => $currentInventoryValue <= $targetInventoryValue ? 'green' : 'yellow'
            ],
            'stockout_rate' => [
                'actual' => $stockoutRate,
                'target' => 5,
                'good_threshold' => 5,
                'warning_threshold' => 10,
                'status' => $stockoutRate < 5 ? 'green' : ($stockoutRate < 10 ? 'yellow' : 'red')
            ]
        ]);
    }

    /**
     * Chart 8.4: Production Performance - Gauge Chart
     */
    public function productionPerformance(): JsonResponse
    {
        // Production Completion Rate
        $totalQtyOrder = ProdHeader::sum('qty_order');
        $totalQtyDelivery = ProdHeader::sum('qty_delivery');
        $completionRate = $totalQtyOrder > 0 
            ? round(($totalQtyDelivery / $totalQtyOrder) * 100, 2) 
            : 0;

        // On-Time Production Rate (simplified)
        $totalProduction = ProdHeader::count();
        $onTimeProduction = ProdHeader::where('status', 'Completed')->count();
        $onTimeRate = $totalProduction > 0 
            ? round(($onTimeProduction / $totalProduction) * 100, 2) 
            : 0;

        return response()->json([
            'production_completion_rate' => [
                'value' => $completionRate,
                'target' => 98,
                'zones' => [
                    ['min' => 0, 'max' => 80, 'color' => 'red'],
                    ['min' => 80, 'max' => 95, 'color' => 'yellow'],
                    ['min' => 95, 'max' => 100, 'color' => 'green']
                ]
            ],
            'on_time_production_rate' => [
                'value' => $onTimeRate,
                'target' => 95,
                'zones' => [
                    ['min' => 0, 'max' => 80, 'color' => 'red'],
                    ['min' => 80, 'max' => 95, 'color' => 'yellow'],
                    ['min' => 95, 'max' => 100, 'color' => 'green']
                ]
            ]
        ]);
    }

    /**
     * Chart 8.5: Sales Performance - Speedometer Gauge
     */
    public function salesPerformance(): JsonResponse
    {
        $actualRevenue = SoInvoiceLine::whereYear('invoice_date', now()->year)
            ->sum('amount_hc');
        
        $targetRevenue = $actualRevenue * 1.2; // Example: 20% growth target
        
        $achievement = $targetRevenue > 0 
            ? round(($actualRevenue / $targetRevenue) * 100, 2) 
            : 0;

        $ytdAchievement = $achievement;
        $remainingTarget = $targetRevenue - $actualRevenue;
        $daysToEnd = now()->endOfYear()->diffInDays(now());
        $requiredDailyRunRate = $daysToEnd > 0 
            ? round($remainingTarget / $daysToEnd, 2) 
            : 0;

        return response()->json([
            'sales_achievement' => $achievement,
            'target' => 100,
            'ytd_achievement' => $ytdAchievement,
            'remaining_target' => $remainingTarget,
            'days_to_period_end' => $daysToEnd,
            'required_daily_run_rate' => $requiredDailyRunRate,
            'zones' => [
                ['min' => 0, 'max' => 70, 'color' => 'red'],
                ['min' => 70, 'max' => 90, 'color' => 'yellow'],
                ['min' => 90, 'max' => 100, 'color' => 'light_green'],
                ['min' => 100, 'max' => 120, 'color' => 'dark_green'],
                ['min' => 120, 'max' => 150, 'color' => 'blue']
            ]
        ]);
    }

    /**
     * Chart 8.6: Operational Efficiency - Radar Chart
     */
    public function operationalEfficiency(): JsonResponse
    {
        // Production Efficiency
        $productionEfficiency = ProdHeader::sum('qty_order') > 0 
            ? round((ProdHeader::sum('qty_delivery') / ProdHeader::sum('qty_order')) * 100, 2) 
            : 0;

        // Delivery Performance
        $deliveryPerformance = SoInvoiceLine::whereNotNull('receipt_date')->count() > 0 
            ? round((SoInvoiceLine::whereRaw('receipt_date <= delivery_date')->count() / SoInvoiceLine::whereNotNull('receipt_date')->count()) * 100, 2) 
            : 0;

        // Stock Management (normalized inventory turnover)
        $stockManagement = 75; // Simplified

        // Procurement Efficiency
        $procurementEfficiency = ReceiptPurchase::sum('request_qty') > 0 
            ? round((ReceiptPurchase::sum('actual_receipt_qty') / ReceiptPurchase::sum('request_qty')) * 100, 2) 
            : 0;

        // Order Fulfillment
        $orderFulfillment = WarehouseOrder::count() > 0 
            ? round((WarehouseOrder::where('status', 'Completed')->count() / WarehouseOrder::count()) * 100, 2) 
            : 0;

        // Quality (Approval Rate)
        $quality = ReceiptPurchase::sum('actual_receipt_qty') > 0 
            ? round((ReceiptPurchase::sum('approve_qty') / ReceiptPurchase::sum('actual_receipt_qty')) * 100, 2) 
            : 0;

        return response()->json([
            'actual' => [
                'production_efficiency' => $productionEfficiency,
                'delivery_performance' => $deliveryPerformance,
                'stock_management' => $stockManagement,
                'procurement_efficiency' => $procurementEfficiency,
                'order_fulfillment' => $orderFulfillment,
                'quality' => $quality
            ],
            'target' => [
                'production_efficiency' => 95,
                'delivery_performance' => 95,
                'stock_management' => 80,
                'procurement_efficiency' => 98,
                'order_fulfillment' => 95,
                'quality' => 95
            ]
        ]);
    }

    /**
     * Chart 8.7: Financial Summary - Combo Chart
     */
    public function financialSummary(): JsonResponse
    {
        $months = collect(range(11, 0))->map(function ($monthsAgo) {
            return now()->subMonths($monthsAgo)->format('Y-m');
        });

        $data = $months->map(function ($month) {
            $year = substr($month, 0, 4);
            $monthNum = substr($month, 5, 2);

            $revenue = SoInvoiceLine::whereYear('invoice_date', $year)
                ->whereMonth('invoice_date', $monthNum)
                ->sum('amount_hc');

            $cost = ReceiptPurchase::whereYear('actual_receipt_date', $year)
                ->whereMonth('actual_receipt_date', $monthNum)
                ->sum('receipt_amount');

            $grossMarginPercent = $revenue > 0 
                ? round((($revenue - $cost) / $revenue) * 100, 2) 
                : 0;

            return [
                'period' => $month,
                'revenue' => $revenue,
                'cost' => $cost,
                'gross_margin_percent' => $grossMarginPercent
            ];
        });

        return response()->json([
            'data' => $data,
            'target_margin' => 25
        ]);
    }

    /**
     * Chart 8.8: Critical Alerts & Actions - Alert List
     */
    public function criticalAlertsActions(): JsonResponse
    {
        $alerts = [];

        // Inventory Alerts
        $criticalStock = StockByWh::whereRaw('onhand < min_stock')
            ->limit(5)
            ->get();
        
        foreach ($criticalStock as $item) {
            $alerts[] = [
                'priority' => 'critical',
                'category' => 'Inventory',
                'description' => "Critical stock level for {$item->partno}",
                'metric_value' => $item->onhand,
                'threshold' => $item->min_stock,
                'status' => 'critical',
                'action_required' => 'Reorder immediately',
                'owner' => 'Inventory Manager'
            ];
        }

        // Production Alerts
        $delayedProduction = ProdHeader::whereRaw('qty_delivery < qty_order')
            ->where('status', '<>', 'Completed')
            ->limit(5)
            ->get();
        
        foreach ($delayedProduction as $prod) {
            $alerts[] = [
                'priority' => 'warning',
                'category' => 'Production',
                'description' => "Production order {$prod->prod_no} behind schedule",
                'metric_value' => $prod->qty_delivery,
                'threshold' => $prod->qty_order,
                'status' => 'warning',
                'action_required' => 'Review production schedule',
                'owner' => 'Production Manager'
            ];
        }

        // Finance Alerts
        $overdueAr = SoInvoiceLine::where('invoice_status', '<>', 'Paid')
            ->whereRaw('DATEDIFF(CURDATE(), invoice_date) > 90')
            ->limit(5)
            ->get();
        
        foreach ($overdueAr as $invoice) {
            $alerts[] = [
                'priority' => 'critical',
                'category' => 'Finance',
                'description' => "Invoice {$invoice->invoice_no} overdue >90 days",
                'metric_value' => $invoice->amount_hc,
                'threshold' => 90,
                'status' => 'critical',
                'action_required' => 'Follow up with customer',
                'owner' => 'Finance Manager'
            ];
        }

        // Sort by priority
        usort($alerts, function ($a, $b) {
            $priority = ['critical' => 1, 'warning' => 2, 'info' => 3];
            return $priority[$a['priority']] <=> $priority[$b['priority']];
        });

        return response()->json(array_slice($alerts, 0, 20));
    }

    /**
     * Chart 8.9: Department Performance Comparison - Grouped Bar Chart
     */
    public function departmentPerformanceComparison(): JsonResponse
    {
        $productionActual = ProdHeader::sum('qty_order') > 0 
            ? round((ProdHeader::sum('qty_delivery') / ProdHeader::sum('qty_order')) * 100, 2) 
            : 0;

        $warehouseActual = WarehouseOrder::count() > 0 
            ? round((WarehouseOrder::where('status', 'Completed')->count() / WarehouseOrder::count()) * 100, 2) 
            : 0;

        $salesActual = 85; // Simplified

        $procurementActual = ReceiptPurchase::sum('request_qty') > 0 
            ? round((ReceiptPurchase::sum('actual_receipt_qty') / ReceiptPurchase::sum('request_qty')) * 100, 2) 
            : 0;

        $financeActual = 80; // Simplified

        return response()->json([
            [
                'department' => 'Production',
                'actual' => $productionActual,
                'target' => 98,
                'variance' => $productionActual - 98,
                'status' => $this->getStatus($productionActual, 98)
            ],
            [
                'department' => 'Warehouse',
                'actual' => $warehouseActual,
                'target' => 95,
                'variance' => $warehouseActual - 95,
                'status' => $this->getStatus($warehouseActual, 95)
            ],
            [
                'department' => 'Sales',
                'actual' => $salesActual,
                'target' => 100,
                'variance' => $salesActual - 100,
                'status' => $this->getStatus($salesActual, 100)
            ],
            [
                'department' => 'Procurement',
                'actual' => $procurementActual,
                'target' => 98,
                'variance' => $procurementActual - 98,
                'status' => $this->getStatus($procurementActual, 98)
            ],
            [
                'department' => 'Finance',
                'actual' => $financeActual,
                'target' => 95,
                'variance' => $financeActual - 95,
                'status' => $this->getStatus($financeActual, 95)
            ]
        ]);
    }

    /**
     * Chart 8.10: Monthly Business Overview - Summary Table
     */
    public function monthlyBusinessOverview(): JsonResponse
    {
        $currentMonth = now()->format('Y-m');
        $lastMonth = now()->subMonth()->format('Y-m');

        $metrics = [
            'revenue' => [
                'current' => SoInvoiceLine::where('invoice_date', 'like', $currentMonth . '%')->sum('amount_hc'),
                'last' => SoInvoiceLine::where('invoice_date', 'like', $lastMonth . '%')->sum('amount_hc')
            ],
            'orders' => [
                'current' => SoInvoiceLine::where('invoice_date', 'like', $currentMonth . '%')->distinct('sales_order')->count(),
                'last' => SoInvoiceLine::where('invoice_date', 'like', $lastMonth . '%')->distinct('sales_order')->count()
            ],
            'production_output' => [
                'current' => ProdHeader::where('planning_date', 'like', $currentMonth . '%')->sum('qty_delivery'),
                'last' => ProdHeader::where('planning_date', 'like', $lastMonth . '%')->sum('qty_delivery')
            ],
            'shipments' => [
                'current' => SoInvoiceLine::where('invoice_date', 'like', $currentMonth . '%')->distinct('shipment')->count(),
                'last' => SoInvoiceLine::where('invoice_date', 'like', $lastMonth . '%')->distinct('shipment')->count()
            ]
        ];

        $data = [];
        foreach ($metrics as $name => $values) {
            $variance = $values['current'] - $values['last'];
            $variancePercent = $values['last'] > 0 
                ? round(($variance / $values['last']) * 100, 2) 
                : 0;

            $data[] = [
                'metric' => ucfirst(str_replace('_', ' ', $name)),
                'current_month' => $values['current'],
                'last_month' => $values['last'],
                'variance' => $variance,
                'variance_percent' => $variancePercent,
                'status' => $variance >= 0 ? 'green' : 'red'
            ];
        }

        return response()->json($data);
    }

    /**
     * Get all dashboard 8 data in one call
     */
    public function getAllData(Request $request): JsonResponse
    {
        return response()->json([
            'overall_business_health' => $this->overallBusinessHealth()->getData(true),
            'key_metrics_trend' => $this->keyMetricsTrend()->getData(true),
            'inventory_health_summary' => $this->inventoryHealthSummary()->getData(true),
            'production_performance' => $this->productionPerformance()->getData(true),
            'sales_performance' => $this->salesPerformance()->getData(true),
            'operational_efficiency' => $this->operationalEfficiency()->getData(true),
            'financial_summary' => $this->financialSummary()->getData(true),
            'critical_alerts_actions' => $this->criticalAlertsActions()->getData(true),
            'department_performance_comparison' => $this->departmentPerformanceComparison()->getData(true),
            'monthly_business_overview' => $this->monthlyBusinessOverview()->getData(true)
        ]);
    }

    /**
     * Helper function to get status based on actual vs target
     */
    private function getStatus($actual, $target)
    {
        $percentage = ($actual / $target) * 100;
        
        if ($percentage >= 100) {
            return 'green';
        } elseif ($percentage >= 90) {
            return 'yellow';
        } else {
            return 'red';
        }
    }

    /**
     * Helper function to get bullet chart status
     */
    private function getBulletStatus($actual, $target, $goodRange)
    {
        if ($actual >= $goodRange[0] && $actual <= $goodRange[1]) {
            return 'green';
        } elseif ($actual >= $goodRange[0] * 0.5 && $actual <= $goodRange[1] * 1.5) {
            return 'yellow';
        } else {
            return 'red';
        }
    }
}
