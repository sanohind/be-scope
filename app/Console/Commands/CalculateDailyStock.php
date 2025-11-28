<?php

namespace App\Console\Commands;

use App\Models\InventoryTransaction;
use App\Models\StockByWh;
use App\Models\WarehouseStockSummary;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Throwable;

class CalculateDailyStock extends Command
{
    protected $signature = 'daily-stock:calculate {date? : Target date (Y-m-d or Y-m-d H:i) depending on granularity} {--warehouse=} {--granularity=}';

    protected $description = 'Calculate daily stock summary per warehouse.';

    public function handle(): int
    {
        $granularity = $this->resolveGranularity($this->option('granularity'));

        try {
            $targetDate = $this->resolveTargetDate($this->argument('date'));
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return Command::FAILURE;
        }

        $allowedWarehouses = $this->getAllowedWarehouses();
        $warehouseFilter = $this->option('warehouse');

        if ($warehouseFilter && ! in_array($warehouseFilter, $allowedWarehouses, true)) {
            $this->error(sprintf(
                'Warehouse %s tidak termasuk daftar yang diperbolehkan (%s).',
                $warehouseFilter,
                implode(', ', $allowedWarehouses)
            ));

            return Command::FAILURE;
        }

        $activeWarehouses = $warehouseFilter ? [$warehouseFilter] : $allowedWarehouses;

        if ($granularity === 'five-minute') {
            $rowsWritten = $this->storeWarehouseSummariesForFiveMinutes($targetDate, $granularity, $activeWarehouses);
            $this->info("Warehouse summary calculation (five-minute) completed. Rows written/updated: {$rowsWritten}");

            return Command::SUCCESS;
        }

        // Daily granularity
        $targetDate = $targetDate->copy()->startOfDay();
        $this->info(sprintf('Calculating daily stock summary for %s', $targetDate->toDateString()));

        $rowsWritten = $this->storeWarehouseSummariesFromDaily($targetDate, $activeWarehouses);

        $this->info("Daily stock calculation completed. Rows written/updated: {$rowsWritten}");

        return Command::SUCCESS;
    }

    private function resolveTargetDate(?string $argument): Carbon
    {
        $date = $argument
            ? Carbon::parse($argument, config('app.timezone'))
            : Carbon::now(config('app.timezone'));

        if ($date->isFuture()) {
            throw new \InvalidArgumentException('Target date cannot be in the future.');
        }

        return $date;
    }

    private function getAllowedWarehouses(): array
    {
        $warehouses = config('daily_stock.allowed_warehouses', []);

        if (empty($warehouses)) {
            throw new \RuntimeException('Daftar warehouse diperbolehkan belum dikonfigurasi.');
        }

        return $warehouses;
    }

    private function resolveGranularity(?string $value): string
    {
        $allowed = config('daily_stock.allowed_granularities', ['daily']);
        // Check environment variable first, then option, then config default
        $granularity = $value
            ?? getenv('DAILY_STOCK_GRANULARITY')
            ?? config('daily_stock.default_granularity', 'daily');

        if (! in_array($granularity, $allowed, true)) {
            throw new \InvalidArgumentException(sprintf(
                'Granularity %s tidak valid. Pilih salah satu dari: %s',
                $granularity,
                implode(', ', $allowed)
            ));
        }

        return $granularity;
    }

    private function storeWarehouseSummariesFromDaily(Carbon $targetDate, array $warehouses): int
    {
        $periodStart = $targetDate->copy()->startOfDay();
        $periodEnd = $targetDate->copy()->endOfDay();
        $previousDate = $targetDate->copy()->subDay();

        // Get transactions for the target date
        $transactionsPerWarehouse = InventoryTransaction::query()
            ->selectRaw("
                warehouse,
                SUM(CASE WHEN LOWER(trans_type) = 'receipt' THEN qty ELSE 0 END) AS receipt_total,
                SUM(CASE WHEN LOWER(trans_type) = 'issue' THEN qty ELSE 0 END) AS issue_total
            ")
            ->whereDate('trans_date', $targetDate->toDateString())
            ->whereIn('warehouse', $warehouses)
            ->groupBy('warehouse')
            ->get()
            ->keyBy('warehouse');

        // Get previous day's onhand from WarehouseStockSummary if exists, otherwise from StockByWh
        $previousSummary = WarehouseStockSummary::query()
            ->where('granularity', 'daily')
            ->whereDate('period_start', $previousDate)
            ->whereIn('warehouse', $warehouses)
            ->get()
            ->keyBy('warehouse');

        // Get current onhand from StockByWh
        $currentOnhandPerWarehouse = StockByWh::query()
            ->selectRaw('warehouse, SUM(onhand) as total_onhand')
            ->whereIn('warehouse', $warehouses)
            ->groupBy('warehouse')
            ->get()
            ->keyBy('warehouse');

        $rows = 0;

        foreach ($warehouses as $warehouse) {
            // Calculate onhand: use previous day's onhand + receipt - issue, or current StockByWh if no previous data
            $previousOnhand = 0;
            if ($previousSummary->has($warehouse)) {
                $prev = $previousSummary->get($warehouse);
                $previousOnhand = (int) $prev->onhand_total;
            } else {
                // Fallback to current StockByWh if no previous summary
                $previousOnhand = (int) optional($currentOnhandPerWarehouse->get($warehouse))->total_onhand;
            }

            $transaction = $transactionsPerWarehouse->get($warehouse);
            $receiptTotal = (int) optional($transaction)->receipt_total;
            $issueTotal = (int) optional($transaction)->issue_total;

            // Calculate today's onhand: previous + receipt - issue
            $onhandToday = $previousOnhand + $receiptTotal - $issueTotal;

            WarehouseStockSummary::updateOrCreate(
                [
                    'warehouse' => $warehouse,
                    'granularity' => 'daily',
                    'period_start' => $periodStart,
                ],
                [
                    'period_end' => $periodEnd,
                    'onhand_total' => $onhandToday,
                    'receipt_total' => $receiptTotal,
                    'issue_total' => $issueTotal,
                ]
            );

            $rows++;
        }

        return $rows;
    }

    private function storeWarehouseSummariesForFiveMinutes(Carbon $targetDate, string $granularity, array $warehouses): int
    {
        $intervalMinutes = config('daily_stock.testing_interval_minutes', 5);
        $periodStart = $targetDate->copy()->setSecond(0);
        $minute = $periodStart->minute - ($periodStart->minute % $intervalMinutes);
        $periodStart->setMinute($minute);
        $periodEnd = $periodStart->copy()->addMinutes($intervalMinutes);

        // Get previous 5-minute period
        $previousPeriodStart = $periodStart->copy()->subMinutes($intervalMinutes);
        $previousPeriodEnd = $periodStart;

        // Get transactions for current 5-minute period
        $transactionsPerWarehouse = InventoryTransaction::query()
            ->selectRaw("
                warehouse,
                SUM(CASE WHEN LOWER(trans_type) = 'receipt' THEN qty ELSE 0 END) AS receipt_total,
                SUM(CASE WHEN LOWER(trans_type) = 'issue' THEN qty ELSE 0 END) AS issue_total
            ")
            ->whereBetween('trans_date', [$periodStart, $periodEnd])
            ->whereIn('warehouse', $warehouses)
            ->groupBy('warehouse')
            ->get()
            ->keyBy('warehouse');

        // Get previous 5-minute period's onhand from WarehouseStockSummary
        $previousSummary = WarehouseStockSummary::query()
            ->where('granularity', $granularity)
            ->where('period_start', $previousPeriodStart)
            ->whereIn('warehouse', $warehouses)
            ->get()
            ->keyBy('warehouse');

        // Get current onhand from StockByWh as fallback if no previous summary
        $currentOnhandPerWarehouse = StockByWh::query()
            ->selectRaw('warehouse, SUM(onhand) as total_onhand')
            ->whereIn('warehouse', $warehouses)
            ->groupBy('warehouse')
            ->get()
            ->keyBy('warehouse');

        $warehousesWithData = $transactionsPerWarehouse->keys()
            ->merge($previousSummary->keys())
            ->merge($currentOnhandPerWarehouse->keys())
            ->unique()
            ->values();

        $rows = 0;

        foreach ($warehousesWithData as $warehouse) {
            // Get onhand from previous 5-minute period, or use StockByWh as baseline
            $previousOnhand = 0;
            if ($previousSummary->has($warehouse)) {
                $prev = $previousSummary->get($warehouse);
                $previousOnhand = (int) $prev->onhand_total;
            } else {
                // Fallback to current StockByWh if no previous summary
                $previousOnhand = (int) optional($currentOnhandPerWarehouse->get($warehouse))->total_onhand;
            }

            $transaction = $transactionsPerWarehouse->get($warehouse);
            $receiptTotal = (int) optional($transaction)->receipt_total;
            $issueTotal = (int) optional($transaction)->issue_total;

            // Calculate current onhand: previous 5-minute onhand + receipt - issue
            $onhandNow = $previousOnhand + $receiptTotal - $issueTotal;

            WarehouseStockSummary::updateOrCreate(
                [
                    'warehouse' => $warehouse,
                    'granularity' => $granularity,
                    'period_start' => $periodStart,
                ],
                [
                    'period_end' => $periodEnd,
                    'onhand_total' => $onhandNow,
                    'receipt_total' => $receiptTotal,
                    'issue_total' => $issueTotal,
                ]
            );

            $rows++;
        }

        return $rows;
    }
}

