@extends('components.layouts.app')

@section('title', 'Dashboard')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/dashboard.css') }}?v={{ filemtime(public_path('css/dashboard.css')) }}">

@endpush

@section('content')

{{-- ── Page Header ─────────────────────────────────────── --}}
<div class="md-page-header">
    <div>
        <h1>Dashboard</h1>
        <p class="subtitle">Overview performa toko & iklan Anda</p>
    </div>
    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
        <div style="position:relative">
            <select class="dash-select" id="storeFilter">
                <option value="all">Semua toko</option>
                @foreach($storeOptions as $slug => $displayName)
                    <option value="{{ $slug }}">{{ $displayName }}</option>
                @endforeach
            </select>
        </div>

        {{-- ── Date Range Picker ── --}}
        <div style="position:relative" id="drp-wrap">
            {{-- Trigger --}}
            <div id="drp-trigger" class="drp-trigger" onclick="drpToggle()">
                <div class="drp-prefix" id="drp-prefix">{{ $currentPeriod === 'custom' ? 'Kustom' : $periodLabel }}:</div>
                <div class="drp-inputs">
                    <input id="drp-from" class="drp-input" readonly
                           value="{{ $curStart->format('M d, Y') }}"
                           placeholder="Tanggal mulai">
                    <span class="drp-sep">-</span>
                    <input id="drp-to" class="drp-input" readonly
                           value="{{ $curEnd->format('M d, Y') }}"
                           placeholder="Tanggal akhir">
                </div>
                <span class="drp-icon">
                    <svg fill="none" stroke="currentColor" stroke-width="4" viewBox="0 0 48 48" width="16" height="16">
                        <path d="M7 22h34M14 5v8m20-8v8M8 41h32a1 1 0 0 0 1-1V10a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v30a1 1 0 0 0 1 1Z"/>
                    </svg>
                </span>
            </div>

            {{-- Dropdown panel --}}
            <div id="drp-panel" class="drp-panel" style="display:none">
                <div class="drp-body">
                    {{-- Preset cepat --}}
                    <div class="drp-side">
                        <button class="drp-preset {{ $currentPeriod === 'today'   ? 'active' : '' }}"
                                onclick="drpPreset('today')">Hari ini</button>
                        <button class="drp-preset {{ $currentPeriod === 'kemarin' ? 'active' : '' }}"
                                onclick="drpPreset('kemarin')">Kemarin</button>
                        <button class="drp-preset {{ $currentPeriod === '7d'      ? 'active' : '' }}"
                                onclick="drpPreset('7d')">7 hari terakhir</button>
                        <button class="drp-preset {{ $currentPeriod === '30d'     ? 'active' : '' }}"
                                onclick="drpPreset('30d')">30 hari terakhir</button>
                        <button class="drp-preset drp-preset-clear" onclick="drpClear()">
                            <i class="bi bi-x-circle" style="font-size:12px;margin-right:5px"></i>Hapus filter
                        </button>
                    </div>

                    {{-- Kalender rentang kustom --}}
                    <div class="drp-cal">
                        <div class="drp-cal-tab">
                            <span class="drp-kustom-tab {{ $currentPeriod === 'custom' ? 'active' : '' }}">Kustom</span>
                        </div>
                        <div class="drp-cal-grids">
                            <div class="drp-month">
                                <div class="drp-month-head">
                                    <button type="button" class="drp-nav-btn" onclick="drpNav(-12)" title="Tahun sebelumnya">«</button>
                                    <button type="button" class="drp-nav-btn" onclick="drpNav(-1)"  title="Bulan sebelumnya">‹</button>
                                    <span id="drp-m1-label"></span>
                                </div>
                                <div class="drp-dow"><span>Sen</span><span>Sel</span><span>Rab</span><span>Kam</span><span>Jum</span><span>Sab</span><span>Min</span></div>
                                <div class="drp-days" id="drp-m1-days"></div>
                            </div>
                            <div class="drp-month">
                                <div class="drp-month-head">
                                    <span id="drp-m2-label"></span>
                                    <button type="button" class="drp-nav-btn" onclick="drpNav(1)"  title="Bulan berikutnya">›</button>
                                    <button type="button" class="drp-nav-btn" onclick="drpNav(12)" title="Tahun berikutnya">»</button>
                                </div>
                                <div class="drp-dow"><span>Sen</span><span>Sel</span><span>Rab</span><span>Kam</span><span>Jum</span><span>Sab</span><span>Min</span></div>
                                <div class="drp-days" id="drp-m2-days"></div>
                            </div>
                        </div>
                        <div class="drp-cal-foot">
                            <span class="drp-range-text" id="drp-range-text">Pilih rentang tanggal</span>
                            <button type="button" class="drp-apply" id="drp-apply" onclick="drpApplyCustom()" disabled>Terapkan</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ── Metric Cards ─────────────────────────────────────── --}}
<div style="display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:20px">

    <div class="dash-metric-card">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:14px">
            <p class="dash-metric-label">Total Penjualan</p>
            <div class="dash-metric-icon" style="background:var(--md-primary-container)">
                <i class="bi bi-coin" style="color:var(--md-on-primary-container)"></i>
            </div>
        </div>
        <p class="dash-metric-value" id="m-rev">—</p>
        <p class="dash-metric-trend-up" id="m-rev-t"></p>
    </div>

    <div class="dash-metric-card">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:14px">
            <p class="dash-metric-label">Total Pesanan</p>
            <div class="dash-metric-icon" style="background:var(--md-tertiary-container)">
                <i class="bi bi-box-seam" style="color:var(--md-on-tertiary-container)"></i>
            </div>
        </div>
        <p class="dash-metric-value" id="m-ord">—</p>
        <p class="dash-metric-trend-up" id="m-ord-t"></p>
    </div>

    <div class="dash-metric-card">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:14px">
            <p class="dash-metric-label">Belanja Iklan</p>
            <div class="dash-metric-icon" style="background:var(--md-warning-container)">
                <i class="bi bi-megaphone" style="color:var(--md-warning)"></i>
            </div>
        </div>
        <p class="dash-metric-value" id="m-ads">—</p>
        <p class="dash-metric-trend-down" id="m-ads-t"></p>
    </div>

    <div class="dash-metric-card">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:14px">
            <p class="dash-metric-label">ROAS Rata-rata</p>
            <div class="dash-metric-icon" style="background:var(--md-secondary-container)">
                <i class="bi bi-graph-up" style="color:var(--md-on-secondary-container)"></i>
            </div>
        </div>
        <p class="dash-metric-value" id="m-roas">—</p>
        <p class="dash-metric-trend-up" id="m-roas-t"></p>
    </div>

</div>

{{-- ── Chart + Ranking + Stock + Ads (2×2, baris tinggi sama) ── --}}
<div style="display:grid;grid-template-columns:1.45fr 1fr;grid-auto-rows:1fr;gap:16px;margin-bottom:20px;align-items:stretch">

    {{-- Sales Chart --}}
    <div class="dash-section-card" style="display:flex;flex-direction:column">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
            <div>
                <p class="dash-section-title">Tren Penjualan</p>
                <p class="dash-section-subtitle" id="chart-period-label">{{ $periodLabel }}</p>
            </div>
        </div>

        {{-- Metric toggle chips --}}
        <div style="display:flex;gap:8px;margin-bottom:14px;flex-shrink:0">
            <button id="toggle-gmv" onclick="toggleMetric('gmv')" style="
                display:inline-flex;align-items:center;gap:6px;
                padding:5px 12px;border-radius:20px;border:1.5px solid #1D9E75;
                background:rgba(29,158,117,.1);cursor:pointer;
                font-size:12px;font-weight:500;color:#1D9E75;
                transition:background .15s,opacity .15s;
            ">
                <span style="width:16px;height:3px;background:#1D9E75;border-radius:2px;flex-shrink:0"></span>
                GMV
                <i class="bi bi-check" style="font-size:13px;font-weight:700"></i>
            </button>
            <button id="toggle-pesanan" onclick="toggleMetric('pesanan')" style="
                display:inline-flex;align-items:center;gap:6px;
                padding:5px 12px;border-radius:20px;border:1.5px solid #378ADD;
                background:rgba(55,138,221,.1);cursor:pointer;
                font-size:12px;font-weight:500;color:#378ADD;
                transition:background .15s,opacity .15s;
            ">
                <span style="width:16px;height:3px;background:#378ADD;border-radius:2px;flex-shrink:0"></span>
                Produk terjual
                <i class="bi bi-check" style="font-size:13px;font-weight:700"></i>
            </button>
        </div>

        <div style="position:relative;flex:1;min-height:160px">
            <canvas id="salesCanvas" style="position:absolute;inset:0;width:100%;height:100%;display:block"></canvas>
            <div id="chart-tooltip" style="
                display:none;position:absolute;pointer-events:none;
                background:var(--md-surface-container-high);
                border:1px solid var(--md-outline-variant);
                border-radius:8px;padding:8px 12px;
                font-size:12px;color:var(--md-on-surface);
                box-shadow:0 4px 12px rgba(0,0,0,.15);
                white-space:nowrap;z-index:10;
                min-width:130px;
            "></div>
        </div>
    </div>

    {{-- Store Ranking --}}
    <div class="dash-section-card" style="display:flex;flex-direction:column">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-shrink:0">
            <div>
                <p class="dash-section-title">Ranking Toko</p>
                <p class="dash-section-subtitle" id="ranking-label">{{ $periodLabel }}</p>
            </div>
            <span class="md-chip surface">
                <i class="bi bi-bar-chart-fill" style="font-size:11px"></i>
                Revenue
            </span>
        </div>
        <div id="store-ranking" style="display:flex;flex-direction:column;gap:12px"></div>
    </div>

    {{-- ── Stock Alerts + Ads Performance ──────────────────── --}}

    {{-- Stock Alerts (di-load lazy via AJAX → dashboard.stock-alerts) --}}
    <div class="dash-section-card">
        <div id="stock-card-lazy">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
                <div>
                    <p class="dash-section-title">Peringatan Stok</p>
                    <p class="dash-section-subtitle">Produk butuh perhatian</p>
                </div>
                <span class="dash-spinner"></span>
            </div>
            <div style="padding:28px 16px;text-align:center;background:var(--md-surface-container-low);border-radius:var(--md-shape-sm)">
                <span class="dash-spinner" style="width:22px;height:22px;border-width:3px"></span>
                <p style="font-size:13px;color:var(--md-on-surface-variant);margin:12px 0 0">Memuat data stok…</p>
            </div>
        </div>
    </div>

    {{-- Ads Performance --}}
    <div class="dash-section-card" style="display:flex;flex-direction:column">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;flex-shrink:0">
            <div>
                <p class="dash-section-title">Performa Iklan</p>
            </div>
        </div>

        {{-- Table header --}}
        <div style="
            display:grid;
            grid-template-columns:minmax(0,1fr) 64px 68px;
            gap:6px;
            padding:0 10px 10px;
            border-bottom:1px solid var(--md-outline-variant);
            flex-shrink:0;
        ">
            <span style="font-size:11px;font-weight:500;letter-spacing:.6px;text-transform:uppercase;color:var(--md-on-surface-variant)">Produk · Toko</span>
            <span style="font-size:11px;font-weight:500;letter-spacing:.6px;text-transform:uppercase;color:var(--md-on-surface-variant);text-align:right">ROAS</span>
            <span style="font-size:11px;font-weight:500;letter-spacing:.6px;text-transform:uppercase;color:var(--md-on-surface-variant);text-align:right">Status</span>
        </div>

        @php $adsTotal = count($adsPerformance); @endphp
        <div style="display:flex;flex-direction:column;gap:2px;margin-top:6px">
            @forelse(array_slice($adsPerformance, 0, 5) as $ad)
                @php $lr = $ad['low_roas']; @endphp
                <div class="ads-row {{ $lr ? 'low-roas' : '' }}">
                    <div style="min-width:0">
                        <p style="font-size:13px;font-weight:600;margin:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:{{ $lr ? 'var(--md-on-error-container)' : 'var(--md-on-surface)' }}">
                            {{ $ad['campaign'] }}
                        </p>
                        <p style="font-size:11px;margin:2px 0 0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:{{ $lr ? 'var(--md-on-error-container)' : 'var(--md-on-surface-variant)' }}">
                            {{ $ad['store_name'] }}
                        </p>
                    </div>
                    <span style="font-size:13px;font-weight:{{ $lr ? '700' : '600' }};color:{{ $lr ? 'var(--md-error)' : 'var(--md-primary)' }};text-align:right">
                        {{ $ad['roas_fmt'] }}
                    </span>
                    <div style="text-align:right">
                        @if($lr)
                            <span class="md-chip error" style="font-size:11px">⚠ Low</span>
                        @elseif($ad['status'] === 'active')
                            <span class="md-chip primary">Aktif</span>
                        @elseif($ad['status'] === 'completed')
                            <span class="md-chip surface">Selesai</span>
                        @else
                            <span class="md-chip surface">Nonaktif</span>
                        @endif
                    </div>
                </div>
            @empty
                <div style="padding:24px 16px;text-align:center">
                    <i class="bi bi-megaphone" style="font-size:2rem;color:var(--md-outline);display:block;margin-bottom:8px"></i>
                    <p style="font-size:14px;color:var(--md-on-surface-variant);margin:0">Tidak ada data iklan untuk periode ini</p>
                </div>
            @endforelse
        </div>

        @if($adsTotal > 5)
            <button type="button" onclick="openAdsPanel()" style="
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
                <span style="font-weight:500;color:var(--md-on-surface-variant)">({{ $adsTotal - 5 }} lainnya)</span>
                <i class="bi bi-arrow-right" style="font-size:13px"></i>
            </button>
        @endif

    </div>

</div>

{{-- ── Stock Alerts Offcanvas (di-inject via AJAX → dashboard.stock-alerts) ── --}}
<div id="stock-panel-lazy"></div>


{{-- ── Ads Performance Offcanvas (slide dari kiri) ── --}}
@if(count($adsPerformance) > 5)
@php
    $adsTotalPanel   = count($adsPerformance);
    $adsAktifCnt     = count(array_filter($adsPerformance, fn ($a) => $a['status'] === 'active' && !$a['low_roas']));
    $adsLowRoasCnt   = count(array_filter($adsPerformance, fn ($a) => $a['low_roas']));
    $adsNonaktifCnt  = count(array_filter($adsPerformance, fn ($a) => $a['status'] !== 'active'));
@endphp
<div id="ads-panel-overlay" class="stock-panel-overlay" onclick="closeAdsPanel()"></div>
<aside id="ads-panel" class="stock-panel" role="dialog" aria-modal="true" aria-label="Semua performa iklan">
    <div class="stock-panel-header">
        <div>
            <p class="dash-section-title">Performa Iklan</p>
            <p class="dash-section-subtitle">{{ $adsTotalPanel }} produk iklan</p>
        </div>
        <button type="button" class="stock-panel-close" onclick="closeAdsPanel()" aria-label="Tutup">
            <i class="bi bi-x-lg"></i>
        </button>
    </div>

    {{-- Filter chips --}}
    <div class="stock-panel-filters">
        <button type="button" class="stock-filter-chip active" data-filter="all" onclick="adsFilter('all')">
            Semua <span class="cnt">{{ $adsTotalPanel }}</span>
        </button>
        <button type="button" class="stock-filter-chip" data-filter="aktif" onclick="adsFilter('aktif')">
            Aktif <span class="cnt">{{ $adsAktifCnt }}</span>
        </button>
        @if($adsLowRoasCnt > 0)
        <button type="button" class="stock-filter-chip kritis" data-filter="lowroas" onclick="adsFilter('lowroas')">
            ⚠ Low ROAS <span class="cnt">{{ $adsLowRoasCnt }}</span>
        </button>
        @endif
        @if($adsNonaktifCnt > 0)
        <button type="button" class="stock-filter-chip" data-filter="nonaktif" onclick="adsFilter('nonaktif')">
            Nonaktif <span class="cnt">{{ $adsNonaktifCnt }}</span>
        </button>
        @endif
    </div>

    {{-- Table header --}}
    <div style="
        display:grid;
        grid-template-columns:minmax(0,1fr) 64px 68px;
        gap:6px;
        padding:0 20px 10px;
        border-bottom:1px solid var(--md-outline-variant);
        flex-shrink:0;
    ">
        <span style="font-size:11px;font-weight:500;letter-spacing:.6px;text-transform:uppercase;color:var(--md-on-surface-variant)">Produk · Toko</span>
        <span style="font-size:11px;font-weight:500;letter-spacing:.6px;text-transform:uppercase;color:var(--md-on-surface-variant);text-align:right">ROAS</span>
        <span style="font-size:11px;font-weight:500;letter-spacing:.6px;text-transform:uppercase;color:var(--md-on-surface-variant);text-align:right">Status</span>
    </div>

    <div class="stock-panel-body" style="padding:8px 10px 24px;gap:2px" id="ads-panel-body">
        @foreach($adsPerformance as $ad)
            @php $lr = $ad['low_roas']; @endphp
            <div class="ads-row {{ $lr ? 'low-roas' : '' }}"
                 data-status="{{ $ad['status'] }}"
                 data-lowroas="{{ $lr ? '1' : '0' }}">
                <div style="min-width:0">
                    <p style="font-size:13px;font-weight:600;margin:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:{{ $lr ? 'var(--md-on-error-container)' : 'var(--md-on-surface)' }}">
                        {{ $ad['campaign'] }}
                    </p>
                    <p style="font-size:11px;margin:2px 0 0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:{{ $lr ? 'var(--md-on-error-container)' : 'var(--md-on-surface-variant)' }}">
                        {{ $ad['store_name'] }}
                    </p>
                </div>
                <span style="font-size:13px;font-weight:{{ $lr ? '700' : '600' }};color:{{ $lr ? 'var(--md-error)' : 'var(--md-primary)' }};text-align:right">
                    {{ $ad['roas_fmt'] }}
                </span>
                <div style="text-align:right">
                    @if($lr)
                        <span class="md-chip error" style="font-size:11px">⚠ Low</span>
                    @elseif($ad['status'] === 'active')
                        <span class="md-chip primary">Aktif</span>
                    @elseif($ad['status'] === 'completed')
                        <span class="md-chip surface">Selesai</span>
                    @else
                        <span class="md-chip surface">Nonaktif</span>
                    @endif
                </div>
            </div>
        @endforeach
        <div id="ads-filter-empty" style="display:none;padding:32px 16px;text-align:center">
            <i class="bi bi-funnel" style="font-size:2rem;color:var(--md-outline);display:block;margin-bottom:8px"></i>
            <p style="font-size:14px;color:var(--md-on-surface-variant);margin:0">Tidak ada iklan untuk filter ini</p>
        </div>
    </div>
</aside>
@endif


@endsection

@push('scripts')
<script>
window.DASH = {
    colors:          @json($jsColors),
    stores:          @json($jsStores),
    storeMetrics:    @json($jsStoreMetrics),
    chartMetricData: @json($chartMetricData),
    chartStoreMetric: @json($chartStoreMetricData),
    defaultMetrics:  @json($jsDefaultMetrics),
    today:     @json(\Carbon\Carbon::today()->toDateString()),
    initStart: @json($currentPeriod === 'custom' ? $curStart->toDateString() : null),
    initEnd:   @json($currentPeriod === 'custom' ? $curEnd->toDateString()   : null),
    routes: {
        dashboard:   "{{ route('dashboard') }}",
        stockAlerts: "{{ route('dashboard.stock-alerts') }}",
    },
};
</script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
<script src="{{ asset('js/dashboard.js') }}?v={{ filemtime(public_path('js/dashboard.js')) }}"></script>
@endpush
