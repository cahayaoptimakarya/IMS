<?php

namespace App\Console\Commands;

use App\Models\Item;
use App\Support\StockApiSyncService;
use Illuminate\Console\Command;

class BackfillStockApiRecords extends Command
{
    protected $signature = 'stock-api:backfill';
    protected $description = 'Mengisi data proyeksi API stok tanpa mengubah saldo atau mutasi';

    public function handle(): int
    {
        Item::query()->orderBy('id')->chunkById(200, function ($items) {
            foreach ($items as $item) StockApiSyncService::syncItem($item->id);
        });
        $this->info('Backfill API stok selesai.');
        return self::SUCCESS;
    }
}
