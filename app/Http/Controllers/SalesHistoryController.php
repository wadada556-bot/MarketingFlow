<?php

namespace App\Http\Controllers;

use App\Http\Requests\FilterDateRangeRequest;
use App\Models\DailySkuSales;
use Carbon\Carbon;

class SalesHistoryController extends Controller
{
    /** id => label tampilan */
    public const CHANNELS = [
        128    => 'Tokopedia',
        131076 => 'TikTok',
    ];

    public function index(FilterDateRangeRequest $request)
    {
        $today = Carbon::today();

        // Batas data yang benar-benar tersedia: tanggal tersedia awal s/d hari ini
        $earliestDate = DailySkuSales::min('sales_date');
        $minDate      = $earliestDate ? Carbon::parse($earliestDate)->toDateString() : $today->toDateString();
        $maxDate      = $today->toDateString();

        // Default: awal tahun berjalan s/d hari ini (lihat seluruh histori tahun ini)
        $from = $request->query('date_from')
            ? Carbon::parse($request->query('date_from'))->toDateString()
            : $today->copy()->startOfYear()->toDateString();
        $to = $request->query('date_to')
            ? Carbon::parse($request->query('date_to'))->toDateString()
            : $today->toDateString();

        // Batasi ke rentang data yang tersedia (tidak boleh sebelum data terlama / setelah hari ini)
        $from = max($from, $minDate);
        $from = min($from, $maxDate);
        $to   = min($to, $maxDate);
        $to   = max($to, $minDate);

        // Halaman ini khusus menampilkan channel TikTok
        $tiktokId = 131076;
        $storeId  = $request->filled('store_id') ? (int) $request->query('store_id') : null;

        // Agregasi per (parent_sku, sku, toko, channel) dalam rentang
        $rows = DailySkuSales::query()
            ->whereBetween('sales_date', [$from, $to])
            ->where('channel_id', $tiktokId)
            ->when($storeId !== null, fn ($q) => $q->where('store_id', $storeId))
            ->selectRaw('parent_sku, sku, store_id, store_name, channel_id, channel_name,
                         MAX(product_name) as product_name,
                         SUM(qty_terjual) as qty')
            ->groupBy('parent_sku', 'sku', 'store_id', 'store_name', 'channel_id', 'channel_name')
            ->get();

        // Susun bertingkat: produk (parent_sku) → variasi (sku); qty dipivot per toko
        $products = [];
        foreach ($rows as $r) {
            $pkey = $r->parent_sku ?: $r->sku;
            $sid  = (int) $r->store_id;
            $qty  = (int) $r->qty;

            $products[$pkey] ??= [
                'parent_sku'   => $pkey,
                'product_name' => $r->product_name,
                'qty'          => 0,
                'channels'     => [],
                'store_qty'    => [],   // total per toko (untuk baris TOTAL)
                'variants'     => [],
            ];

            $products[$pkey]['qty'] += $qty;
            $products[$pkey]['channels'][(int) $r->channel_id] = self::CHANNELS[(int) $r->channel_id]
                ?? ($r->channel_name ?: $r->channel_id);
            $products[$pkey]['store_qty'][$sid] = ($products[$pkey]['store_qty'][$sid] ?? 0) + $qty;
            if (empty($products[$pkey]['product_name']) && $r->product_name) {
                $products[$pkey]['product_name'] = $r->product_name;
            }

            $vkey = $r->sku;
            $products[$pkey]['variants'][$vkey] ??= [
                'sku'       => $r->sku,
                'qty'       => 0,
                'store_qty' => [],       // qty per toko untuk variasi ini
            ];
            $products[$pkey]['variants'][$vkey]['qty'] += $qty;
            $products[$pkey]['variants'][$vkey]['store_qty'][$sid]
                = ($products[$pkey]['variants'][$vkey]['store_qty'][$sid] ?? 0) + $qty;
        }

        // Urut: produk & variasi by qty desc
        foreach ($products as &$p) {
            uasort($p['variants'], fn ($a, $b) => $b['qty'] <=> $a['qty']);
        }
        unset($p);
        uasort($products, fn ($a, $b) => $b['qty'] <=> $a['qty']);

        // Total keseluruhan
        $totals = [
            'qty'      => array_sum(array_column($products, 'qty')),
            'products' => count($products),
        ];

        // Kolom toko = seluruh toko TikTok (stabil di tiap tabel; sel kosong → "-")
        $storeColumns = DailySkuSales::query()
            ->selectRaw('store_id, MAX(store_name) as store_name')
            ->where('channel_id', $tiktokId)
            ->when($storeId !== null, fn ($q) => $q->where('store_id', $storeId))
            ->whereNotNull('store_name')
            ->groupBy('store_id')
            ->orderByRaw('MAX(store_name)')
            ->get();

        // Opsi filter toko (semua toko TikTok)
        $storeOptions = DailySkuSales::query()
            ->selectRaw('store_id, MAX(store_name) as store_name')
            ->where('channel_id', $tiktokId)
            ->whereNotNull('store_name')
            ->groupBy('store_id')
            ->orderByRaw('MAX(store_name)')
            ->get();

        return view('sales-history.index', [
            'products'     => $products,
            'totals'       => $totals,
            'from'         => $from,
            'to'           => $to,
            'minDate'      => $minDate,
            'maxDate'      => $maxDate,
            'storeId'      => $storeId,
            'storeColumns' => $storeColumns,
            'storeOptions' => $storeOptions,
        ]);
    }
}
