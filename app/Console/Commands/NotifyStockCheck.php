<?php

namespace App\Console\Commands;

use App\Models\JubelioInventory;
use App\Models\NotificationLog;
use App\Models\ProductAd;
use App\Models\User;
use App\Notifications\StockAlertNotification;
use App\Services\DailySalesQueryService;
use Illuminate\Console\Command;

class NotifyStockCheck extends Command
{
    protected $signature   = 'notify:stock-check';
    protected $description = 'Cek stok produk aktif: best seller tanpa PO (urgent) dan stok rendah yg sudah ada PO (info)';

    public function handle(DailySalesQueryService $dailySalesQueryService): int
    {
        $this->info('[Stock Check] Mengambil produk aktif...');

        $parentSkus = ProductAd::where('status', 'active')
            ->with('product:id,parent_sku')
            ->get()
            ->pluck('product.parent_sku')
            ->unique()
            ->filter()
            ->values()
            ->all();

        if (empty($parentSkus)) {
            $this->info('[Stock Check] Tidak ada produk aktif.');
            return self::SUCCESS;
        }

        $this->info('[Stock Check] Ambil stok+PO dari jubelio_inventory untuk ' . count($parentSkus) . ' parent SKU...');

        // Stok + PO dari tabel lokal jubelio_inventory (disync jubelio:sync-inventory).
        // Kolom DB selalu uppercase -> map balik ke casing asli $parentSkus.
        $rows = JubelioInventory::whereIn('parent_sku', array_map('strtoupper', $parentSkus))->get();

        $stockByUpper = [];
        $poByUpper    = [];
        foreach ($rows as $row) {
            $stockByUpper[$row->parent_sku][]            = ['sku' => $row->sku_code, 'qty' => $row->stok];
            $poByUpper[$row->parent_sku][$row->sku_code]  = $row->po_qty;
        }

        $salesTotal = $dailySalesQueryService->getForParentSkus($parentSkus)['total'];

        // Part 1: best seller (ada penjualan 90 hari) + stok rendah + TANPA PO → urgent, perlu tindakan
        $urgentNoPo = [];
        // Part 2: stok rendah + SUDAH ADA PO → info saja
        $withPo     = [];

        foreach ($parentSkus as $parentSku) {
            $variants = $stockByUpper[strtoupper($parentSku)] ?? [];
            if (empty($variants)) {
                continue;
            }

            $poMap    = $poByUpper[strtoupper($parentSku)] ?? [];
            $sales90d = $salesTotal[$parentSku]['90d']     ?? [];

            foreach ($variants as $v) {
                $sku          = $v['sku'];
                $qty          = (int) $v['qty'];
                $po           = (int) ($poMap[$sku] ?? 0);
                $isBestSeller = ($sales90d[$sku] ?? 0) > 0;
                $isCritical   = $qty <= 100;
                $isLow        = $qty <= 300;

                if (!$isLow) {
                    continue;
                }

                if ($po > 0 && $isBestSeller) {
                    // Best seller + sudah ada PO masuk → info saja
                    if (!NotificationLog::isOnCooldown('stock_with_po', $sku)) {
                        $withPo[] = [
                            'sku'        => $sku,
                            'qty'        => $qty,
                            'po'         => $po,
                            'parent_sku' => $parentSku,
                            'critical'   => $isCritical,
                        ];
                    }
                } elseif ($isBestSeller) {
                    // Best seller + stok rendah + tanpa PO → harus segera ditindak
                    if (!NotificationLog::isOnCooldown('stock_urgent', $sku)) {
                        $urgentNoPo[] = [
                            'sku'        => $sku,
                            'qty'        => $qty,
                            'parent_sku' => $parentSku,
                            'critical'   => $isCritical,
                        ];
                    }
                }
                // Non-best-seller + stok rendah + tanpa PO: diabaikan (tidak cukup urgen)
            }
        }

        if (empty($urgentNoPo) && empty($withPo)) {
            $this->info('[Stock Check] Tidak ada alert baru (semua dalam cooldown atau stok aman).');
            return self::SUCCESS;
        }

        $this->warn('[Stock Check] Kirim email: ' . count($urgentNoPo) . ' urgent (best seller tanpa PO), ' . count($withPo) . ' info (sudah ada PO).');

        \Illuminate\Support\Facades\Notification::route('mail', config('mail.to'))
            ->notify(new StockAlertNotification($urgentNoPo, $withPo));

        foreach ($urgentNoPo as $v) {
            NotificationLog::record('stock_urgent', $v['sku']);
        }
        foreach ($withPo as $v) {
            NotificationLog::record('stock_with_po', $v['sku']);
        }

        $this->info('[Stock Check] Selesai.');
        return self::SUCCESS;
    }
}
