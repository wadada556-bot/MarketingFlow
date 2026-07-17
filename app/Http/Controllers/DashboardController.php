<?php

namespace App\Http\Controllers;

use App\Models\JubelioInventory;
use App\Models\Product;
use App\Support\SkuMatch;
use App\Services\DailySalesQueryService;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    private const QTY_DANGER  = 100;
    private const QTY_WARNING = 300;

    public function __construct(
        private readonly DailySalesQueryService $dailySalesQueryService,
    ) {}

    public function index()
    {
        // ── Stock Alerts ─────────────────────────────────────────────────
        // Bagian ini memanggil ERP (lambat). Tidak di-hitung di sini agar dashboard
        // render instan; diisi lewat AJAX ke dashboard.stock-alerts (lihat view).

        return view('dashboard.index');
    }

    /**
     * Endpoint AJAX: hitung Peringatan Stok (ERP) lalu kembalikan HTML kartu + panel.
     */
    public function stockAlerts()
    {
        $data = $this->buildStockAlerts();

        return response()->json([
            'card'  => view('dashboard.partials._stock-card-body', $data)->render(),
            'panel' => view('dashboard.partials._stock-panel', $data)->render(),
            'count' => $data['stockAlertCount'],
        ]);
    }

    /**
     * Hitung daftar peringatan stok dari ERP (stok + penjualan 30 hari).
     *
     * @return array{stockAlerts: array, stockAlertCount: int, stockApiUnavailable: bool}
     */
    private function buildStockAlerts(): array
    {
        $stockAlerts         = [];
        $stockAlertCount     = 0;
        $stockApiUnavailable = false;

        $products = Product::query()
            ->select('id', 'parent_sku')
            ->with([
                'productAds:id,product_id',
                'productAds.stores:id,name',
            ])
            ->whereHas('productAds')
            ->limit(100)
            ->get();

        $parentSkus = $products->pluck('parent_sku')->unique()->values()->all();

        if (!empty($parentSkus)) {
            // Stok + HPP dari tabel lokal jubelio_inventory (disync berkala via
            // jubelio:sync-inventory) — query DB murah, tidak perlu cache/live API lagi.
            // Produk memiliki varian yang KODE-nya diawali parent_sku (lihat SkuMatch).
            $q = JubelioInventory::query();
            SkuMatch::wherePrefix($q, 'sku_code', $parentSkus);
            $rows = $q->get();

            if ($rows->isEmpty() && JubelioInventory::count() === 0) {
                // Tabel belum pernah disync sama sekali
                $stockApiUnavailable = true;
            }

            if (!$stockApiUnavailable) {
                $stockData = [];
                $hppMap    = [];
                foreach ($rows as $row) {
                    $owner = SkuMatch::owner($row->sku_code, $parentSkus);
                    if ($owner !== null) {
                        $stockData[$owner][] = ['sku' => $row->sku_code, 'qty' => $row->stok];
                    }
                    $hppMap[$row->sku_code] = $row->hpp;
                }

                foreach ($products as $product) {
                    $parentSku     = $product->parent_sku;
                    $allVariants   = $stockData[$parentSku] ?? [];
                    $alertVariants = array_filter($allVariants, fn ($v) => $v['qty'] <= self::QTY_WARNING);

                    if (empty($alertVariants)) {
                        continue;
                    }

                    $classified = array_values(array_map(fn ($v) => [
                        'sku'    => $v['sku'],
                        'qty'    => $v['qty'],
                        'status' => $this->classifyStock($v['qty']),
                        'hpp'    => $hppMap[$v['sku']] ?? 0,
                    ], $allVariants));

                    $alertClassified = array_filter($classified, fn ($v) => $v['status'] !== 'safe');
                    $hasDanger       = !empty(array_filter($alertClassified, fn ($v) => $v['status'] === 'danger'));
                    $overallStatus   = $hasDanger ? 'danger' : 'warning';
                    $alertQtyTotal   = array_sum(array_column(array_values($alertClassified), 'qty'));

                    $storeNames = $product->productAds
                        ->flatMap->stores
                        ->unique('id')
                        ->pluck('name')
                        ->map(fn ($n) => strtoupper($n))
                        ->values()
                        ->all();

                    $stockAlerts[] = [
                        'parent_sku'   => $parentSku,
                        'status'       => $overallStatus,
                        'stores'       => $storeNames,
                        'variants'     => array_values($alertClassified),
                        'alert_count'  => count($alertClassified),
                        'danger_count' => count(array_filter($alertClassified, fn ($v) => $v['status'] === 'danger')),
                        '_sort_qty'    => $alertQtyTotal,
                    ];
                }

                usort($stockAlerts, function ($a, $b) {
                    if ($a['status'] !== $b['status']) {
                        return $a['status'] === 'danger' ? -1 : 1;
                    }
                    return $a['_sort_qty'] <=> $b['_sort_qty'];
                });

                $stockAlerts = array_map(function ($a) {
                    unset($a['_sort_qty']);
                    return $a;
                }, $stockAlerts);
            }
        }

        $stockAlertCount = count($stockAlerts);

        // ── Best Seller badge: 90-day TikTok sales per alert product ─────
        foreach ($stockAlerts as &$_a) {
            $_a['is_best_seller']     = false;
            $_a['has_urgent_variant'] = false;
            $_a['sales_90d']          = 0;
        }
        unset($_a);

        if (!$stockApiUnavailable && !empty($stockAlerts)) {
            $alertSkus     = array_column($stockAlerts, 'parent_sku');
            $salesCacheKey = 'dashboard_local_sales_' . md5(implode(',', $alertSkus));

            // Sales dari tabel orders lokal (bukan ERP lagi) — query DB murah, cache tetap
            // dipasang untuk menghindari query berulang tiap load dashboard.
            $salesData = Cache::remember($salesCacheKey, 1800, function () use ($alertSkus) {
                return $this->dailySalesQueryService->getForParentSkus($alertSkus)['total'];
            });

            // ── PO data — dari jubelio_inventory lokal (sudah di-query di atas sbg $rows) ──
            $poData = [];
            foreach ($rows as $row) {
                $owner = SkuMatch::owner($row->sku_code, $alertSkus);
                if ($owner !== null) {
                    $poData[$owner][$row->sku_code] = $row->po_qty;
                }
            }

            foreach ($stockAlerts as &$_a) {
                $skuSales90d     = $salesData[$_a['parent_sku']]['90d'] ?? [];
                $_a['sales_90d'] = array_sum($skuSales90d);

                // Rank top-3 variants by 90d sales
                $sorted = collect($skuSales90d)->filter(fn ($v) => $v > 0)->sortDesc();
                $rank1  = $sorted->keys()->get(0);
                $rank2  = $sorted->keys()->get(1);
                $rank3  = $sorted->keys()->get(2);

                $variantPoMap = $poData[$_a['parent_sku']] ?? [];

                $_a['variants'] = array_map(function ($v) use ($rank1, $rank2, $rank3, $variantPoMap) {
                    $v['rank_90d'] = match (true) {
                        $v['sku'] === $rank1 => 1,
                        $v['sku'] === $rank2 => 2,
                        $v['sku'] === $rank3 => 3,
                        default              => null,
                    };
                    $v['po_qty'] = $variantPoMap[$v['sku']] ?? null;
                    return $v;
                }, $_a['variants']);

                // URGENT only when a top-ranked variant itself is low/critical stock
                $_a['has_urgent_variant'] = !empty(array_filter(
                    $_a['variants'],
                    fn ($v) => ($v['rank_90d'] ?? null) !== null
                ));
            }
            unset($_a);

            // ★ Best Seller badge: product has meaningful 90d sales (2+ products needed)
            $withSales = array_filter($stockAlerts, fn ($a) => $a['sales_90d'] > 0);
            if (count($withSales) >= 2) {
                foreach ($stockAlerts as &$_a) {
                    $_a['is_best_seller'] = $_a['sales_90d'] > 0;
                }
                unset($_a);
            }

            // Sort: urgent (top-variant low stock) first → danger → warning
            usort($stockAlerts, function ($a, $b) {
                if ($a['has_urgent_variant'] !== $b['has_urgent_variant']) {
                    return $a['has_urgent_variant'] ? -1 : 1;
                }
                if ($a['status'] !== $b['status']) {
                    return $a['status'] === 'danger' ? -1 : 1;
                }
                return 0;
            });
        }

        return [
            'stockAlerts'         => $stockAlerts,
            'stockAlertCount'     => $stockAlertCount,
            'stockApiUnavailable' => $stockApiUnavailable,
        ];
    }

    private function classifyStock(int $qty): string
    {
        if ($qty <= self::QTY_DANGER) return 'danger';
        if ($qty <= self::QTY_WARNING) return 'warning';
        return 'safe';
    }
}
