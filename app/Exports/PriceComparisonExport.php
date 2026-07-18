<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Export .xlsx pivot Perbandingan Harga Promo: satu baris per seller SKU, satu
 * kolom per toko. Sel yang harganya beda dari modus baris itu diberi fill warna
 * yang sama seperti highlight di halaman (FFDAD6 — setara var(--md-error-container)).
 */
class PriceComparisonExport implements FromArray, WithHeadings, WithColumnFormatting, WithStyles, WithEvents, ShouldAutoSize
{
    private const FIXED_COLUMNS = 6; // Seller SKU, Variasi, HPP, Harga Terendah, Harga Tertinggi, Selisih

    private const DIFF_FILL_COLOR = 'FFDAD6';

    public function __construct(private Collection $rows, private Collection $stores)
    {
    }

    public function array(): array
    {
        return $this->rows->map(function ($row) {
            $line = [
                $row['sku_code'],
                $row['variasi'] ?? '',
                $row['hpp'],
                $row['min'],
                $row['max'],
                $row['selisih'],
            ];
            foreach ($this->stores as $store) {
                $line[] = $row['prices'][$store->id] ?? null;
            }

            return $line;
        })->all();
    }

    public function headings(): array
    {
        $headings = ['Seller SKU', 'Variasi', 'HPP', 'Harga Terendah', 'Harga Tertinggi', 'Selisih (Rp)'];
        foreach ($this->stores as $store) {
            $headings[] = ucwords($store->name);
        }

        return $headings;
    }

    public function columnFormats(): array
    {
        $formats = [];
        $totalColumns = self::FIXED_COLUMNS + $this->stores->count();
        for ($i = 3; $i <= $totalColumns; $i++) {
            $formats[Coordinate::stringFromColumnIndex($i)] = '"Rp"#,##0';
        }

        return $formats;
    }

    public function styles(Worksheet $sheet)
    {
        return [1 => ['font' => ['bold' => true]]];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $rowIndex = 2; // baris 1 = header

                foreach ($this->rows as $row) {
                    if ($row['mode'] !== null) {
                        foreach ($this->stores as $i => $store) {
                            $price = $row['prices'][$store->id] ?? null;
                            if ($price !== null && $price !== $row['mode']) {
                                $col = Coordinate::stringFromColumnIndex(self::FIXED_COLUMNS + $i + 1);
                                $sheet->getStyle($col . $rowIndex)->getFill()
                                    ->setFillType(Fill::FILL_SOLID)
                                    ->getStartColor()->setRGB(self::DIFF_FILL_COLOR);
                            }
                        }
                    }
                    $rowIndex++;
                }
            },
        ];
    }
}
