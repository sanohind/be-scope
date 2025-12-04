<?php

namespace App\Console\Commands;

use App\Models\StockByWh;
use App\Models\StockByWhSnapshot;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class RecordStockByWhSnapshot extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stock:snapshot {--date=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Record daily snapshot of stock by warehouse data';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $snapshotDate = $this->option('date')
            ? Carbon::parse($this->option('date'))->toDateString()
            : now()->toDateString();

        $this->info("Starting stock snapshot for {$snapshotDate}");

        try {
            DB::transaction(function () use ($snapshotDate) {
                StockByWhSnapshot::whereDate('snapshot_date', $snapshotDate)->delete();

                $chunkSize = 1000;
                $inserted = 0;

                StockByWh::query()
                    ->orderBy('warehouse')
                    ->chunk($chunkSize, function ($records) use ($snapshotDate, &$inserted) {
                        $payload = $records->map(function (StockByWh $record) use ($snapshotDate) {
                            return [
                                'snapshot_date' => $snapshotDate,
                                'warehouse' => $record->warehouse,
                                'partno' => $record->partno,
                                'desc' => $record->desc,
                                'partname' => $record->partname,
                                'oldpartno' => $record->oldpartno,
                                'group' => $record->group,
                                'groupkey' => $record->groupkey,
                                'product_type' => $record->product_type,
                                'model' => $record->model,
                                'customer' => $record->customer,
                                'onhand' => $record->onhand,
                                'allocated' => $record->allocated,
                                'onorder' => $record->onorder,
                                'economicstock' => $record->economicstock,
                                'safety_stock' => $record->safety_stock,
                                'min_stock' => $record->min_stock,
                                'max_stock' => $record->max_stock,
                                'unit' => $record->unit,
                                'location' => $record->location,
                                'group_type' => $record->group_type,
                                'group_type_desc' => $record->group_type_desc,
                                'created_at' => now(),
                            ];
                        })->toArray();

                        if (!empty($payload)) {
                            StockByWhSnapshot::insert($payload);
                            $inserted += count($payload);
                        }
                    });

                $this->info("Finished snapshot. Inserted {$inserted} rows.");
            });
        } catch (Throwable $exception) {
            $this->error("Failed to record snapshot: {$exception->getMessage()}");

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}


