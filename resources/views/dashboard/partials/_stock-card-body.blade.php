@php
    $stockAlerts         = $stockAlerts ?? [];
    $stockAlertCount     = $stockAlertCount ?? 0;
    $stockApiUnavailable = $stockApiUnavailable ?? false;
@endphp
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
    <div>
        <p class="dash-section-title">Peringatan Stok</p>
        <p class="dash-section-subtitle">Produk butuh perhatian</p>
    </div>
    @if($stockApiUnavailable)
        <span class="md-chip surface"><i class="bi bi-wifi-off" style="font-size:11px"></i> Offline</span>
    @elseif($stockAlertCount > 0)
        <span class="md-chip warning"><i class="bi bi-exclamation-triangle" style="font-size:11px"></i> {{ $stockAlertCount }} item</span>
    @else
        <span class="md-chip primary"><i class="bi bi-check-circle" style="font-size:11px"></i> Semua aman</span>
    @endif
</div>

@if($stockApiUnavailable)
    <div style="
        padding:24px 16px;
        background:var(--md-surface-container-low);
        border-radius:var(--md-shape-sm);
        text-align:center;
    ">
        <i class="bi bi-wifi-off" style="font-size:2rem;color:var(--md-outline);display:block;margin-bottom:8px"></i>
        <p style="font-size:14px;color:var(--md-on-surface-variant);margin:0">Data stok tidak tersedia saat ini</p>
    </div>
@elseif(empty($stockAlerts))
    <div style="
        padding:24px 16px;
        background:color-mix(in srgb, var(--md-primary-container) 40%, transparent);
        border-radius:var(--md-shape-sm);
        text-align:center;
    ">
        <i class="bi bi-check-circle" style="font-size:2rem;color:var(--md-primary);display:block;margin-bottom:8px"></i>
        <p style="font-size:14px;color:var(--md-on-surface);font-weight:500;margin:0">Semua stok dalam kondisi aman</p>
    </div>
@else
    <div style="display:flex;flex-direction:column;gap:6px">
        @foreach(array_slice($stockAlerts, 0, 5) as $idx => $alert)
            @include('dashboard.partials._stock-group', ['alert' => $alert, 'uid' => 'm' . $idx])
        @endforeach
    </div>

    @if($stockAlertCount > 5)
        <button type="button" onclick="openStockPanel()" style="
            margin-top:12px;width:100%;
            display:flex;align-items:center;justify-content:center;gap:6px;
            padding:10px 14px;
            background:var(--md-surface-container-low);
            border:1px solid var(--md-outline-variant);
            border-radius:var(--md-shape-sm);
            color:var(--md-primary);font-size:13px;font-weight:600;
            cursor:pointer;transition:background .12s;
        " onmouseover="this.style.background='var(--md-surface-container)'"
           onmouseout="this.style.background='var(--md-surface-container-low)'">
            Lihat selengkapnya
            <span style="font-weight:500;color:var(--md-on-surface-variant)">({{ $stockAlertCount - 5 }} lainnya)</span>
            <i class="bi bi-arrow-right" style="font-size:13px"></i>
        </button>
    @endif
@endif
