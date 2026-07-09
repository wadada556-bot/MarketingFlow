@php
    $rp = fn ($n) => 'Rp' . number_format((int) $n, 0, ',', '.');
    $cost   = (int) ($perf->cost ?? 0);
    $gmv    = (int) ($perf->gmv ?? 0);
    $orders = (int) ($perf->orders ?? 0);
    $cpo    = $perf->cost_per_order ?? null;   // NULL bila 0 pesanan
@endphp

{{-- Performa iklan periode terpilih. Dipindah ke sini dari kolom tabel supaya
     tabel tidak melebar ke samping. --}}
<div class="d-flex align-items-center justify-content-between mb-3" style="gap:12px;flex-wrap:wrap">
    <p class="mb-0" style="font-size:11px;font-weight:500;letter-spacing:.8px;text-transform:uppercase;color:var(--md-on-surface-variant)">
        <i class="bi bi-graph-up me-1"></i> Performa Iklan
    </p>
    <div class="d-flex gap-2 flex-wrap">
        @if($cost > 0 || $gmv > 0 || $orders > 0)
            <span class="md-chip surface" style="font-size:11px">Biaya: {{ $rp($cost) }}</span>
            <span class="md-chip surface" style="font-size:11px">GMV: {{ $rp($gmv) }}</span>
            <span class="md-chip surface" style="font-size:11px">Pesanan: {{ number_format($orders, 0, ',', '.') }}</span>
            <span class="md-chip surface" style="font-size:11px">
                Biaya/Pesanan: {{ $cpo !== null ? $rp($cpo) : '—' }}
            </span>
        @else
            <span class="md-chip surface" style="font-size:11px">Belum ada belanja iklan di periode ini</span>
        @endif
    </div>
</div>

{!! $variantsHtml !!}
