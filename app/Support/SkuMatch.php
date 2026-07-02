<?php

namespace App\Support;

/**
 * Pencocokan produk (parent_sku) ↔ variannya berbasis PREFIX.
 *
 * Penomoran SKU di Jubelio tidak konsisten — ada yang pakai dash+angka
 * (`C223-ANT161-1`), angka tanpa dash (`HTT1`, `TRG2`), angka menempel
 * (`BM-LCAZD01`), atau suffix teks (`C223-KLG75-SET`). Tidak ada satu aturan
 * strip/grouping yang benar untuk semua. Satu-satunya relasi yang konsisten:
 * **sebuah produk memiliki semua varian yang KODE-nya diawali parent_sku-nya.**
 *
 * Dipakai bersama oleh Product Ads, Dashboard, NotifyStockCheck (lookup
 * jubelio_inventory) & DailySalesQueryService (lookup orders) supaya semua
 * konsisten. Tidak ada tabrakan selama tak ada parent_sku yang jadi prefix
 * parent_sku lain (bila terjadi, owner() memilih prefix TERPANJANG).
 */
class SkuMatch
{
    /** UPPER + trim + buang kosong + unik (kolom SKU di DB selalu uppercase). */
    public static function normalize(array $parentSkus): array
    {
        return array_values(array_unique(array_filter(
            array_map(fn ($s) => strtoupper(trim((string) $s)), $parentSkus),
            fn ($s) => $s !== '',
        )));
    }

    /**
     * Tambahkan `WHERE (col LIKE 'A%' OR col LIKE 'B%' ...)` ke query builder
     * (Eloquent maupun Query Builder). Kalau daftar kosong → paksa tanpa hasil.
     *
     * @param  \Illuminate\Contracts\Database\Query\Builder  $query
     * @param  string[]  $parentSkus
     */
    public static function wherePrefix($query, string $column, array $parentSkus): void
    {
        $skus = self::normalize($parentSkus);

        if (empty($skus)) {
            $query->whereRaw('1 = 0');
            return;
        }

        $query->where(function ($q) use ($column, $skus) {
            foreach ($skus as $s) {
                $q->orWhere($column, 'like', self::escapeLike($s) . '%');
            }
        });
    }

    /**
     * Tentukan parent_sku mana (dari $parentSkus) yang memiliki $code — yaitu
     * prefix TERPANJANG yang cocok. Mengembalikan string ASLI dari $parentSkus
     * (casing pemanggil dipertahankan) supaya hasil bisa langsung di-key balik.
     *
     * @param  string[]  $parentSkus
     */
    public static function owner(string $code, array $parentSkus): ?string
    {
        $code    = strtoupper($code);
        $best    = null;
        $bestLen = -1;

        foreach ($parentSkus as $s) {
            $u = strtoupper(trim((string) $s));
            if ($u !== '' && str_starts_with($code, $u) && strlen($u) > $bestLen) {
                $best    = $s;
                $bestLen = strlen($u);
            }
        }

        return $best;
    }

    /** Escape karakter wildcard LIKE (%, _, \) agar diperlakukan literal. */
    private static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
