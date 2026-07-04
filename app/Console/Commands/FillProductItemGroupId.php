<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Mengisi products.item_group_id yang masih NULL dengan cara mencocokkan
 * parent_sku ke parent_sku paling mirip di jubelio_inventory (metrik
 * similar_text), lalu mengambil MODUS item_group_id dari baris yang cocok.
 *
 * Kalau kemiripan terbaik < ambang (--threshold, default 60%), produk
 * dilewati (dibiarkan NULL) agar tidak menetapkan grup yang keliru.
 *
 * Dry-run secara default; tambahkan --apply untuk benar-benar menulis.
 */
class FillProductItemGroupId extends Command
{
    protected $signature = 'products:fill-item-group
        {--apply : Benar-benar menulis ke DB (tanpa ini hanya pratinjau)}
        {--threshold=60 : Ambang kemiripan minimum (persen) agar sebuah match dipakai}';

    protected $description = 'Isi products.item_group_id yang NULL dari parent_sku termirip di jubelio_inventory (modus)';

    public function handle(): int
    {
        $apply     = (bool) $this->option('apply');
        $threshold = (float) $this->option('threshold');

        $nulls = DB::table('products')->whereNull('item_group_id')->get(['id', 'parent_sku']);
        if ($nulls->isEmpty()) {
            $this->info('Tidak ada products dengan item_group_id NULL. Selesai.');
            return self::SUCCESS;
        }

        $parents = DB::table('jubelio_inventory')
            ->select('parent_sku')->distinct()->pluck('parent_sku')->filter()->all();

        if (empty($parents)) {
            $this->warn('jubelio_inventory kosong — tidak ada kandidat untuk dicocokkan.');
            return self::SUCCESS;
        }

        $this->line(sprintf(
            '%s | %d produk NULL | %d parent_sku kandidat | ambang %.0f%%',
            $apply ? 'APPLY' : 'DRY-RUN',
            $nulls->count(),
            count($parents),
            $threshold
        ));
        $this->newLine();

        $rows        = [];
        $assignCount = 0;

        foreach ($nulls as $p) {
            [$bestPct, $cands] = $this->bestMatches($p->parent_sku, $parents);
            $igid = $this->modeItemGroupId($cands);

            $willAssign = $bestPct >= $threshold && $igid !== null;

            if ($willAssign && $apply) {
                DB::table('products')->where('id', $p->id)->whereNull('item_group_id')
                    ->update(['item_group_id' => $igid, 'updated_at' => now()]);
                $assignCount++;
            } elseif ($willAssign) {
                $assignCount++;
            }

            $rows[] = [
                $p->id,
                $p->parent_sku,
                $this->preview($cands),
                round($bestPct) . '%',
                $willAssign ? $igid : '— (skip)',
            ];
        }

        $this->table(['id', 'parent_sku', 'match termirip', 'skor', 'item_group_id'], $rows);
        $this->newLine();

        if ($apply) {
            $this->info("Selesai: {$assignCount} produk di-assign, " . ($nulls->count() - $assignCount) . ' dilewati.');
        } else {
            $this->warn("Pratinjau saja. {$assignCount} akan di-assign, " . ($nulls->count() - $assignCount) . ' dilewati.');
            $this->line('Jalankan lagi dengan --apply untuk menulis perubahan.');
        }

        return self::SUCCESS;
    }

    /**
     * Kembalikan [skor tertinggi, daftar parent_sku yang seri di skor itu].
     */
    private function bestMatches(string $sku, array $parents): array
    {
        $target  = strtoupper($sku);
        $bestPct = -1.0;
        $cands   = [];

        foreach ($parents as $cand) {
            similar_text($target, strtoupper((string) $cand), $pct);
            if ($pct > $bestPct) {
                $bestPct = $pct;
                $cands   = [$cand];
            } elseif ($pct === $bestPct) {
                $cands[] = $cand;
            }
        }

        return [$bestPct, $cands];
    }

    /** Modus item_group_id dari semua baris inventory milik parent_sku kandidat. */
    private function modeItemGroupId(array $parentList): ?int
    {
        if (empty($parentList)) {
            return null;
        }

        $vals = DB::table('jubelio_inventory')
            ->whereIn('parent_sku', $parentList)
            ->whereNotNull('item_group_id')
            ->pluck('item_group_id')
            ->map(fn ($v) => (string) $v)
            ->all();

        if (empty($vals)) {
            return null;
        }

        $counts = array_count_values($vals);
        arsort($counts);

        return (int) array_key_first($counts);
    }

    private function preview(array $cands): string
    {
        $shown = array_slice($cands, 0, 3);
        $extra = count($cands) - count($shown);

        return implode(', ', $shown) . ($extra > 0 ? " …(+{$extra})" : '');
    }
}
