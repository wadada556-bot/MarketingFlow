<?php

namespace App\Console\Commands;

use App\Models\SalesSyncState;
use App\Services\SalesSyncService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SyncSales extends Command
{
    protected $signature = 'sales:sync
        {--days=30 : Jumlah hari ke belakang yang di-scan ulang}
        {--delay=400 : Jeda antar panggilan detail (ms) untuk menahan rate-limit}';

    protected $description = 'Sync penjualan TikTok+Tokopedia (rolling N hari terakhir) ke daily_sku_sales';

    public function handle(SalesSyncService $sales): int
    {
        $days = max(1, (int) $this->option('days'));
        $to   = Carbon::now('Asia/Jakarta')->endOfDay();
        $from = $to->copy()->subDays($days - 1)->startOfDay();

        $this->info("[Sales Sync] Scan {$from->toDateString()} s/d {$to->toDateString()} ...");

        $state = SalesSyncState::firstOrCreate(['key' => 'daily']);
        $state->update(['status' => 'running']);

        try {
            $res = $sales->setDetailDelayMs((int) $this->option('delay'))
                ->syncRange($from, $to, SalesSyncService::CHANNELS, function ($page, $collected, $total) {
                    $this->line("  halaman {$page}: {$collected}/{$total} order");
                });
        } catch (\Throwable $e) {
            $state->update(['status' => 'failed', 'note' => $e->getMessage()]);
            $this->error("[Sales Sync] Gagal: {$e->getMessage()}");
            return self::FAILURE;
        }

        $state->update([
            'status'         => 'done',
            'last_synced_at' => now(),
            'note'           => "rolling {$days}h: {$res['orders']} order, {$res['rows']} baris",
        ]);

        $this->info("[Sales Sync] Selesai: {$res['orders']} order diproses, {$res['rows']} baris ditulis.");

        return self::SUCCESS;
    }
}
