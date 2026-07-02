<?php

namespace App\Http\Controllers;

use App\Http\Requests\FilterDateRangeRequest;
use App\Models\DailyAdStore;
use App\Models\DailyProductAd;
use App\Models\JubelioInventory;
use App\Models\Product;
use App\Models\Store;
use App\Models\DailyStoreStat;
use App\Services\DailySalesQueryService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    private const QTY_DANGER      = 100;
    private const QTY_WARNING     = 300;
    private const AGG_CACHE_TTL      = 300; // cache GMV/pesanan dashboard (5 menit)
    private const AGG_ROAS_CACHE_TTL = 360; // cache ROAS/ad-spend (stagger agar tidak expire bersamaan)

    private const SLUG_MAP = [
        'topi keren'        => 'topi-keren',
        'caramel aksesoris' => 'caramel',
        'minzo store'       => 'minzo',
        'yarra store'       => 'yarra',
        'nomide store'      => 'nomide',
        'topi kece'         => 'topi-kece',
        'moonklaz'          => 'moonklaz',
    ];

    private const COLORS = [
        'topi-keren' => '#1D9E75',
        'caramel'    => '#BA7517',
        'minzo'      => '#378ADD',
        'yarra'      => '#D4537E',
        'nomide'     => '#7F77DD',
        'topi-kece'  => '#D85A30',
        'moonklaz'   => '#2CA4C0',
    ];

    public function __construct(
        private readonly DailySalesQueryService $dailySalesQueryService,
    ) {}

    public function index(FilterDateRangeRequest $request)
    {
        $today  = Carbon::today();
        $period = $request->query('period', 'today');

        if ($period === 'custom') {
            $dateFrom = $request->query('date_from');
            $dateTo   = $request->query('date_to');

            $curStart = $dateFrom ? Carbon::parse($dateFrom) : $today->copy();
            $curEnd   = $dateTo   ? Carbon::parse($dateTo)   : $today->copy();

            $days      = $curStart->diffInDays($curEnd) + 1;
            $prevStart = $curStart->copy()->subDays($days);
            $prevEnd   = $curStart->copy()->subDay();

            $startFmt   = $curStart->locale('id')->isoFormat('D MMM YYYY');
            $endFmt     = $curEnd->locale('id')->isoFormat('D MMM YYYY');
            $trendLabel = 'vs periode sebelumnya';
            $periodLabel = $startFmt === $endFmt ? $startFmt : "{$startFmt} – {$endFmt}";
        } else {
            $dateFrom = null;
            $dateTo   = null;

            [$curStart, $curEnd, $prevStart, $prevEnd, $trendLabel, $periodLabel] = match ($period) {
                '7d'  => [
                    $today->copy()->subDays(6), $today->copy(),
                    $today->copy()->subDays(13), $today->copy()->subDays(7),
                    'vs 7 hari sebelumnya', '7 hari terakhir',
                ],
                '30d' => [
                    $today->copy()->subDays(29), $today->copy(),
                    $today->copy()->subDays(59), $today->copy()->subDays(30),
                    'vs 30 hari sebelumnya', '30 hari terakhir',
                ],
                'kemarin' => [
                    $today->copy()->subDay(), $today->copy()->subDay(),
                    $today->copy()->subDays(2), $today->copy()->subDays(2),
                    'vs 2 hari lalu', 'Kemarin',
                ],
                default => [
                    $today->copy(), $today->copy(),
                    $today->copy()->subDay(), $today->copy()->subDay(),
                    'vs kemarin', 'Hari ini',
                ],
            };
        }

        $dayNames = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];

        // ── Aggregasi DB: dua blok cache terpisah dengan TTL berbeda ────────
        // Cache menyimpan array biasa (scalar) — 100% aman di-serialize; objek/collection
        // Eloquent tidak round-trip andal lewat serialize(). Direkonstruksi ke stdClass di bawah.
        // GMV/pesanan dan ROAS dipisah agar tidak spike DB saat expire bersamaan.
        $dateKey  = $curStart->toDateString() . '_' . $curEnd->toDateString()
                  . '_' . $prevStart->toDateString() . '_' . $prevEnd->toDateString();

        $gmvAgg = Cache::remember(
            'dashboard_gmv_' . $dateKey,
            self::AGG_CACHE_TTL,
            function () use ($curStart, $curEnd, $prevStart, $prevEnd) {
                $toArr = fn ($q) => $q->get()->map(fn ($m) => $m->getAttributes())->all();

                return [
                    'curSums' => $toArr(DailyStoreStat::query()
                        ->whereBetween('tanggal', [$curStart->toDateString(), $curEnd->toDateString()])
                        ->selectRaw('store_id, SUM(gmv) as gmv, SUM(pesanan) as pesanan')
                        ->groupBy('store_id')),

                    'prevSums' => $toArr(DailyStoreStat::query()
                        ->whereBetween('tanggal', [$prevStart->toDateString(), $prevEnd->toDateString()])
                        ->selectRaw('store_id, SUM(gmv) as gmv, SUM(pesanan) as pesanan')
                        ->groupBy('store_id')),

                    'chartRaw' => $toArr(DailyStoreStat::query()
                        ->whereBetween('tanggal', [$curStart->toDateString(), $curEnd->toDateString()])
                        ->selectRaw('store_id, DATE(tanggal) as d, SUM(gmv) as gmv, SUM(pesanan) as pesanan')
                        ->groupBy('store_id', \DB::raw('DATE(tanggal)'))),
                ];
            }
        );

        $roasAgg = Cache::remember(
            'dashboard_roas_' . $dateKey,
            self::AGG_ROAS_CACHE_TTL,
            function () use ($curStart, $curEnd, $prevStart, $prevEnd) {
                $toArr = fn ($q) => $q->get()->map(fn ($m) => $m->getAttributes())->all();

                return [
                    'curAds' => $toArr(DailyAdStore::query()
                        ->whereBetween('tanggal', [$curStart->toDateString(), $curEnd->toDateString()])
                        ->selectRaw('store_id, SUM(cost) as total_cost, SUM(roi * cost) / NULLIF(SUM(cost), 0) as avg_roas')
                        ->groupBy('store_id')),

                    'prevAds' => $toArr(DailyAdStore::query()
                        ->whereBetween('tanggal', [$prevStart->toDateString(), $prevEnd->toDateString()])
                        ->selectRaw('store_id, SUM(cost) as total_cost, SUM(roi * cost) / NULLIF(SUM(cost), 0) as avg_roas')
                        ->groupBy('store_id')),
                ];
            }
        );

        $rebuild  = fn (array $rows) => collect($rows)->map(fn ($a) => (object) $a);
        $curSums  = $rebuild($gmvAgg['curSums'])->keyBy('store_id');
        $prevSums = $rebuild($gmvAgg['prevSums'])->keyBy('store_id');
        $curAds   = $rebuild($roasAgg['curAds'])->keyBy('store_id');
        $prevAds  = $rebuild($roasAgg['prevAds'])->keyBy('store_id');
        $chartRaw = $rebuild($gmvAgg['chartRaw']);

        $chartIdx        = [];
        $chartPesananIdx = [];
        foreach ($chartRaw as $row) {
            $chartIdx[$row->store_id][$row->d]        = (int) $row->gmv;
            $chartPesananIdx[$row->store_id][$row->d] = (int) $row->pesanan;
        }

        // Build rentang tanggal dari curStart → curEnd
        $dateRange = [];
        $rangeCursor = $curStart->copy();
        while ($rangeCursor->lte($curEnd)) {
            $dateRange[] = $rangeCursor->format('Y-m-d');
            $rangeCursor->addDay();
        }
        $totalDays = count($dateRange);

        // Thinning label agar tidak crowded di chart
        $labelStep = 1;
        if ($totalDays > 30) $labelStep = 7;
        elseif ($totalDays > 14) $labelStep = 3;
        elseif ($totalDays > 7)  $labelStep = 2;

        // ── Totals semua toko (sum koleksi — termasuk store_id NULL) ──────
        $totalRev  = (int) $curSums->sum('gmv');
        $totalOrd  = (int) $curSums->sum('pesanan');
        $totalRevP = (int) $prevSums->sum('gmv');
        $totalOrdP = (int) $prevSums->sum('pesanan');

        // ── Per-toko ─────────────────────────────────────────────────────
        $stores = collect(
            Cache::remember('stores_all', 3600,
                fn() => Store::select('id', 'name')->orderBy('id')
                    ->get()->map(fn($s) => $s->getAttributes())->all()
            )
        )->map(fn($a) => (object) $a);
        $storeInfo = [];

        foreach ($stores as $store) {
            $sid  = $store->id;
            $slug = self::SLUG_MAP[strtolower($store->name)]
                ?? str_replace(' ', '-', strtolower($store->name));

            $cur    = $curSums->get($sid);
            $prev   = $prevSums->get($sid);
            $curAd  = $curAds->get($sid);
            $prevAd = $prevAds->get($sid);

            $rev  = (int) ($cur?->gmv      ?? 0);
            $ord  = (int) ($cur?->pesanan   ?? 0);
            $revP = (int) ($prev?->gmv      ?? 0);
            $ordP = (int) ($prev?->pesanan  ?? 0);

            $ads   = (int) ($curAd?->total_cost  ?? 0);
            $adsP  = (int) ($prevAd?->total_cost ?? 0);
            $roas  = $curAd?->avg_roas  !== null ? (float) $curAd->avg_roas  : null;
            $roasP = $prevAd?->avg_roas !== null ? (float) $prevAd->avg_roas : null;

            $revTrend  = $revP > 0 ? round(($rev - $revP) / $revP * 100) : 0;
            $ordTrend  = $ordP > 0 ? round(($ord - $ordP) / $ordP * 100) : 0;
            $adsTrend  = $adsP > 0 ? round(($ads - $adsP) / $adsP * 100) : 0;
            $roasTrend = ($roasP !== null && $roasP > 0 && $roas !== null)
                ? round(($roas - $roasP) / $roasP * 100) : 0;

            $storeInfo[$slug] = [
                'sid'         => $sid,
                'slug'        => $slug,
                'rev'         => $rev,
                'ord'         => $ord,
                'revP'        => $revP,
                'ordP'        => $ordP,
                'ads'         => $ads,
                'adsP'        => $adsP,
                'roas'        => $roas,
                'roasP'       => $roasP,
                'revTrend'    => $revTrend,
                'ordTrend'    => $ordTrend,
                'adsTrend'    => $adsTrend,
                'roasTrend'   => $roasTrend,
                'displayName' => strtoupper($store->name),
                'trendUp'     => $revTrend >= 0,
            ];
        }

        uasort($storeInfo, fn ($a, $b) => $b['rev'] - $a['rev']);

        $totalRevTrend = $totalRevP > 0 ? round(($totalRev - $totalRevP) / $totalRevP * 100) : 0;
        $totalOrdTrend = $totalOrdP > 0 ? round(($totalOrd - $totalOrdP) / $totalOrdP * 100) : 0;

        // ── Total ads spend & ROAS (weighted) ────────────────────────────
        $totalAds  = (int) $curAds->sum('total_cost');
        $totalAdsP = (int) $prevAds->sum('total_cost');

        $totalRoas = null;
        if ($totalAds > 0) {
            $weighted  = $curAds->sum(fn ($r) => (float) $r->total_cost * (float) ($r->avg_roas ?? 0));
            $totalRoas = $weighted / $totalAds;
        }
        $totalRoasP = null;
        if ($totalAdsP > 0) {
            $weightedP  = $prevAds->sum(fn ($r) => (float) $r->total_cost * (float) ($r->avg_roas ?? 0));
            $totalRoasP = $weightedP / $totalAdsP;
        }

        $totalAdsTrend  = $totalAdsP > 0 ? round(($totalAds - $totalAdsP) / $totalAdsP * 100) : 0;
        $totalRoasTrend = ($totalRoasP !== null && $totalRoasP > 0 && $totalRoas !== null)
            ? round(($totalRoas - $totalRoasP) / $totalRoasP * 100) : 0;

        // ── JS: DEFAULT_METRICS ──────────────────────────────────────────
        $jsDefaultMetrics = [
            'rev'    => $this->fmtRp($totalRev),
            'revUp'  => $totalRevTrend >= 0,
            'revT'   => $this->trendStr($totalRevTrend, '%', $trendLabel),
            'ord'    => number_format($totalOrd, 0, ',', '.'),
            'ordUp'  => $totalOrdTrend >= 0,
            'ordT'   => $this->trendStr($totalOrdTrend, '%', $trendLabel),
            'ads'    => $totalAds > 0 ? $this->fmtRp($totalAds) : 'Rp –',
            'adsUp'  => $totalAdsTrend >= 0,
            'adsT'   => $totalAds > 0 ? $this->trendStr($totalAdsTrend, '%', $trendLabel) : '–',
            'roas'   => $this->fmtRoas($totalRoas),
            'roasUp' => $totalRoasTrend >= 0,
            'roasT'  => $totalRoas !== null ? $this->trendStr($totalRoasTrend, '%', $trendLabel) : '–',
        ];

        // ── JS: STORES ───────────────────────────────────────────────────
        $jsStores = [];
        foreach ($storeInfo as $slug => $s) {
            $jsStores[] = [
                'id'       => $slug,
                'name'     => $s['displayName'],
                'rev'      => $s['rev'],
                'orders'   => $s['ord'],
                'adspend'  => $s['ads'],
                'roas'     => $s['roas'],
                'trend'    => $s['revTrend'],
                'trendUp'  => $s['trendUp'],
                'platform' => 'tiktok',
            ];
        }

        // ── JS: STORE_METRICS ────────────────────────────────────────────
        $jsStoreMetrics = [];
        foreach ($storeInfo as $slug => $s) {
            $jsStoreMetrics[$slug] = [
                'rev'    => $this->fmtRp($s['rev']),
                'revUp'  => $s['revTrend'] >= 0,
                'revT'   => $this->trendStr($s['revTrend'], '%', $trendLabel),
                'ord'    => number_format($s['ord'], 0, ',', '.'),
                'ordUp'  => $s['ordTrend'] >= 0,
                'ordT'   => $this->trendStr($s['ordTrend'], '%', $trendLabel),
                'ads'    => $s['ads'] > 0 ? $this->fmtRp($s['ads']) : 'Rp –',
                'adsUp'  => $s['adsTrend'] >= 0,
                'adsT'   => $s['ads'] > 0 ? $this->trendStr($s['adsTrend'], '%', $trendLabel) : '–',
                'roas'   => $this->fmtRoas($s['roas']),
                'roasUp' => $s['roasTrend'] >= 0,
                'roasT'  => $s['roas'] !== null ? $this->trendStr($s['roasTrend'], '%', $trendLabel) : '–',
            ];
        }

        // ── Labels: selalu format tanggal (D MMM) ────────────────────────
        $chartLabels = [];
        foreach ($dateRange as $i => $date) {
            if ($i % $labelStep === 0 || $i === $totalDays - 1) {
                $chartLabels[] = Carbon::parse($date)->locale('id')->isoFormat('D MMM');
            } else {
                $chartLabels[] = '';
            }
        }

        // ── JS: CHART_DAILY (harian per toko, gmv ÷ 1000) ───────────────
        $chartDaily = ['labels' => $chartLabels];
        foreach ($storeInfo as $slug => $s) {
            $line = [];
            foreach ($dateRange as $date) {
                $line[] = (int) round(($chartIdx[$s['sid']][$date] ?? 0) / 1000);
            }
            $chartDaily[$slug] = $line;
        }

        // ── JS: CHART_WEEKLY (mingguan per toko, gmv ÷ 1000) ───────────
        $numWeeks    = max(1, (int) ceil($totalDays / 7));
        $chartWeekly = ['labels' => []];
        for ($w = 0; $w < $numWeeks; $w++) {
            $chartWeekly['labels'][] = 'Mg ' . ($w + 1);
        }
        foreach ($storeInfo as $slug => $s) {
            $weeks = array_fill(0, $numWeeks, 0);
            foreach ($dateRange as $i => $date) {
                $wIdx = (int) floor($i / 7);
                if ($wIdx < $numWeeks) {
                    $weeks[$wIdx] += $chartIdx[$s['sid']][$date] ?? 0;
                }
            }
            $chartWeekly[$slug] = array_map(fn ($v) => (int) round($v / 1000), $weeks);
        }

        // ── JS: CHART_METRIC_DATA (total harian & mingguan: gmv + pesanan) ─
        $dailyGmv     = array_fill(0, $totalDays, 0);
        $dailyPesanan = array_fill(0, $totalDays, 0);
        foreach ($storeInfo as $slug => $s) {
            foreach ($dateRange as $i => $date) {
                $dailyGmv[$i]     += $chartIdx[$s['sid']][$date]        ?? 0;
                $dailyPesanan[$i] += $chartPesananIdx[$s['sid']][$date] ?? 0;
            }
        }

        $weeklyGmv     = array_fill(0, $numWeeks, 0);
        $weeklyPesanan = array_fill(0, $numWeeks, 0);
        foreach ($dateRange as $i => $date) {
            $wIdx = (int) floor($i / 7);
            if ($wIdx < $numWeeks) {
                $weeklyGmv[$wIdx]     += $dailyGmv[$i];
                $weeklyPesanan[$wIdx] += $dailyPesanan[$i];
            }
        }

        $chartMetricData = [
            'daily' => [
                'labels'  => $chartLabels,
                'gmv'     => array_map(fn ($v) => (int) round($v / 1000), $dailyGmv),
                'pesanan' => $dailyPesanan,
            ],
            'weekly' => [
                'labels'  => $chartWeekly['labels'],
                'gmv'     => array_map(fn ($v) => (int) round($v / 1000), $weeklyGmv),
                'pesanan' => $weeklyPesanan,
            ],
        ];

        // ── JS: CHART_STORE_METRIC_DATA (per toko: gmv + pesanan) ───────
        $chartStoreMetricData = [];
        foreach ($storeInfo as $slug => $s) {
            $sGmvD = $sPesD = [];
            $sGmvW = array_fill(0, $numWeeks, 0);
            $sPesW = array_fill(0, $numWeeks, 0);
            foreach ($dateRange as $i => $date) {
                $g = $chartIdx[$s['sid']][$date]        ?? 0;
                $p = $chartPesananIdx[$s['sid']][$date] ?? 0;
                $sGmvD[] = (int) round($g / 1000);
                $sPesD[] = (int) $p;
                $wIdx = (int) floor($i / 7);
                if ($wIdx < $numWeeks) { $sGmvW[$wIdx] += $g; $sPesW[$wIdx] += $p; }
            }
            $chartStoreMetricData[$slug] = [
                'daily'  => ['gmv' => $sGmvD, 'pesanan' => $sPesD],
                'weekly' => [
                    'gmv'     => array_map(fn ($v) => (int) round($v / 1000), $sGmvW),
                    'pesanan' => $sPesW,
                ],
            ];
        }

        // ── Dropdown options ─────────────────────────────────────────────
        $storeOptions = [];
        foreach ($storeInfo as $slug => $s) {
            $storeOptions[$slug] = $s['displayName'];
        }

        $jsColors      = self::COLORS;
        $currentPeriod = $period;

        // ── Ads Performance per produk ────────────────────────────────────
        // Status diturunkan dari SQL GROUP BY (MAX priority) — tidak perlu PHP groupBy/map.
        // Prioritas: active=3 > completed=2 > stopped=1
        $adStatusMap = collect(
            Cache::remember('dashboard_ad_status_map', self::AGG_CACHE_TTL, function () {
                return \DB::table('product_ads as pa')
                    ->join('product_ad_store as pas', 'pas.product_ad_id', '=', 'pa.id')
                    ->selectRaw("CONCAT(pa.product_id, '_', pas.store_id) as map_key,
                        CASE
                            WHEN MAX(CASE pa.status WHEN 'active' THEN 3 WHEN 'completed' THEN 2 ELSE 1 END) = 3 THEN 'active'
                            WHEN MAX(CASE pa.status WHEN 'active' THEN 3 WHEN 'completed' THEN 2 ELSE 1 END) = 2 THEN 'completed'
                            ELSE 'stopped'
                        END as status")
                    ->groupBy('pa.product_id', 'pas.store_id')
                    ->pluck('status', 'map_key')
                    ->all();
            })
        );

        // Agregasi ROAS berat (data batch harian) di-cache sebagai array; status digabung live di bawah.
        // Limit 50: dashboard hanya menampilkan top items, tidak perlu seluruh produk×toko.
        $adsRoasArr = Cache::remember(
            'dashboard_ads_roas_' . $curStart->toDateString() . '_' . $curEnd->toDateString(),
            self::AGG_CACHE_TTL,
            fn() => DailyProductAd::query()
                ->whereBetween('tanggal', [$curStart->toDateString(), $curEnd->toDateString()])
                ->join('products', 'products.id', '=', 'daily_product_ads.product_id')
                ->join('stores', 'stores.id', '=', 'daily_product_ads.store_id')
                ->selectRaw('daily_product_ads.product_id, daily_product_ads.store_id,
                             products.parent_sku, stores.name as store_name,
                             AVG(daily_product_ads.roi) as avg_roas')
                ->groupBy('daily_product_ads.product_id', 'daily_product_ads.store_id',
                          'products.parent_sku', 'stores.name')
                ->orderByDesc('avg_roas')
                ->limit(50)
                ->get()
                ->map(fn ($m) => $m->getAttributes())
                ->all()
        );

        $adsPerformance = collect($adsRoasArr)
            ->map(fn ($a) => (object) $a)
            ->map(function ($row) use ($adStatusMap) {
                $roas   = $row->avg_roas !== null ? (float) $row->avg_roas : null;
                $status = $adStatusMap->get($row->product_id . '_' . $row->store_id, 'stopped');
                return [
                    'campaign'   => strtoupper($row->parent_sku),
                    'store_name' => strtoupper($row->store_name),
                    'roas'       => $roas,
                    'roas_fmt'   => $roas !== null ? number_format($roas, 1, ',', '.') . 'x' : '–',
                    'low_roas'   => $roas !== null && $roas < 3,
                    'status'     => $status,
                ];
            })
            ->all();

        // ── Stock Alerts ─────────────────────────────────────────────────
        // Bagian ini memanggil ERP (lambat). Tidak di-hitung di sini agar dashboard
        // render instan; diisi lewat AJAX ke dashboard.stock-alerts (lihat view).

        return view('dashboard.index', compact(
            'jsStores', 'jsDefaultMetrics', 'jsStoreMetrics',
            'chartDaily', 'chartWeekly', 'chartMetricData', 'chartStoreMetricData',
            'storeOptions', 'jsColors',
            'currentPeriod', 'periodLabel',
            'curStart', 'curEnd', 'dateFrom', 'dateTo',
            'adsPerformance'
        ));
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
            ->select('id', 'parent_sku', 'category_id')
            ->with([
                'productAds:id,product_id',
                'productAds.stores:id,name',
                'category:id,name',
            ])
            ->whereHas('productAds')
            ->limit(100)
            ->get();

        $parentSkus = $products->pluck('parent_sku')->unique()->values()->all();

        if (!empty($parentSkus)) {
            // Stok + HPP dari tabel lokal jubelio_inventory (disync berkala via
            // jubelio:sync-inventory) — query DB murah, tidak perlu cache/live API lagi.
            $rows = JubelioInventory::whereIn('parent_sku', array_map('strtoupper', $parentSkus))->get();

            if ($rows->isEmpty() && JubelioInventory::count() === 0) {
                // Tabel belum pernah disync sama sekali
                $stockApiUnavailable = true;
            }

            if (!$stockApiUnavailable) {
                $stockData = [];
                $hppMap    = [];
                foreach ($rows as $row) {
                    $stockData[$row->parent_sku][] = ['sku' => $row->sku_code, 'qty' => $row->stok];
                    $hppMap[$row->sku_code]         = $row->hpp;
                }

                foreach ($products as $product) {
                    $parentSku     = $product->parent_sku;
                    $allVariants   = $stockData[strtoupper($parentSku)] ?? [];
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
                        'category'     => $product->category?->name ?? '',
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
                if (in_array($row->parent_sku, array_map('strtoupper', $alertSkus), true)) {
                    $poData[$row->parent_sku][$row->sku_code] = $row->po_qty;
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

                $variantPoMap = $poData[strtoupper($_a['parent_sku'])] ?? [];

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

    private function fmtRp(int $n): string
    {
        if ($n >= 1_000_000) {
            return 'Rp ' . number_format($n / 1_000_000, 1, ',', '.') . ' jt';
        }
        if ($n >= 1_000) {
            return 'Rp ' . number_format($n / 1_000, 0, ',', '.') . ' rb';
        }
        return 'Rp ' . number_format($n, 0, ',', '.');
    }

    private function fmtRoas(?float $roas): string
    {
        if ($roas === null) return '–';
        return number_format($roas, 1, ',', '.') . 'x';
    }

    private function trendStr(int $val, string $unit, string $suffix = '', int $decimals = 0): string
    {
        $arrow   = $val >= 0 ? '↑' : '↓';
        $display = number_format(abs($val) / ($decimals > 0 ? 10 ** $decimals : 1), $decimals, ',', '.');
        return trim("{$arrow} {$display}{$unit} {$suffix}");
    }
}
