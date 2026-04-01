<?php

namespace App\Imports;

use App\Models\Inventory;
use App\Models\Item;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class TransferRequestItemsImport implements ToCollection, WithHeadingRow, SkipsEmptyRows
{
    private ?int $warehouseId;
    private array $items = [];
    private array $errors = [];

    public function __construct(?int $warehouseId = null)
    {
        $this->warehouseId = $warehouseId;
    }

    public function collection(Collection $rows): void
    {
        $rowNumber = 1; // heading row

        foreach ($rows as $row) {
            $rowNumber++;

            $sku = $this->cleanString($row['sku'] ?? null);
            $itemId = $this->cleanString($row['item_id'] ?? null);
            $name = $this->cleanString($row['nama_barang'] ?? null);

            $quantityRaw = $row['quantity'] ?? $row['qty'] ?? $row['jumlah'] ?? null;
            $koliRaw = $row['koli'] ?? $row['kol'] ?? null;
            $description = $this->cleanString($row['description'] ?? $row['deskripsi'] ?? $row['keterangan'] ?? null);

            if ($this->isRowEmpty([$sku, $itemId, $name, $quantityRaw, $koliRaw, $description])) {
                continue;
            }

            $item = $this->resolveItem($itemId, $sku, $name);
            if (!$item) {
                $this->errors[] = [
                    'row' => $rowNumber,
                    'message' => 'Item tidak ditemukan. Gunakan kolom item_id atau sku yang valid.',
                ];
                continue;
            }

            if ($this->warehouseId && !$this->itemExistsInWarehouse($item->id, $this->warehouseId)) {
                $this->errors[] = [
                    'row' => $rowNumber,
                    'message' => 'Item tidak tersedia di gudang asal yang dipilih.',
                ];
                continue;
            }

            if ($quantityRaw === null || trim((string) $quantityRaw) === '') {
                $this->errors[] = [
                    'row' => $rowNumber,
                    'message' => 'Kolom quantity/jumlah wajib diisi.',
                ];
                continue;
            }

            $quantity = $this->parseNumber($quantityRaw);
            if ($quantity <= 0) {
                $this->errors[] = [
                    'row' => $rowNumber,
                    'message' => 'Quantity harus lebih dari 0.',
                ];
                continue;
            }

            $koli = $koliRaw === null || trim((string) $koliRaw) === '' ? 0 : $this->parseNumber($koliRaw);
            if ($koli < 0) {
                $this->errors[] = [
                    'row' => $rowNumber,
                    'message' => 'Koli tidak boleh negatif.',
                ];
                continue;
            }

            $key = (string) $item->id;
            if (!isset($this->items[$key])) {
                $this->items[$key] = [
                    'item_id' => $item->id,
                    'quantity' => 0,
                    'koli' => 0,
                    'description' => '',
                ];
            }

            $this->items[$key]['quantity'] += $quantity;
            $this->items[$key]['koli'] += $koli;
            if ($description) {
                $existing = $this->items[$key]['description'];
                $this->items[$key]['description'] = $existing
                    ? trim($existing . '; ' . $description)
                    : $description;
            }
        }
    }

    public function getItems(): array
    {
        return array_values($this->items);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function hasErrors(): bool
    {
        return count($this->errors) > 0;
    }

    private function resolveItem(?string $itemId, ?string $sku, ?string $name): ?Item
    {
        if ($itemId !== null && $itemId !== '') {
            $item = Item::find($itemId);
            if ($item) {
                return $item;
            }
        }

        if ($sku !== null && $sku !== '') {
            $item = Item::where('sku', $sku)->first();
            if ($item) {
                return $item;
            }
        }

        if ($name !== null && $name !== '') {
            return Item::where('nama_barang', $name)->first();
        }

        return null;
    }

    private function itemExistsInWarehouse(int $itemId, int $warehouseId): bool
    {
        return Inventory::where('warehouse_id', $warehouseId)
            ->where('item_id', $itemId)
            ->exists();
    }

    private function parseNumber($value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        $stringValue = trim((string) $value);
        if ($stringValue === '') {
            return 0;
        }

        if (str_contains($stringValue, ',') && str_contains($stringValue, '.')) {
            $stringValue = str_replace('.', '', $stringValue);
            $stringValue = str_replace(',', '.', $stringValue);
        } elseif (str_contains($stringValue, ',')) {
            $stringValue = str_replace(',', '.', $stringValue);
        }

        return is_numeric($stringValue) ? (float) $stringValue : 0;
    }

    private function cleanString($value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    private function isRowEmpty(array $values): bool
    {
        foreach ($values as $value) {
            if ($value !== null && $value !== '' && $value !== 0 && $value !== '0') {
                return false;
            }
        }
        return true;
    }
}
