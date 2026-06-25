@php
    $stockAlerts         = $stockAlerts ?? [];
    $stockAlertCount     = $stockAlertCount ?? 0;
    $stockApiUnavailable = $stockApiUnavailable ?? false;
@endphp
@if(!$stockApiUnavailable && !empty($stockAlerts))
@php
    $urgentCnt   = count(array_filter($stockAlerts, fn ($a) => $a['has_urgent_variant']));
    $kritisCnt   = count(array_filter($stockAlerts, fn ($a) => $a['status'] === 'danger'));
    $menipisCnt  = count(array_filter($stockAlerts, fn ($a) => $a['status'] === 'warning'));
    $belumPoCnt  = count(array_filter($stockAlerts, fn ($a) =>
        !empty(array_filter($a['variants'], fn ($v) => ($v['rank_90d'] ?? null) !== null && ($v['po_qty'] ?? null) === null))
    ));
@endphp
<div id="stock-panel-overlay" class="stock-panel-overlay" onclick="closeStockPanel()"></div>
<aside id="stock-panel" class="stock-panel" role="dialog" aria-modal="true" aria-label="Semua peringatan stok">
    <div class="stock-panel-header">
        <div>
            <p class="dash-section-title">Peringatan Stok</p>
            <p class="dash-section-subtitle">{{ $stockAlertCount }} produk butuh perhatian</p>
        </div>
        <button type="button" class="stock-panel-close" onclick="closeStockPanel()" aria-label="Tutup">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>

    {{-- Filter chips (multi-select) --}}
    <div class="stock-panel-filters">
        <button type="button" class="stock-filter-chip active" data-filter="all" onclick="stockFilter('all')">
            Semua <span class="cnt">{{ $stockAlertCount }}</span>
        </button>
        <button type="button" class="stock-filter-chip urgent" data-filter="urgent" onclick="stockFilter('urgent')">
            ⚡ Urgent <span class="cnt">{{ $urgentCnt }}</span>
        </button>
        <button type="button" class="stock-filter-chip kritis" data-filter="kritis" onclick="stockFilter('kritis')">
            Kritis <span class="cnt">{{ $kritisCnt }}</span>
        </button>
        <button type="button" class="stock-filter-chip menipis" data-filter="menipis" onclick="stockFilter('menipis')">
            Menipis <span class="cnt">{{ $menipisCnt }}</span>
        </button>
        @if($belumPoCnt > 0)
        <button type="button" class="stock-filter-chip belum-po" data-filter="belum-po" onclick="stockFilter('belum-po')">
            ⚠ Belum PO <span class="cnt">{{ $belumPoCnt }}</span>
        </button>
        @endif
    </div>

    <div class="stock-panel-body">
        @foreach($stockAlerts as $idx => $alert)
            @include('dashboard.partials._stock-group', ['alert' => $alert, 'uid' => 'o' . $idx])
        @endforeach
        <div id="stock-filter-empty" style="display:none;padding:32px 16px;text-align:center">
            <i class="bi bi-funnel" style="font-size:2rem;color:var(--md-outline);display:block;margin-bottom:8px"></i>
            <p style="font-size:14px;color:var(--md-on-surface-variant);margin:0">Tidak ada produk untuk filter ini</p>
        </div>
    </div>
</aside>
@endif
