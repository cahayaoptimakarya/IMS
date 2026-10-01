<?php

namespace App\Exports\PergerakanBarang;

use App\Support\ItemMovementAnalyzer as Analyzer;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class NotesSheet extends BaseSheet
{
    public function title(): string
    {
        return 'Keterangan';
    }

    protected function build(Worksheet $sheet): void
    {
        $this->writeTitle($sheet, 'KETERANGAN & METODOLOGI', 'B');
        $sheet->getColumnDimension('A')->setWidth(26);
        $sheet->getColumnDimension('B')->setWidth(100);

        $fast = (int) Analyzer::FAST_THRESHOLD;
        $medium = (int) Analyzer::MEDIUM_THRESHOLD;
        $notes = [
            'Sumber Qty Keluar' => 'Barang yang dikirim atas Permintaan Barang (surat jalan) dengan gudang asal = gudang laporan, berdasarkan tanggal kirim. Shipment yang dibatalkan tidak dihitung. Pengiriman dari PO (stock in order) tidak termasuk.',
            'Fast Moving' => "Item teratas (diurutkan qty keluar terbesar) yang membentuk lapisan kontribusi kumulatif 0 - {$fast}% dari total qty keluar.",
            'Medium Moving' => "Item pada lapisan kontribusi kumulatif {$fast} - {$medium}%.",
            'Slow Moving' => "Item pada lapisan kontribusi kumulatif di atas {$medium}%.",
            'Non Moving' => 'Item yang memiliki stok di gudang tetapi tidak ada qty keluar pada periode.',
            'Batas Lapisan' => 'Item yang kontribusinya melintasi batas lapisan dimasukkan ke lapisan tempat kontribusinya dimulai, sehingga item teratas selalu Fast Moving.',
            'Kontribusi (%)' => 'Qty keluar item / total qty keluar seluruh item x 100. Kumulatif (%) = penjumlahan kontribusi dari peringkat 1 sampai item tersebut.',
            'Rata-rata / Hari' => 'Qty keluar / jumlah hari periode (hari setelah hari ini tidak dihitung).',
            'Frequency (Dok)' => 'Jumlah dokumen Permintaan Barang berbeda yang dikirim. Satu permintaan yang dikirim bertahap dihitung 1.',
            'Days Cover' => 'Stok saat ini / rata-rata keluar per hari = perkiraan berapa hari stok akan bertahan. Kosong untuk item Non Moving.',
            'Status Days Cover' => sprintf(
                'Kritis < %d hari; Waspada %d - %d hari; Aman %d - %d hari; Berlebih > %d hari.',
                Analyzer::COVER_CRITICAL_DAYS, Analyzer::COVER_CRITICAL_DAYS, Analyzer::COVER_WARNING_DAYS,
                Analyzer::COVER_WARNING_DAYS, Analyzer::COVER_EXCESS_DAYS, Analyzer::COVER_EXCESS_DAYS
            ),
            'Days Cover Needed' => 'Jumlah hari ke depan yang ingin dicukupi stoknya (diinput saat export, saat ini ' . $this->report->daysNeededLabel() . ' hari).',
            'Dalam Order' => 'Qty Stock In Order ke gudang laporan berstatus Requested / On Shipping yang belum diterima (sisa qty).',
            'Rekomendasi Order' => 'Kebutuhan = (rata-rata keluar per hari x days cover needed) - stok saat ini - qty dalam order. Jika kebutuhan > 0, dibulatkan ke atas menjadi koli penuh (isi per koli dari master item); Qty = koli x isi per koli. Item Non Moving tidak direkomendasikan.',
            'Stok' => 'Stok saat file dibuat pada gudang laporan (bukan stok pada akhir periode).',
            'Terakhir Keluar' => 'Tanggal kirim terakhir hingga akhir periode, termasuk sebelum periode, agar item Non Moving tetap terlihat kapan terakhir keluar.',
            'Satuan Qty' => 'Qty dijumlahkan dalam satuan dasar masing-masing item; kontribusi dihitung dari qty tanpa konversi satuan.',
        ];

        $this->writeHeader($sheet, 4, ['Istilah', 'Penjelasan']);
        $row = 5;
        foreach ($notes as $term => $text) {
            $sheet->setCellValue("A{$row}", $term);
            $sheet->setCellValue("B{$row}", $text);
            $row++;
        }
        $this->borderTable($sheet, 'A4:B' . ($row - 1));
        $sheet->getStyle('A5:A' . ($row - 1))->getFont()->setBold(true);
        $sheet->getStyle('B5:B' . ($row - 1))->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
    }
}
