<?php

namespace App\Exports;

use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Export .xlsx SELURUH listing+varian satu toko. parent_sku (label induk per
 * listing) diulang di tiap baris. Kolom ID TikTok (~19 digit) dipaksa TEXT agar
 * presisi tak hilang / tak jadi notasi ilmiah di Excel.
 */
class StoreProductsExport extends DefaultValueBinder implements FromCollection, WithHeadings, WithColumnFormatting, WithCustomValueBinder, WithStyles, WithEvents, ShouldAutoSize
{
    /** Batas lebar kolom Parent SKU (C) — labelnya bisa sangat panjang bila 1 listing gabung banyak base. */
    private const PARENT_SKU_MAX_WIDTH = 50;

    public function __construct(private int $storeId)
    {
    }

    /**
     * Paksa string digit-panjang (product_id/sku_id ~19 digit) jadi TEXT — kalau
     * dibiarkan, binder default meng-cast ke angka & Excel membulatkan (>15 digit).
     */
    public function bindValue(Cell $cell, $value): bool
    {
        if (is_string($value) && ctype_digit($value) && strlen($value) > 15) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    public function collection()
    {
        $rows = DB::table('tiktok_listings as tl')
            ->join('tiktok_listing_skus as ts', 'ts.listing_id', '=', 'tl.id')
            ->join('products as pr', 'pr.id', '=', 'ts.product_id')
            ->leftJoin('tiktok_listing_prices as p', function ($x) {
                $x->on('p.listing_id', '=', 'tl.id')
                    ->on('p.product_id', '=', 'ts.product_id');
            })
            ->where('tl.store_id', $this->storeId)
            ->orderBy('tl.tiktok_product_id')
            ->orderBy('ts.tiktok_sku_id')
            ->get([
                'tl.tiktok_product_id as product_id', 'ts.tiktok_sku_id as sku_id',
                'pr.sku_code', 'pr.variation_label',
                'pr.stok', 'pr.po_qty', 'pr.hpp', 'p.retail_price', 'p.promotion_price',
            ]);

        // Label induk per listing (product_id) → diulang di tiap baris varian.
        $labels = $rows->groupBy('product_id')
            ->map(fn ($g) => ProductController::indukLabel($g->pluck('sku_code')));

        return $rows->map(fn ($r) => [
            (string) $r->product_id,
            (string) $r->sku_id,
            (string) $labels[$r->product_id],
            (string) $r->sku_code,
            (string) ($r->variation_label ?? ''),
            (int) $r->hpp,
            $r->retail_price !== null ? (int) $r->retail_price : null,
            $r->promotion_price !== null ? (int) $r->promotion_price : null,
            (int) $r->stok,
            (int) $r->po_qty,
        ]);
    }

    public function headings(): array
    {
        return [
            'Product ID', 'SKU ID', 'Parent SKU', 'Seller SKU', 'Variasi',
            'HPP', 'Harga Normal', 'Harga Promo', 'Stok', 'PO',
        ];
    }

    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_TEXT,        // Product ID (bigint) → text
            'B' => NumberFormat::FORMAT_TEXT,        // SKU ID (bigint)     → text
            'F' => '"Rp"#,##0',                       // HPP
            'G' => '"Rp"#,##0',                       // Harga Normal
            'H' => '"Rp"#,##0',                       // Harga Promo
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [1 => ['font' => ['bold' => true]]];   // header tebal
    }

    public function registerEvents(): array
    {
        // ShouldAutoSize meng-auto-fit semua kolom; event ini (dijalankan SETELAH
        // auto-size) meng-override kolom C (Parent SKU) ke lebar tetap agar tak melebar.
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $event->sheet->getDelegate()
                    ->getColumnDimension('C')
                    ->setAutoSize(false)
                    ->setWidth(self::PARENT_SKU_MAX_WIDTH);
            },
        ];
    }
}
