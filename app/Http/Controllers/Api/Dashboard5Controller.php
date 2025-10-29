<?php

namespace App\Http\Controllers\Api;

use App\Models\ReceiptPurchase;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class Dashboard5Controller extends ApiController
{
    /**
     * Chart 5.1: Procurement KPI - KPI Cards
     */
    public function procurementKpi(): JsonResponse
    {
        $totalPoValue = ReceiptPurchase::sum('receipt_amount');
        $totalReceipts = ReceiptPurchase::distinct('receipt_no')->count('receipt_no');
        $totalApprovedQty = ReceiptPurchase::sum('approve_qty');
        
        // Note: Pending receipts would require dn_header table
        // For now, using is_confirmed = 0 as proxy
        $pendingReceipts = ReceiptPurchase::where('is_confirmed', 0)->count();
        
        $avgReceiptTime = ReceiptPurchase::selectRaw('AVG(DATEDIFF(actual_receipt_date, actual_receipt_date)) as avg_time')
            ->value('avg_time') ?? 0;
        
        $totalActualQty = ReceiptPurchase::sum('actual_receipt_qty');
        $receiptAccuracyRate = $totalActualQty > 0 
            ? round(($totalApprovedQty / $totalActualQty) * 100, 2) 
            : 0;

        return response()->json([
            'total_po_value' => $totalPoValue,
            'total_receipts' => $totalReceipts,
            'total_approved_qty' => $totalApprovedQty,
            'pending_receipts' => $pendingReceipts,
            'average_receipt_time' => round($avgReceiptTime, 2),
            'receipt_accuracy_rate' => $receiptAccuracyRate
        ]);
    }

    /**
     * Chart 5.2: Receipt Performance - Gauge Chart
     */
    public function receiptPerformance(): JsonResponse
    {
        $totalRequestQty = ReceiptPurchase::sum('request_qty');
        $totalActualQty = ReceiptPurchase::sum('actual_receipt_qty');
        $totalApprovedQty = ReceiptPurchase::sum('approve_qty');

        $receiptFulfillmentRate = $totalRequestQty > 0 
            ? round(($totalActualQty / $totalRequestQty) * 100, 2) 
            : 0;

        $approvalRate = $totalActualQty > 0 
            ? round(($totalApprovedQty / $totalActualQty) * 100, 2) 
            : 0;

        return response()->json([
            'receipt_fulfillment_rate' => [
                'value' => $receiptFulfillmentRate,
                'target' => 98,
                'status' => $receiptFulfillmentRate >= 98 ? 'green' : ($receiptFulfillmentRate >= 90 ? 'yellow' : 'red')
            ],
            'approval_rate' => [
                'value' => $approvalRate,
                'target' => 95,
                'status' => $approvalRate >= 95 ? 'green' : ($approvalRate >= 90 ? 'yellow' : 'red')
            ]
        ]);
    }

    /**
     * Chart 5.3: Top Suppliers by Value - Horizontal Bar Chart
     */
    public function topSuppliersByValue(Request $request): JsonResponse
    {
        $limit = $request->get('limit', 20);

        $data = ReceiptPurchase::select('bp_name')
            ->selectRaw('SUM(receipt_amount) as total_receipt_amount')
            ->selectRaw('COUNT(DISTINCT po_no) as number_of_pos')
            ->selectRaw('ROUND(AVG(receipt_amount), 2) as avg_po_value')
            ->selectRaw('COUNT(DISTINCT receipt_no) as receipt_count')
            ->groupBy('bp_name')
            ->orderBy('total_receipt_amount', 'desc')
            ->limit($limit)
            ->get();

        return response()->json($data);
    }

    /**
     * Chart 5.4: Receipt Trend - Line Chart with Area
     */
    public function receiptTrend(Request $request): JsonResponse
    {
        $groupBy = $request->get('group_by', 'monthly');
        
        $query = ReceiptPurchase::query();

        // Apply filters
        if ($request->has('supplier')) {
            $query->where('bp_name', $request->supplier);
        }
        if ($request->has('item_group')) {
            $query->where('item_group', $request->item_group);
        }
        if ($request->has('date_from')) {
            $query->where('actual_receipt_date', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->where('actual_receipt_date', '<=', $request->date_to);
        }

        // Group by period
        if ($groupBy === 'daily') {
            $dateFormat = "DATE(actual_receipt_date)";
        } elseif ($groupBy === 'weekly') {
            $dateFormat = "DATE_FORMAT(actual_receipt_date, '%Y-W%u')";
        } else {
            $dateFormat = "DATE_FORMAT(actual_receipt_date, '%Y-%m')";
        }

        $data = $query->selectRaw("$dateFormat as period")
            ->selectRaw('SUM(receipt_amount) as receipt_amount')
            ->selectRaw('SUM(actual_receipt_qty) as receipt_qty')
            ->selectRaw('COUNT(DISTINCT receipt_no) as receipt_count')
            ->groupByRaw($dateFormat)
            ->orderByRaw($dateFormat)
            ->get();

        return response()->json($data);
    }

    /**
     * Chart 5.5: Supplier Delivery Performance - Scatter Plot
     */
    public function supplierDeliveryPerformance(): JsonResponse
    {
        $data = ReceiptPurchase::select('bp_name')
            ->selectRaw('AVG(DATEDIFF(actual_receipt_date, actual_receipt_date)) as delivery_time_variance')
            ->selectRaw('ROUND((SUM(approve_qty) / NULLIF(SUM(actual_receipt_qty), 0)) * 100, 2) as accuracy_rate')
            ->selectRaw('SUM(receipt_amount) as total_receipt_value')
            ->selectRaw('COUNT(DISTINCT receipt_no) as receipt_count')
            ->groupBy('bp_name')
            ->having('receipt_count', '>', 0)
            ->get();

        return response()->json($data);
    }

    /**
     * Chart 5.6: Receipt by Item Group - Treemap
     */
    public function receiptByItemGroup(): JsonResponse
    {
        $data = ReceiptPurchase::select([
                'item_group',
                'item_type',
                'item_no',
                'item_desc',
                'receipt_amount',
                'actual_receipt_qty'
            ])
            ->selectRaw('COUNT(DISTINCT bp_name) as supplier_count')
            ->selectRaw('COUNT(DISTINCT receipt_no) as total_receipts')
            ->groupBy('item_group', 'item_type', 'item_no', 'item_desc', 'receipt_amount', 'actual_receipt_qty')
            ->orderBy('receipt_amount', 'desc')
            ->get()
            ->groupBy(['item_group', 'item_type']);

        return response()->json($data);
    }

    /**
     * Chart 5.7: PO vs Invoice Status - Waterfall Chart
     */
    public function poVsInvoiceStatus(): JsonResponse
    {
        $totalPoAmount = ReceiptPurchase::sum('receipt_amount');
        
        $receivedAmount = ReceiptPurchase::whereNotNull('receipt_no')
            ->sum('receipt_amount');
        
        $notYetReceived = $totalPoAmount - $receivedAmount;
        
        $invoicedAmount = ReceiptPurchase::whereNotNull('inv_doc_no')
            ->sum('inv_amount');
        
        $notYetInvoiced = $receivedAmount - $invoicedAmount;
        
        $paidAmount = ReceiptPurchase::whereNotNull('payment_doc')
            ->sum('inv_amount');
        
        $notYetPaid = $invoicedAmount - $paidAmount;

        return response()->json([
            'total_po_amount' => $totalPoAmount,
            'not_yet_received' => $notYetReceived,
            'received_amount' => $receivedAmount,
            'not_yet_invoiced' => $notYetInvoiced,
            'invoiced_amount' => $invoicedAmount,
            'not_yet_paid' => $notYetPaid,
            'paid_amount' => $paidAmount
        ]);
    }

    /**
     * Chart 5.8: Outstanding PO Analysis - Data Table
     */
    public function outstandingPoAnalysis(Request $request): JsonResponse
    {
        $query = ReceiptPurchase::query();

        // Filter for outstanding POs
        $query->where(function($q) {
            $q->where('is_final_receipt', 0)
              ->orWhereRaw('request_qty > actual_receipt_qty');
        });

        $data = $query->select([
                'po_no',
                'bp_name',
                'part_no',
                'item_desc',
                'request_qty',
                'actual_receipt_qty',
                'actual_receipt_date',
                'is_final_receipt'
            ])
            ->selectRaw('(request_qty - actual_receipt_qty) as pending_qty')
            ->selectRaw('DATEDIFF(CURDATE(), actual_receipt_date) as days_outstanding')
            ->orderByRaw('DATEDIFF(CURDATE(), actual_receipt_date) DESC')
            ->get();

        // Add conditional formatting status
        $data = $data->map(function ($item) {
            if ($item->days_outstanding > 30) {
                $item->status = 'red';
            } elseif ($item->days_outstanding >= 15) {
                $item->status = 'yellow';
            } else {
                $item->status = 'green';
            }
            return $item;
        });

        return response()->json($data);
    }

    /**
     * Chart 5.9: Receipt Approval Rate by Supplier - Bar Chart
     */
    public function receiptApprovalRateBySupplier(Request $request): JsonResponse
    {
        $limit = $request->get('limit', 20);

        $data = ReceiptPurchase::select('bp_name')
            ->selectRaw('ROUND((SUM(approve_qty) / NULLIF(SUM(actual_receipt_qty), 0)) * 100, 2) as approval_rate')
            ->selectRaw('COUNT(DISTINCT receipt_no) as total_receipts')
            ->selectRaw('SUM(actual_receipt_qty - approve_qty) as rejected_qty')
            ->groupBy('bp_name')
            ->orderBy('total_receipts', 'desc')
            ->limit($limit)
            ->get();

        // Add color coding
        $data = $data->map(function ($item) {
            if ($item->approval_rate > 95) {
                $item->status = 'green';
            } elseif ($item->approval_rate >= 90) {
                $item->status = 'yellow';
            } else {
                $item->status = 'red';
            }
            return $item;
        });

        return response()->json([
            'data' => $data,
            'target' => 95
        ]);
    }

    /**
     * Chart 5.10: Purchase Price Trend - Line Chart
     */
    public function purchasePriceTrend(Request $request): JsonResponse
    {
        $limit = $request->get('limit', 15);
        
        $query = ReceiptPurchase::query();

        // Apply filters
        if ($request->has('item')) {
            $query->where('item_no', $request->item);
        }
        if ($request->has('supplier')) {
            $query->where('bp_name', $request->supplier);
        }
        if ($request->has('date_from')) {
            $query->where('actual_receipt_date', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->where('actual_receipt_date', '<=', $request->date_to);
        }

        // Get top items by volume
        $topItems = ReceiptPurchase::select('item_no', 'item_desc')
            ->selectRaw('SUM(actual_receipt_qty) as total_qty')
            ->groupBy('item_no', 'item_desc')
            ->orderBy('total_qty', 'desc')
            ->limit($limit)
            ->pluck('item_no');

        $data = $query->whereIn('item_no', $topItems)
            ->select('item_no', 'item_desc', 'actual_receipt_date')
            ->selectRaw('ROUND(AVG(receipt_unit_price), 2) as avg_unit_price')
            ->groupBy('item_no', 'item_desc', 'actual_receipt_date')
            ->orderBy('actual_receipt_date')
            ->get()
            ->groupBy('item_no');

        return response()->json($data);
    }

    /**
     * Chart 5.11: Payment Status Tracking - Stacked Area Chart
     */
    public function paymentStatusTracking(Request $request): JsonResponse
    {
        $query = ReceiptPurchase::query();

        // Apply filters
        if ($request->has('supplier')) {
            $query->where('bp_name', $request->supplier);
        }
        if ($request->has('date_from')) {
            $query->where('inv_doc_date', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->where('inv_doc_date', '<=', $request->date_to);
        }

        $dateFormat = "DATE_FORMAT(inv_doc_date, '%Y-%m')";

        $data = $query->selectRaw("$dateFormat as period")
            ->selectRaw('SUM(CASE WHEN payment_doc IS NULL THEN inv_amount ELSE 0 END) as invoiced_not_paid')
            ->selectRaw('SUM(CASE WHEN payment_doc IS NOT NULL THEN inv_amount ELSE 0 END) as paid')
            ->selectRaw('COUNT(CASE WHEN inv_due_date < CURDATE() AND payment_doc IS NULL THEN 1 END) as overdue_count')
            ->groupByRaw($dateFormat)
            ->orderByRaw($dateFormat)
            ->get();

        return response()->json($data);
    }

    /**
     * Get all dashboard 5 data in one call
     */
    public function getAllData(Request $request): JsonResponse
    {
        return response()->json([
            'procurement_kpi' => $this->procurementKpi()->getData(true),
            'receipt_performance' => $this->receiptPerformance()->getData(true),
            'top_suppliers_by_value' => $this->topSuppliersByValue($request)->getData(true),
            'receipt_trend' => $this->receiptTrend($request)->getData(true),
            'supplier_delivery_performance' => $this->supplierDeliveryPerformance()->getData(true),
            'receipt_by_item_group' => $this->receiptByItemGroup()->getData(true),
            'po_vs_invoice_status' => $this->poVsInvoiceStatus()->getData(true),
            'outstanding_po_analysis' => $this->outstandingPoAnalysis($request)->getData(true),
            'receipt_approval_rate_by_supplier' => $this->receiptApprovalRateBySupplier($request)->getData(true),
            'purchase_price_trend' => $this->purchasePriceTrend($request)->getData(true),
            'payment_status_tracking' => $this->paymentStatusTracking($request)->getData(true)
        ]);
    }
}
