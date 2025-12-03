<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WarehouseStockSummary;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DailyStockController extends Controller
{
    // Warehouse mapping by category
    private $warehouseCategories = [
        'RM' => ['WHRM01', 'WHRM02', 'WHMT01'],
        'FG' => ['WHFG01', 'WHFG02'],
    ];

    private $allWarehouses = ['WHMT01', 'WHRM01', 'WHRM02', 'WHFG01', 'WHFG02'];

    public function index(Request $request)
    {
        $validated = $request->validate([
            'warehouse' => [
                'nullable',
                'string',
                Rule::in(array_merge($this->allWarehouses, array_keys($this->warehouseCategories))),
            ],
            // Use unified date parameters: date_from and date_to
            'date_from' => 'nullable|date_format:Y-m-d',
            'date_to' => 'nullable|date_format:Y-m-d',
        ]);

        // Determine which warehouses to query
        $warehousesToQuery = $this->allWarehouses;

        if (!empty($validated['warehouse'])) {
            $warehouse = $validated['warehouse'];

            // If it's a category (RM or FG), get warehouses in that category
            if (in_array($warehouse, array_keys($this->warehouseCategories))) {
                $warehousesToQuery = $this->warehouseCategories[$warehouse];
            } else {
                // Otherwise, it's a specific warehouse code
                $warehousesToQuery = [$warehouse];
            }
        }

        // Get latest data for each period_start and warehouse combination
        $query = WarehouseStockSummary::query()
            ->whereIn('warehouse', $warehousesToQuery);

        // Apply date filters if provided (using unified date_from / date_to)
        if (!empty($validated['date_from'])) {
            $query->where('period_start', '>=', $validated['date_from'] . ' 00:00:00');
        }

        if (!empty($validated['date_to'])) {
            $query->where('period_end', '<=', $validated['date_to'] . ' 23:59:59');
        }

        $records = $query
            ->orderBy('warehouse')
            ->orderBy('period_start', 'desc')
            ->get()
            ->groupBy(function ($item) {
                // Group by warehouse and period_start date
                return $item->warehouse . '|' . $item->period_start->toDateString();
            })
            ->map(function ($group) {
                // Get the latest record (first one due to desc ordering by period_start)
                return $group->first();
            })
            ->values()
            ->sortBy(function ($item) {
                return [$item->warehouse, $item->period_start];
            })
            ->values()
            ->map(function (WarehouseStockSummary $summary) {
                return [
                    'period_start' => optional($summary->period_start)->toDateTimeString(),
                    'period_end' => optional($summary->period_end)->toDateTimeString(),
                    'granularity' => $summary->granularity,
                    'warehouse' => $summary->warehouse,
                    'onhand' => (int) $summary->onhand_total,
                    'receipt' => (int) $summary->receipt_total,
                    'issue' => (int) $summary->issue_total,
                ];
            });

        $warehouses = $records
            ->groupBy('warehouse')
            ->map(function ($items) {
                return [
                    'data' => $items->values()->all(),
                ];
            });

        return response()->json([
            'meta' => [
                'warehouse_filter' => $validated['warehouse'] ?? 'all',
                'warehouses_queried' => $warehousesToQuery,
                // Expose unified date filters in the response meta
                'date_from_filter' => $validated['date_from'] ?? null,
                'date_to_filter' => $validated['date_to'] ?? null,
                'total_records' => $records->count(),
            ],
            'warehouses' => $warehouses,
        ]);
    }
}

