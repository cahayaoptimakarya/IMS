<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Item extends Model
{
    use HasFactory;

    protected $fillable = [
        'koli',
        'sku',
        'uom_id',
        'item_type_id',
        'nama_barang',
        'tempat',
        'deskripsi',
        'item_category_id',
        'low_stock_threshold',
        'lead_time_po_days',
        'lead_time_safety_days',
        'is_active',
        'panjang_koli',
        'lebar_koli',
        'tinggi_koli',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'panjang_koli' => 'float',
            'lebar_koli' => 'float',
            'tinggi_koli' => 'float',
        ];
    }

    /**
     * CBM (m³) per koli dari dimensi P x L x T (cm). Null jika dimensi belum lengkap.
     */
    public function getCbmPerKoliAttribute(): ?float
    {
        if (!$this->panjang_koli || !$this->lebar_koli || !$this->tinggi_koli) {
            return null;
        }

        return round($this->panjang_koli * $this->lebar_koli * $this->tinggi_koli / 1_000_000, 6);
    }

    /**
     * CBM (m³) per satuan (UoM) = CBM per koli / isi per koli.
     */
    public function getCbmPerUnitAttribute(): ?float
    {
        $cbmPerKoli = $this->cbm_per_koli;
        if ($cbmPerKoli === null || (int) $this->koli <= 0) {
            return null;
        }

        return round($cbmPerKoli / (int) $this->koli, 8);
    }


    public function uom()
    {
        return $this->belongsTo(Uom::class);
    }

    public function itemCategory()
    {
        return $this->belongsTo(ItemCategory::class);
    }

    public function itemType()
    {
        return $this->belongsTo(ItemType::class);
    }
}
