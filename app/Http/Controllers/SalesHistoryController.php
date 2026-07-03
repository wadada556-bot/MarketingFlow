<?php

namespace App\Http\Controllers;

use App\Http\Requests\FilterDateRangeRequest;
use App\Models\JubelioInventory;
use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

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

        // Default: 30 hari terakhir (buka halaman ringan; perlebar via date picker).
        $from = $request->query('date_from')
            ? Carbon::parse($request->query('date_from'))->toDateString()
            : $today->copy()->subDays(29)->toDateString();
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
            ->selectRaw('sku_parent as parent_sku, sku_variant as sku, store_id, channel_id,
                         MAX(store_name) as store_name, MAX(channel_name) as channel_name,
                         SUM(qty) as qty')
            ->groupBy('sku_parent', 'sku_variant', 'store_id', 'channel_id')
            ->get();

        // Parent produk = parent_sku Jubelio (sumber kebenaran item_group).
        // Peta sku_code → parent_sku dari jubelio_inventory; SKU yg tak ada di
        // inventory jatuh ke sku_parent bawaan orders.
        $skuList   = $rows->pluck('sku')->unique()->all();
        $parentMap = JubelioInventory::whereIn('sku_code', $skuList)
            ->pluck('parent_sku', 'sku_code')
            ->all();

        // Susun bertingkat: produk (parent_sku) → variasi (sku); qty dipivot per toko
        $products = [];
        foreach ($rows as $r) {
            $pkey = $parentMap[$r->sku] ?? ($r->parent_sku ?: $r->sku);
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

        // Daftar toko TikTok jarang berubah → cache 30 mnt supaya tidak memindai
        // seluruh tabel orders tiap buka halaman (dulu 2 query full-scan).
        // Cache ARRAY PRIMITIF murni (bukan Collection/Model) — objek tidak
        // ter-unserialize bersih dari file cache. Objek dibangun ulang di memori.
        $storeRaw = Cache::remember('sales_history_tiktok_stores', 1800, fn () =>
            Order::query()
                ->selectRaw('store_id, MAX(store_name) as store_name')
                ->where('channel_id', $tiktokId)
                ->whereNotNull('store_name')
                ->groupBy('store_id')
                ->orderByRaw('MAX(store_name)')
                ->get()
                ->map(fn ($r) => ['store_id' => (int) $r->store_id, 'store_name' => $r->store_name])
                ->all()
        );
        $allStores = collect($storeRaw)->map(fn ($a) => (object) $a);

        // Opsi filter = semua toko; kolom tabel = toko terpilih saja bila difilter.
        $storeOptions = $allStores;
        $storeColumns = $storeId !== null
            ? $allStores->where('store_id', $storeId)->values()
            : $allStores;

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
