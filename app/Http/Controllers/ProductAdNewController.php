<?php

namespace App\Http\Controllers;

use App\Models\Ad;
use App\Models\AdLog;
use App\Models\Store;
use App\Services\DailySalesQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Menu "Product Ads New" — laporan performa iklan per produk.
 *
 * Tabel utama = `ads` (identitas + status, satu baris per (store, product))
 * di-join dengan `ad_weekly_performances` (metrik minggu terpilih). Semua produk
 * beriklan muncul otomatis (baris `ads` dibuat importer) — tak ada pendaftaran.
 * Status/testing & catatan bisa diedit (milik aplikasi, importer tak menyentuhnya).
 */
class ProductAdNewController extends Controller
{
    public function __construct(
        private readonly DailySalesQueryService $dailySalesQueryService,
    ) {}

    /** Kolom yang boleh dipakai ORDER BY — jangan pernah dari input mentah. */
    private const SORTABLE = [
        'mulai'  => 'fs.mulai_iklan',
        'produk' => 'ads.product_id',
        'cost'   => 'perf.cost',
        'gmv'    => 'perf.gmv',
        'orders' => 'perf.orders_sku',
        'roi'    => 'roi',
    ];

    private const DEFAULT_ROI_THRESHOLD = 5.0;

    public function index(Request $request)
    {
        $periods   = $this->periods();
        $period    = $request->input('period') ?: ($periods->first()->period_start ?? null);
        $tab       = $request->input('testing_status', 'semua');
        $threshold = (float) ($request->input('roi_threshold') ?: self::DEFAULT_ROI_THRESHOLD);

        // null = belum ada klik header kolom -> pakai urutan default (lihat bawah).
        $sortKey = array_key_exists($request->input('sort'), self::SORTABLE)
            ? $request->input('sort') : null;
        $dir = $request->input('dir') === 'asc' ? 'asc' : 'desc';

        $filters = $request->only(['store', 'q', 'status']);

        $adsQuery = $this->adQuery($period)->filter($filters)->testingTab($tab);

        if ($sortKey === null) {
            // Default: iklan AKTIF dulu (yang stopped turun ke bawah, tak
            // bersaing perhatian), lalu ROI TERENDAH dulu -- baris yang kena
            // highlight merah (di bawah ambang) otomatis tampil paling atas.
            $adsQuery->orderByRaw("ads.status = 'active' DESC")
                ->orderByRaw('roi IS NULL')
                ->orderByRaw('roi ASC');
        } else {
            $col = self::SORTABLE[$sortKey];
            $adsQuery->orderByRaw("{$col} IS NULL")
                ->orderByRaw("{$col} {$dir}");
        }

        $ads = $adsQuery->orderBy('ads.id')
            ->paginate(20)
            ->withQueryString();

        $this->attachStockAndSkuCount($ads->getCollection());

        return view('product-ads-new.index', [
            'ads'        => $ads,
            'stores'     => Store::select('id', 'name')->orderBy('name')->get(),
            'periods'    => $periods,
            'period'     => $period,
            'currentTab' => $tab,
            'tabCounts'  => $this->tabCounts($period, $filters),
            'threshold'  => $threshold,
            'sortKey'    => $sortKey,
            'sortDir'    => $dir,
        ]);
    }

    /**
     * Lampirkan agregat stok + jumlah SKU + label "SKU Induk" ke tiap baris.
     * Sumber lokal jubelio_inventory (cepat) — jalur: product_id → tiktok_listings →
     * tiktok_listing_skus.sku_code → jubelio_inventory. Hanya untuk baris halaman ini.
     *
     * Label SKU Induk memakai aturan yang SAMA dengan menu Products
     * (ProductController::indukLabel) — bukan disalin, dipanggil langsung, agar kalau
     * aturannya berubah di sana, menu ini otomatis ikut tanpa perlu disentuh dua kali.
     */
    private function attachStockAndSkuCount($ads): void
    {
        if ($ads->isEmpty()) {
            return;
        }

        $rows = DB::table('tiktok_listings AS tl')
            ->join('tiktok_listing_skus AS tls', 'tls.listing_id', '=', 'tl.id')
            ->leftJoin('jubelio_inventory AS ji', 'ji.sku_code', '=', 'tls.sku_code')
            ->whereIn('tl.product_id', $ads->pluck('product_id')->all())
            ->orderBy('tls.sku_id')
            ->get(['tl.store_id', 'tl.product_id', 'tls.sku_code', 'ji.stok']);

        $grouped = $rows->groupBy(fn ($r) => $r->store_id . '-' . $r->product_id);

        foreach ($ads as $ad) {
            $group = $grouped->get($ad->store_id . '-' . $ad->product_id, collect());
            $ad->sku_count   = $group->pluck('sku_code')->unique()->count();
            $ad->agg_stock   = (int) $group->sum('stok');
            $ad->induk_label = ProductController::indukLabel($group->pluck('sku_code'));
        }
    }

    /** Periode mingguan yang tersedia, terbaru dulu (dari performa mingguan). */
    private function periods()
    {
        return DB::table('ad_weekly_performances')
            ->select('period_start', 'period_end')
            ->distinct()
            ->orderByDesc('period_start')
            ->get();
    }

    /**
     * `ads` + performa minggu terpilih. INNER JOIN ke performa: hanya iklan yang
     * benar-benar jalan di minggu itu yang tampil. Performa diagregasi per ad_id
     * di subquery (aman kalau kelak satu produk punya >1 campaign dalam seminggu).
     */
    private function adQuery(?string $period)
    {
        $perf = DB::table('ad_weekly_performances')
            ->when($period, fn ($q) => $q->where('period_start', $period))
            ->selectRaw('ad_id,
                         SUM(cost) AS cost,
                         SUM(gmv) AS gmv,
                         SUM(orders_sku) AS orders_sku,
                         COUNT(*) AS campaigns,
                         MIN(campaign_id) AS campaign_id,
                         MIN(campaign_name) AS campaign_name,
                         MIN(roi_protection) AS roi_protection')
            ->groupBy('ad_id');

        // "Mulai iklan" = minggu PALING AWAL ad ini punya data performa, lintas
        // SEMUA periode (bukan hanya yang dipilih). Dipakai untuk kolom Status
        // sekaligus urutan default (terbaru dulu).
        $firstSeen = DB::table('ad_weekly_performances')
            ->selectRaw('ad_id, MIN(period_start) AS mulai_iklan')
            ->groupBy('ad_id');

        return Ad::query()
            ->joinSub($perf, 'perf', 'perf.ad_id', '=', 'ads.id')
            ->joinSub($firstSeen, 'fs', 'fs.ad_id', '=', 'ads.id')
            ->with('store:id,name')
            ->select('ads.*')
            ->addSelect('perf.campaign_id', 'perf.campaign_name', 'perf.roi_protection', 'fs.mulai_iklan')
            ->selectRaw('perf.campaigns AS perf_campaigns')
            // ROI = GMV / cost. NULL bila belum ada belanja (bukan 0 = gagal total).
            ->selectRaw('CASE WHEN COALESCE(perf.cost,0) > 0
                              THEN ROUND(perf.gmv / perf.cost, 2) END AS roi');
    }

    private function tabCounts(?string $period, array $filters): array
    {
        $base = fn () => $this->adQuery($period)->filter($filters);

        return [
            'semua'       => $base()->count(),
            'perlu_dicek' => $base()->whereNull('ads.testing_status')->count(),
            'berhasil'    => $base()->where('ads.testing_status', 'success')->count(),
            'gagal'       => $base()->where('ads.testing_status', 'fail')->count(),
        ];
    }

    /**
     * Isi baris expand: performa minggu terpilih + tabel stok/HPP/penjualan/PO per SKU.
     * Partial varian dipakai ulang dari menu Product Ads lama. Varian sudah pasti
     * lewat product_id → tiktok_listings → tiktok_listing_skus.
     */
    public function variants(Request $request, Ad $ad): JsonResponse
    {
        $ad->loadMissing('store:id,name,jubelio_store_id');

        // ROUND (bukan FLOOR) biar cost_per_order sama dengan angka TikTok.
        $perf = DB::table('ad_weekly_performances')
            ->where('ad_id', $ad->id)
            ->when($request->input('period'), fn ($q, $p) => $q->where('period_start', $p))
            ->selectRaw('COALESCE(SUM(cost),0) cost, COALESCE(SUM(gmv),0) gmv,
                         COALESCE(SUM(orders_sku),0) orders,
                         ROUND(SUM(cost) / NULLIF(SUM(orders_sku), 0)) cost_per_order')
            ->first();

        $skus = DB::table('tiktok_listing_skus AS tls')
            ->join('tiktok_listings AS tl', 'tl.id', '=', 'tls.listing_id')
            ->where('tl.store_id', $ad->store_id)
            ->where('tl.product_id', $ad->product_id)
            ->orderBy('tls.sku_code')
            ->pluck('tls.sku_code')
            ->all();

        $inventory = $skus
            ? DB::table('jubelio_inventory')->whereIn('sku_code', $skus)
                ->select('sku_code', 'stok', 'hpp', 'po_qty')->get()
            : collect();

        $stockVariants = $inventory->map(fn ($r) => ['sku' => $r->sku_code, 'qty' => (int) $r->stok])->all();
        $hppMap        = $inventory->pluck('hpp', 'sku_code')->map(fn ($v) => (int) $v)->all();
        $variantPo     = $inventory->pluck('po_qty', 'sku_code')->map(fn ($v) => (int) $v)
            ->filter(fn ($v) => $v > 0)->all();

        $variantSales = $this->dailySalesQueryService->getForSkus(
            $skus,
            $ad->store->jubelio_store_id ?? null,
        );

        return response()->json([
            'html' => view('product-ads-new.partials._expand', [
                'perf'         => $perf,
                'variantsHtml' => view('product-ads.partials._variant-stock-table', compact(
                    'stockVariants', 'variantSales', 'variantPo', 'hppMap',
                ))->render(),
            ])->render(),
        ]);
    }

    /** Toggle active <-> stopped. Dipakai switch di kolom Status. */
    public function toggleStatus(Ad $ad)
    {
        $ad->update(['status' => $ad->status === 'active' ? 'stopped' : 'active']);

        return back()->with('success', 'Status iklan diubah menjadi ' . ($ad->status === 'active' ? 'Active' : 'Stopped') . '.');
    }

    public function markSuccess(Ad $ad)
    {
        $ad->update(['testing_status' => 'success', 'testing_completed_at' => now()]);

        return back()->with('success', 'Iklan ditandai Berhasil.');
    }

    public function markFail(Ad $ad)
    {
        $ad->update(['testing_status' => 'fail', 'testing_completed_at' => now()]);

        return back()->with('success', 'Iklan ditandai Gagal.');
    }

    public function extend(Request $request, Ad $ad)
    {
        $request->validate([
            'days'        => ['required'],
            'custom_days' => ['required_if:days,custom', 'nullable', 'integer', 'min:1', 'max:365'],
        ]);

        $days = $request->days === 'custom' ? (int) $request->custom_days : (int) $request->days;

        $deadline = $ad->testing_completed_at ?: now();
        $newDeadline = $deadline->isPast() ? now()->addDays($days) : $deadline->copy()->addDays($days);

        $ad->update([
            'testing_status'       => null,   // NULL = masa testing (enum hanya success/fail)
            'testing_started_at'   => $ad->testing_started_at ?: now(),
            'testing_completed_at' => $newDeadline,
        ]);

        return back()->with('success', "Masa testing diperpanjang {$days} hari.");
    }

    public function storeLog(Request $request, Ad $ad)
    {
        $data = $request->validate([
            'action_date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:2000'],
        ]);

        $ad->logs()->create($data);

        return back()->with('success', 'Catatan ditambahkan.');
    }

    public function updateLog(Request $request, AdLog $adLog)
    {
        $data = $request->validate([
            'action_date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:2000'],
        ]);

        $adLog->update($data);

        return back()->with('success', 'Catatan diperbarui.');
    }

    public function logs(Ad $ad): JsonResponse
    {
        return response()->json([
            'html' => view('product-ads-new.partials._logs', [
                'logs' => $ad->logs()->get(),
                'ad'   => $ad,
            ])->render(),
        ]);
    }

    public function destroyLog(AdLog $adLog)
    {
        $adLog->delete();

        return back()->with('success', 'Catatan dihapus.');
    }
}
