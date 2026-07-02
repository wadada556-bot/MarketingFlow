<?php

namespace App\Console\Commands;

use App\Models\SalesSyncState;
use App\Services\OrderSyncService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SyncOrders extends Command
{
    protected $signature = 'orders:sync
        {--days=30 : Jumlah hari ke belakang yang di-scan ulang}
        {--concurrency=12 : Jumlah detail order diambil paralel per batch}';

    protected $description = 'Sync pesanan masuk (rolling N hari terakhir) ke tabel orders';

    public function handle(OrderSyncService $orders): int
    {
        $days = max(1, (int) $this->option('days'));
        $to   = Carbon::now('Asia/Jakarta')->endOfDay();
        $from = $to->copy()->subDays($days - 1)->startOfDay();

        $this->info("[Orders Sync] Scan {$from->toDateString()} s/d {$to->toDateString()} ...");

        $state = SalesSyncState::firstOrCreate(['key' => 'orders:daily']);
        $state->update(['status' => 'running']);

        try {
            $res = $orders->setConcurrency((int) $this->option('concurrency'))
                ->syncRange($from, $to, OrderSyncService::CHANNELS, function (string $msg) {
                    $this->line("  {$msg}");
                });
        } catch (\Throwable $e) {
            $state->update(['status' => 'failed', 'note' => $e->getMessage()]);
            $this->error("[Orders Sync] Gagal: {$e->getMessage()}");
            return self::FAILURE;
        }

        $state->update([
            'status'         => 'done',
            'last_synced_at' => now(),
            'note'           => "rolling {$days}h: {$res['fetched']} fetch, {$res['skipped']} skip, {$res['rows']} baris",
        ]);

        $this->info("[Orders Sync] Selesai: {$res['orders']} order dalam rentang, "
            . "{$res['fetched']} di-fetch, {$res['skipped']} tak berubah, {$res['failed']} gagal, {$res['rows']} baris ditulis.");

        return self::SUCCESS;
    }
}
