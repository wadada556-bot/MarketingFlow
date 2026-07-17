@php
    $stockAlerts         = $stockAlerts ?? [];
    $stockAlertCount     = $stockAlertCount ?? 0;
    $stockApiUnavailable = $stockApiUnavailable ?? false;

    $urgentCnt   = count(array_filter($stockAlerts, fn ($a) => $a['has_urgent_variant']));
    $kritisCnt   = count(array_filter($stockAlerts, fn ($a) => $a['status'] === 'danger'));
    $menipisCnt  = count(array_filter($stockAlerts, fn ($a) => $a['status'] === 'warning'));
    $belumPoCnt  = count(array_filter($stockAlerts, fn ($a) =>
        !empty(array_filter($a['variants'], fn ($v) => ($v['rank_90d'] ?? null) !== null && ($v['po_qty'] ?? null) === null))
    ));
@endphp
<div class="stock-sticky-header">
    <div style="display:flex;justify-content:space-between;align-items:center;{{ (!$stockApiUnavailable && !empty($stockAlerts)) ? 'margin-bottom:16px' : '' }}">
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

    @if(!$stockApiUnavailable && !empty($stockAlerts))
        {{-- Filter chips (multi-select) --}}
        <div class="stock-filter-chips">
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
    <div id="stock-list-body" class="stock-list-body">
        @foreach($stockAlerts as $idx => $alert)
            @include('dashboard.partials._stock-group', ['alert' => $alert, 'uid' => 'm' . $idx])
        @endforeach
        <div id="stock-filter-empty" style="display:none;padding:32px 16px;text-align:center">
            <i class="bi bi-funnel" style="font-size:2rem;color:var(--md-outline);display:block;margin-bottom:8px"></i>
            <p style="font-size:14px;color:var(--md-on-surface-variant);margin:0">Tidak ada produk untuk filter ini</p>
        </div>
    </div>
@endif
