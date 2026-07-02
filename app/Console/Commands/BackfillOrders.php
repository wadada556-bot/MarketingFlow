<?php

namespace App\Console\Commands;

use App\Models\SalesSyncState;
use App\Services\OrderSyncService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class BackfillOrders extends Command
{
    protected $signature = 'orders:backfill
        {--from=2026-01-01 : Tanggal mulai (WIB)}
        {--to= : Tanggal akhir (WIB), default hari ini}
        {--concurrency=12 : Jumlah detail order diambil paralel per batch}
        {--fresh : Abaikan checkpoint, mulai dari awal}';

    protected $description = 'Backfill histori pesanan masuk per-hari (resumable) ke tabel orders';

    public function handle(OrderSyncService $orders): int
    {
        $from = Carbon::parse($this->option('from'), 'Asia/Jakarta')->startOfDay();
        $to   = $this->option('to')
            ? Carbon::parse($this->option('to'), 'Asia/Jakarta')->endOfDay()
            : Carbon::now('Asia/Jakarta')->endOfDay();

        if ($from->gt($to)) {
            $this->error('Tanggal --from melebihi --to.');
            return self::FAILURE;
        }

        $orders->setConcurrency((int) $this->option('concurrency'));

        $state = SalesSyncState::firstOrCreate(['key' => 'orders:backfill']);
        if ($this->option('fresh')) {
            $state->update(['cursor_date' => null, 'status' => 'idle', 'note' => null]);
        }
        // cursor_date = hari terakhir yang SUDAH selesai (kita jalan dari baru ke lama)
        $resumeBefore = (! $this->option('fresh') && $state->cursor_date)
            ? $state->cursor_date->copy()
            : null;

        $days = $this->dayRanges($from, $to); // urut terbaru -> terlama
        $this->info('[Backfill Orders] ' . count($days) . " hari dari {$from->toDateString()} s/d {$to->toDateString()}"
            . ($resumeBefore ? " (lanjut dari sebelum {$resumeBefore->toDateString()})" : ''));

        $state->update(['status' => 'running']);
        $grandOrders = 0;
        $grandRows   = 0;

        foreach ($days as [$dStart, $dEnd]) {
            $tgl = $dStart->toDateString();

            if ($resumeBefore && $dStart->gte($resumeBefore)) {
                $this->line("  lewati {$tgl} (sudah diproses)");
                continue;
            }

            $this->info("  > {$tgl}");

            try {
                $res = $orders->syncRange($dStart, $dEnd, OrderSyncService::CHANNELS, function (string $msg) {
                    $this->line("      {$msg}");
                });
            } catch (\Throwable $e) {
                $state->update(['status' => 'failed', 'note' => "{$tgl}: {$e->getMessage()}"]);
                $this->error("  Gagal di {$tgl}: {$e->getMessage()}");
                $this->warn('  Jalankan ulang `orders:backfill` untuk melanjutkan dari titik ini.');
                return self::FAILURE;
            }

            $grandOrders += $res['orders'];
            $grandRows   += $res['rows'];

            $state->update([
                'cursor_date'    => $tgl,
                'last_synced_at' => now(),
                'note'           => "{$tgl}: {$res['fetched']} fetch, {$res['skipped']} skip, {$res['rows']} baris",
            ]);

            $this->info("    selesai {$tgl}: {$res['fetched']} fetch, {$res['skipped']} skip, {$res['failed']} gagal, {$res['rows']} baris");
        }

        $state->update(['status' => 'done', 'last_synced_at' => now()]);
        $this->info("[Backfill Orders] SELESAI. Total {$grandOrders} order, {$grandRows} baris.");

        return self::SUCCESS;
    }

    /**
     * Daftar rentang per HARI dalam [$from,$to], urut terbaru -> terlama.
     * Per-hari agar tulis & checkpoint sering (tahan putus pada volume besar).
     *
     * @return array<array{0: Carbon, 1: Carbon}>
     */
    private function dayRanges(Carbon $from, Carbon $to): array
    {
        $ranges = [];
        $cursor = $to->copy()->startOfDay();
        $floor  = $from->copy()->startOfDay();

        while ($cursor->gte($floor)) {
            $ranges[] = [$cursor->copy()->startOfDay(), $cursor->copy()->endOfDay()];
            $cursor->subDay();
        }

        return $ranges;
    }
}
