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

        // Default: awal tahun berjalan s/d hari ini (lihat seluruh histori tahun ini)
        $from = $request->query('date_from')
            ? Carbon::parse($request->query('date_from'))->toDateString()
            : $today->copy()->startOfYear()->toDateString();
        $to = $request->query('date_to')
            ? Carbon::parse($request->query('date_to'))->toDateString()
            : $today->toDateString();

        $channelId = $request->integer('channel_id') ?: null;
        $storeId   = $request->filled('store_id') ? (int) $request->query('store_id') : null;

        // Agregasi per (parent_sku, sku, toko, channel) dalam rentang
        $rows = DailySkuSales::query()
            ->whereBetween('sales_date', [$from, $to])
            ->when($channelId, fn ($q) => $q->where('channel_id', $channelId))
            ->when($storeId !== null, fn ($q) => $q->where('store_id', $storeId))
            ->selectRaw('parent_sku, sku, store_id, store_name, channel_id, channel_name,
                         MAX(product_name) as product_name,
                         SUM(qty_terjual) as qty, SUM(omzet) as omzet, SUM(order_count) as orders')
            ->groupBy('parent_sku', 'sku', 'store_id', 'store_name', 'channel_id', 'channel_name')
            ->get();

        // Susun bertingkat: produk (parent_sku) → variasi (sku) → toko
        $products = [];
        foreach ($rows as $r) {
            $pkey = $r->parent_sku ?: $r->sku;

            $products[$pkey] ??= [
                'parent_sku'   => $pkey,
                'product_name' => $r->product_name,
                'qty'          => 0,
                'omzet'        => 0,
                'orders'       => 0,
                'channels'     => [],
                'variants'     => [],
            ];

            $products[$pkey]['qty']    += (int) $r->qty;
            $products[$pkey]['omzet']  += (int) $r->omzet;
            $products[$pkey]['orders'] += (int) $r->orders;
            $products[$pkey]['channels'][(int) $r->channel_id] = self::CHANNELS[(int) $r->channel_id]
                ?? ($r->channel_name ?: $r->channel_id);
            if (empty($products[$pkey]['product_name']) && $r->product_name) {
                $products[$pkey]['product_name'] = $r->product_name;
            }

            $vkey = $r->sku;
            $products[$pkey]['variants'][$vkey] ??= [
                'sku'    => $r->sku,
                'qty'    => 0,
                'omzet'  => 0,
                'orders' => 0,
                'stores' => [],
            ];
            $products[$pkey]['variants'][$vkey]['qty']    += (int) $r->qty;
            $products[$pkey]['variants'][$vkey]['omzet']  += (int) $r->omzet;
            $products[$pkey]['variants'][$vkey]['orders'] += (int) $r->orders;
            $products[$pkey]['variants'][$vkey]['stores'][] = [
                'store_name'   => $r->store_name,
                'channel_id'   => (int) $r->channel_id,
                'channel_name' => self::CHANNELS[(int) $r->channel_id] ?? $r->channel_name,
                'qty'          => (int) $r->qty,
                'omzet'        => (int) $r->omzet,
                'orders'       => (int) $r->orders,
            ];
        }

        // Urut: produk & variasi by omzet desc; toko by omzet desc
        foreach ($products as &$p) {
            foreach ($p['variants'] as &$v) {
                usort($v['stores'], fn ($a, $b) => $b['omzet'] <=> $a['omzet']);
            }
            unset($v);
            uasort($p['variants'], fn ($a, $b) => $b['omzet'] <=> $a['omzet']);
        }
        unset($p);
        uasort($products, fn ($a, $b) => $b['omzet'] <=> $a['omzet']);

        // Total keseluruhan
        $totals = [
            'omzet'    => array_sum(array_column($products, 'omzet')),
            'qty'      => array_sum(array_column($products, 'qty')),
            'orders'   => array_sum(array_column($products, 'orders')),
            'products' => count($products),
        ];

        // Opsi filter toko (dari data yang ada)
        $storeOptions = DailySkuSales::query()
            ->select('store_id', 'store_name')
            ->whereNotNull('store_name')
            ->distinct()
            ->orderBy('store_name')
            ->get();

        return view('sales-history.index', [
            'products'     => $products,
            'totals'       => $totals,
            'from'         => $from,
            'to'           => $to,
            'channelId'    => $channelId,
            'storeId'      => $storeId,
            'channels'     => self::CHANNELS,
            'storeOptions' => $storeOptions,
        ]);
    }
}
