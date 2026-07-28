<?php

namespace App\Support;

use App\Models\Inventory;
use App\Models\Item;
use App\Models\StockApiSyncRecord;
use App\Models\Warehouse;
use Illuminate\Support\Carbon;

class StockApiSyncService
{
    public static function syncItem(int $itemId, ?Carbon $changedAt = null): void
    {
        $item = Item::with(['uom', 'itemCategory'])->find($itemId);
        if (! $item) return;
        $warehouseId = (int) Warehouse::where('name', config('stock_api.source_warehouse_name'))->value('id');
        if ($warehouseId <= 0) return;

        $attributes = [
            'item_id' => $item->id,
            'sku' => $item->sku,
            'name' => $item->nama_barang,
            'category' => $item->itemCategory?->name,
            'uom' => $item->uom?->symbol ?: ($item->uom?->name ?: 'PCS'),
            'qty' => max(0, (float) Inventory::where('item_id', $item->id)->where('warehouse_id', $warehouseId)->sum('quantity')),
            'min_qty' => (float) $item->low_stock_threshold ?: null,
            'status' => $item->is_active ? 'active' : 'inactive',
            'source_updated_at' => $changedAt ?? now(),
        ];
        $record = StockApiSyncRecord::where(fn ($q) => $q->where('item_id', $item->id)->orWhere('sku', $item->sku))->first();
        $record ? $record->fill($attributes)->save() : StockApiSyncRecord::create($attributes);
    }

    public static function markDeleted(Item $item, ?Carbon $changedAt = null): void
    {
        StockApiSyncRecord::where('item_id', $item->id)->each(fn (StockApiSyncRecord $record) => $record->update([
            'item_id' => null, 'qty' => 0, 'min_qty' => null, 'status' => 'deleted', 'source_updated_at' => $changedAt ?? now(),
        ]));
    }
}
