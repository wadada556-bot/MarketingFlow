<?php

namespace App\Http\Controllers;

use App\Http\Requests\FilterDateRangeRequest;
use App\Models\DailyAdStore;
use App\Models\Product;
use App\Models\Store;
use App\Models\DailyStoreStat;
use App\Services\ErpApiService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    private const QTY_DANGER      = 20;
    private const QTY_WARNING     = 100;
    private const STOCK_CACHE_TTL = 900;

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

    public function __construct(private readonly ErpApiService $erpApiService) {}

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

        // ── Aggregasi DB: periode ini & periode pembanding ────────────────
        // SUM langsung di DB: benar meski store_id NULL, atau ada duplikat tanggal
        $curSums = DailyStoreStat::query()
            ->whereBetween('tanggal', [$curStart->toDateString(), $curEnd->toDateString()])
            ->selectRaw('store_id, SUM(gmv) as gmv, SUM(pesanan) as pesanan')
            ->groupBy('store_id')
            ->get()
            ->keyBy('store_id');

        $prevSums = DailyStoreStat::query()
            ->whereBetween('tanggal', [$prevStart->toDateString(), $prevEnd->toDateString()])
            ->selectRaw('store_id, SUM(gmv) as gmv, SUM(pesanan) as pesanan')
            ->groupBy('store_id')
            ->get()
            ->keyBy('store_id');

        // ── Aggregasi DB: ad spend & ROAS periode ini & pembanding ───────
        $curAds = DailyAdStore::query()
            ->whereBetween('tanggal', [$curStart->toDateString(), $curEnd->toDateString()])
            ->selectRaw('store_id, SUM(cost) as total_cost, SUM(roi * cost) / NULLIF(SUM(cost), 0) as avg_roas')
            ->groupBy('store_id')
            ->get()
            ->keyBy('store_id');

        $prevAds = DailyAdStore::query()
            ->whereBetween('tanggal', [$prevStart->toDateString(), $prevEnd->toDateString()])
            ->selectRaw('store_id, SUM(cost) as total_cost, SUM(roi * cost) / NULLIF(SUM(cost), 0) as avg_roas')
            ->groupBy('store_id')
            ->get()
            ->keyBy('store_id');

        // ── Chart: data harian untuk periode yang dipilih ────────────────
        $chartRaw = DailyStoreStat::query()
            ->whereBetween('tanggal', [$curStart->toDateString(), $curEnd->toDateString()])
            ->selectRaw('store_id, DATE(tanggal) as d, SUM(gmv) as gmv, SUM(pesanan) as pesanan')
            ->groupBy('store_id', \DB::raw('DATE(tanggal)'))
            ->get();

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
        $stores    = Store::all();
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

        // ── Stock Alerts ─────────────────────────────────────────────────
        $stockAlerts         = [];
        $stockAlertCount     = 0;
        $stockApiUnavailable = false;

        $products = Product::with([
            'productAds' => fn ($q) => $q->with('stores:id,name'),
            'category:id,name',
        ])->whereHas('productAds')->get();

        $parentSkus = $products->pluck('parent_sku')->unique()->values()->all();

        if (!empty($parentSkus)) {
            $cacheKey = 'dashboard_stock_' . md5(implode(',', $parentSkus));
            $cached   = Cache::get($cacheKey);

            if ($cached !== null) {
                $stockData = $cached;
            } else {
                $fetched = $this->erpApiService->getStockByParentSkus($parentSkus);
                if (!empty($fetched)) {
                    Cache::put($cacheKey, $fetched, self::STOCK_CACHE_TTL);
                    $stockData = $fetched;
                } else {
                    $stockApiUnavailable = true;
                    $stockData = [];
                }
            }

            if (!$stockApiUnavailable) {
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

        return view('dashboard.index', compact(
            'jsStores', 'jsDefaultMetrics', 'jsStoreMetrics',
            'chartDaily', 'chartWeekly', 'chartMetricData', 'chartStoreMetricData',
            'storeOptions', 'jsColors',
            'stockAlerts', 'stockAlertCount', 'stockApiUnavailable',
            'currentPeriod', 'periodLabel',
            'curStart', 'curEnd', 'dateFrom', 'dateTo'
        ));
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
