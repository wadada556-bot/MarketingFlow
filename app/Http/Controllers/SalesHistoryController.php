<?php

namespace App\Http\Controllers;

use App\Http\Requests\FilterDateRangeRequest;
use App\Models\Order;
use App\Models\Product;
use App\Support\SkuMatch;
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
        $earliestDate = Order::min('sales_date');
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

        // Agregasi per (parent_sku, sku, toko, channel) dalam rentang.
        // Alias sku_parent/sku_variant -> parent_sku/sku agar sisa kode & view tak berubah.
        $rows = Order::query()
            ->whereBetween('sales_date', [$from, $to])
            ->where('channel_id', $tiktokId)
            ->when($storeId !== null, fn ($q) => $q->where('store_id', $storeId))
            ->selectRaw('sku_parent as parent_sku, sku_variant as sku, store_id, store_name, channel_id, channel_name,
                         SUM(qty) as qty')
            ->groupBy('sku_parent', 'sku_variant', 'store_id', 'store_name', 'channel_id', 'channel_name')
            ->get();

        // Parent produk terdaftar (products) untuk pengelompokan berbasis prefix —
        // konsisten dgn Product Ads (lihat App\Support\SkuMatch). SKU yg tak dimiliki
        // produk mana pun jatuh ke sku_parent lama (strip "-\d+$").
        $productParents = Product::pluck('parent_sku')->all();

        // Susun bertingkat: produk (parent_sku) → variasi (sku); qty dipivot per toko
        $products = [];
        foreach ($rows as $r) {
            $pkey = SkuMatch::owner($r->sku, $productParents) ?? ($r->parent_sku ?: $r->sku);
            $sid  = (int) $r->store_id;
            $qty  = (int) $r->qty;

            $products[$pkey] ??= [
                'parent_sku'   => $pkey,
                'product_name' => '',   // orders tak simpan nama produk; cari via SKU saja
                'qty'          => 0,
                'channels'     => [],
                'store_qty'    => [],   // total per toko (untuk baris TOTAL)
                'variants'     => [],
            ];

            $products[$pkey]['qty'] += $qty;
            $products[$pkey]['channels'][(int) $r->channel_id] = self::CHANNELS[(int) $r->channel_id]
                ?? ($r->channel_name ?: $r->channel_id);
            $products[$pkey]['store_qty'][$sid] = ($products[$pkey]['store_qty'][$sid] ?? 0) + $qty;

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

            // Ranking best seller (emas/perak/perunggu) per toko: berdasarkan qty variasi di toko itu
            $storeIds = [];
            foreach ($p['variants'] as $v) {
                $storeIds = array_merge($storeIds, array_keys($v['store_qty']));
            }
            $p['store_rank'] = [];
            foreach (array_unique($storeIds) as $sid) {
                $qtyBySku = [];
                foreach ($p['variants'] as $v) {
                    $q = $v['store_qty'][$sid] ?? 0;
                    if ($q > 0) {
                        $qtyBySku[$v['sku']] = $q;
                    }
                }
                arsort($qtyBySku);
                $rank = 1;
                foreach (array_keys(array_slice($qtyBySku, 0, 3, true)) as $sku) {
                    $p['store_rank'][$sid][$sku] = $rank++;
                }
            }
        }
        unset($p);
        uasort($products, fn ($a, $b) => $b['qty'] <=> $a['qty']);

        // Total keseluruhan
        $totals = [
            'qty'      => array_sum(array_column($products, 'qty')),
            'products' => count($products),
        ];

        // Kelompokkan produk per prefix huruf (buang angka di belakang):
        // TRC1, TRC5 → grup "TRC". Kalau tak ada angka di belakang, produk jadi grup sendiri.
        $groups = [];
        foreach ($products as $p) {
            $gkey = preg_replace('/[\s\-_]*\d+.*$/', '', $p['parent_sku']);
            $gkey = $gkey !== '' ? $gkey : $p['parent_sku'];

            $groups[$gkey] ??= [
                'group'    => $gkey,
                'qty'      => 0,
                'products' => [],
            ];
            $groups[$gkey]['qty']        += $p['qty'];
            $groups[$gkey]['products'][]  = $p;
        }
        // Grup diurut by qty desc (produk di dalamnya sudah urut qty desc)
        uasort($groups, fn ($a, $b) => $b['qty'] <=> $a['qty']);

        // Kolom toko = seluruh toko TikTok (stabil di tiap tabel; sel kosong → "-")
        $storeColumns = Order::query()
            ->selectRaw('store_id, MAX(store_name) as store_name')
            ->where('channel_id', $tiktokId)
            ->when($storeId !== null, fn ($q) => $q->where('store_id', $storeId))
            ->whereNotNull('store_name')
            ->groupBy('store_id')
            ->orderByRaw('MAX(store_name)')
            ->get();

        // Opsi filter toko (semua toko TikTok)
        $storeOptions = Order::query()
            ->selectRaw('store_id, MAX(store_name) as store_name')
            ->where('channel_id', $tiktokId)
            ->whereNotNull('store_name')
            ->groupBy('store_id')
            ->orderByRaw('MAX(store_name)')
            ->get();

        return view('sales-history.index', [
            'products'     => $products,
            'groups'       => $groups,
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
