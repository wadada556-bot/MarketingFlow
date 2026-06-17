@extends('components.layouts.app')

@section('title', 'Dashboard')

@push('styles')
<style>
/* ── Dashboard-specific styles (MD3 tokens only) ──────── */
.dash-metric-card {
    background: var(--md-surface-container-lowest);
    border: 1px solid var(--md-outline-variant);
    border-radius: var(--md-shape-lg);
    padding: 20px;
}
.dash-metric-icon {
    width: 40px; height: 40px;
    border-radius: var(--md-shape-full);
    display: flex; align-items: center; justify-content: center;
    font-size: 18px;
    flex-shrink: 0;
}
.dash-metric-value {
    font-size: 26px;
    font-weight: 500;
    color: var(--md-on-surface);
    margin: 0;
    line-height: 1.2;
    letter-spacing: -.5px;
}
.dash-metric-label {
    font-size: 12px;
    font-weight: 500;
    color: var(--md-on-surface-variant);
    margin: 0;
    letter-spacing: .3px;
    text-transform: uppercase;
}
.dash-metric-trend-up   { font-size: 12px; color: var(--md-primary); font-weight: 500; margin: 6px 0 0; }
.dash-metric-trend-down { font-size: 12px; color: var(--md-error);   font-weight: 500; margin: 6px 0 0; }

.dash-section-card {
    background: var(--md-surface-container-lowest);
    border: 1px solid var(--md-outline-variant);
    border-radius: var(--md-shape-lg);
    padding: 20px;
}
.dash-section-title {
    font-size: 15px;
    font-weight: 600;
    color: var(--md-on-surface);
    margin: 0;
    letter-spacing: .1px;
}
.dash-section-subtitle {
    font-size: 12px;
    color: var(--md-on-surface-variant);
    margin: 0;
}

/* Period/view toggle chips */
.dash-view-chip {
    font-size: 12px;
    font-weight: 500;
    padding: 5px 14px;
    border-radius: var(--md-shape-full);
    cursor: pointer;
    border: 1px solid var(--md-outline-variant);
    background: transparent;
    color: var(--md-on-surface-variant);
    transition: all .15s;
    line-height: 20px;
    letter-spacing: .1px;
}
.dash-view-chip:hover { background: var(--md-surface-container-high); color: var(--md-on-surface); }
.dash-view-chip.active {
    background: var(--md-secondary-container);
    color: var(--md-on-secondary-container);
    border-color: transparent;
}

/* Filter selects */
.dash-select {
    font-size: 13px;
    padding: 7px 12px;
    border: 1px solid var(--md-outline);
    border-radius: var(--md-shape-xs);
    background: var(--md-surface);
    color: var(--md-on-surface);
    cursor: pointer;
    transition: border-color .12s;
    appearance: none;
    -webkit-appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' stroke='%236F7977' stroke-width='1.5' fill='none' stroke-linecap='round'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 10px center;
    padding-right: 30px;
}
.dash-select:focus {
    outline: none;
    border-color: var(--md-primary);
    border-width: 2px;
}

/* Stock accordion */
.stock-group {
    border: 1px solid var(--md-outline-variant);
    border-radius: var(--md-shape-sm);
    overflow: hidden;
    background: var(--md-surface);
}
.stock-group-header {
    display: flex;
    align-items: flex-start;
    padding: 12px 14px 12px 16px;
    cursor: pointer;
    transition: background .12s;
    gap: 10px;
    background: var(--md-surface);
    position: relative;
}
.stock-group-header::before {
    content: '';
    position: absolute;
    left: 0; top: 0; bottom: 0;
    width: 4px;
    border-radius: 0;
}
.stock-group-header:hover { background: var(--md-surface-container-low); }
.stock-group-header.danger-header::before  { background: var(--md-error); }
.stock-group-header.warning-header::before { background: var(--md-warning); }
.stock-group-body {
    display: none;
    padding: 8px 14px 12px 18px;
    background: var(--md-surface-container-lowest);
    border-top: 1px solid var(--md-outline-variant);
}

/* Ads table row */
.ads-row {
    display: grid;
    grid-template-columns: minmax(0,1fr) 52px 48px 68px;
    gap: 6px;
    padding: 9px 10px;
    align-items: center;
    border-radius: var(--md-shape-sm);
}
.ads-row:hover { background: var(--md-surface-container-low); }
.ads-row.low-roas {
    background: var(--md-error-container);
    opacity: .85;
}
.ads-row.low-roas:hover { opacity: 1; }

/* Store ranking bar */
.rank-bar-track {
    flex: 1;
    height: 5px;
    background: var(--md-surface-container-high);
    border-radius: var(--md-shape-full);
    overflow: hidden;
}
.rank-bar-fill {
    height: 100%;
    border-radius: var(--md-shape-full);
    transition: width .4s cubic-bezier(.4,0,.2,1);
}

/* ── Date Range Picker ─────────────────────────────────── */
.drp-trigger {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 7px 12px;
    border: 1px solid var(--md-outline);
    border-radius: var(--md-shape-xs);
    background: var(--md-surface);
    cursor: pointer;
    transition: border-color .12s;
    white-space: nowrap;
    min-width: 0;
}
.drp-trigger:hover  { border-color: var(--md-primary); }
.drp-trigger.open   { border: 2px solid var(--md-primary); padding: 6px 11px; }

.drp-prefix {
    font-size: 12px;
    font-weight: 500;
    color: var(--md-on-surface-variant);
    max-width: 120px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    flex-shrink: 0;
}
.drp-inputs { display: flex; align-items: center; gap: 4px; }
.drp-input {
    border: none;
    outline: none;
    background: transparent;
    font-size: 13px;
    color: var(--md-on-surface);
    cursor: pointer;
    width: 90px;
    text-align: center;
}
.drp-sep { font-size: 13px; color: var(--md-on-surface-variant); }
.drp-icon { color: var(--md-on-surface-variant); display: flex; align-items: center; flex-shrink: 0; }

.drp-panel {
    position: absolute;
    right: 0;
    top: calc(100% + 6px);
    background: var(--md-surface-container-lowest);
    border: 1px solid var(--md-outline-variant);
    border-radius: var(--md-shape-md);
    box-shadow: 0 4px 20px rgba(0,0,0,.12);
    z-index: 1000;
    overflow: hidden;
}

.drp-presets {
    display: flex;
    flex-direction: column;
    padding: 12px;
    gap: 8px;
}
.drp-preset {
    text-align: center;
    padding: 8px 14px;
    font-size: 13px;
    font-weight: 500;
    border: 1px solid var(--md-outline-variant);
    background: var(--md-surface);
    color: var(--md-on-surface);
    border-radius: var(--md-shape-xs);
    cursor: pointer;
    transition: all .12s;
    white-space: nowrap;
    min-width: 160px;
}
.drp-preset:hover {
    border-color: var(--md-primary);
    color: var(--md-primary);
}
.drp-preset.active {
    border-color: var(--md-primary);
    color: var(--md-primary);
    background: color-mix(in srgb, var(--md-primary) 6%, transparent);
}
.drp-preset-clear {
    border-color: var(--md-outline-variant);
    color: var(--md-on-surface-variant);
}
.drp-preset-clear:hover {
    border-color: var(--md-error);
    color: var(--md-error);
}

/* Ranking scrollable list */
#store-ranking {
    max-height: 260px;
    overflow-y: auto;
    scrollbar-width: thin;
    scrollbar-color: var(--md-outline-variant) transparent;
}
#store-ranking::-webkit-scrollbar { width: 4px; }
#store-ranking::-webkit-scrollbar-track { background: transparent; }
#store-ranking::-webkit-scrollbar-thumb { background: var(--md-outline-variant); border-radius: 4px; }


</style>
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
                <div class="drp-presets">
                    <button class="drp-preset {{ $currentPeriod === 'today'   ? 'active' : '' }}"
                            onclick="drpPreset('today')">Hari ini</button>
                    <button class="drp-preset {{ $currentPeriod === 'kemarin' ? 'active' : '' }}"
                            onclick="drpPreset('kemarin')">Kemarin</button>
                    <button class="drp-preset {{ $currentPeriod === '7d'      ? 'active' : '' }}"
                            onclick="drpPreset('7d')">7 hari terakhir</button>
                    <button class="drp-preset {{ $currentPeriod === '30d'     ? 'active' : '' }}"
                            onclick="drpPreset('30d')">30 hari terakhir</button>
                    <button class="drp-preset drp-preset-clear" onclick="drpClear()">
                        <i class="bi bi-x-circle" style="font-size:12px;margin-right:5px"></i>Hapus filter tanggal
                    </button>
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

{{-- ── Chart + Store Ranking ────────────────────────────── --}}
<div style="display:grid;grid-template-columns:1.45fr 1fr;gap:16px;margin-bottom:20px;align-items:start">

    {{-- Sales Chart --}}
    <div class="dash-section-card">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
            <div>
                <p class="dash-section-title">Tren Penjualan</p>
                <p class="dash-section-subtitle" id="chart-period-label">{{ $periodLabel }}</p>
            </div>
        </div>

        {{-- Metric toggle chips --}}
        <div style="display:flex;gap:8px;margin-bottom:14px">
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

        <div style="position:relative">
            <canvas id="salesCanvas" style="width:100%;height:200px;display:block"></canvas>
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
    <div class="dash-section-card">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
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

</div>

{{-- ── Stock Alerts + Ads Performance ──────────────────── --}}
<div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px;align-items:start">

    {{-- Stock Alerts --}}
    <div class="dash-section-card">
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
                @foreach($stockAlerts as $alert)
                    @php
                        $idx       = $loop->index;
                        $isDanger  = $alert['status'] === 'danger';
                        $alertCnt  = $alert['alert_count'];
                        $dangerCnt = $alert['danger_count'];
                    @endphp
                    <div class="stock-group">
                        <div class="stock-group-header {{ $isDanger ? 'danger-header' : 'warning-header' }}"
                             onclick="toggleStock({{ $idx }})">
                            <i class="bi {{ $isDanger ? 'bi-x-circle-fill' : 'bi-exclamation-triangle-fill' }}"
                               style="font-size:15px;flex-shrink:0;margin-top:1px;color:{{ $isDanger ? 'var(--md-error)' : 'var(--md-warning)' }}"></i>
                            <div style="min-width:0;flex:1;overflow:hidden">
                                <span style="font-size:13px;font-weight:600;color:var(--md-on-surface);
                                             display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                    {{ $alert['parent_sku'] }}
                                </span>
                                <div style="display:flex;align-items:center;gap:4px;flex-wrap:wrap;margin-top:3px">
                                    @if($alert['category'])
                                        <span class="md-chip surface" style="font-size:10px;padding:2px 7px;flex-shrink:0">{{ $alert['category'] }}</span>
                                    @endif
                                    @php
                                        $storeShow  = array_slice($alert['stores'], 0, 2);
                                        $storeExtra = count($alert['stores']) - 2;
                                    @endphp
                                    <span style="font-size:11px;color:var(--md-on-surface-variant);min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                        {{ implode(' · ', $storeShow) }}@if($storeExtra > 0) <span style="color:var(--md-outline)">+{{ $storeExtra }}</span>@endif
                                        · {{ $alertCnt }} varian
                                        @if($dangerCnt > 0)
                                            · <span style="color:var(--md-error);font-weight:600">{{ $dangerCnt }} kritis</span>
                                        @endif
                                    </span>
                                </div>
                            </div>
                            <div style="display:flex;align-items:center;gap:6px;flex-shrink:0">
                                <span class="md-chip {{ $isDanger ? 'error' : 'warning' }}">
                                    {{ $isDanger ? 'Kritis' : 'Menipis' }}
                                </span>
                                <i id="stock-chevron-{{ $idx }}" class="bi bi-chevron-down"
                                   style="font-size:12px;transition:transform .2s;color:var(--md-on-surface-variant)"></i>
                            </div>
                        </div>
                        <div id="stock-body-{{ $idx }}" class="stock-group-body">
                            <div style="display:flex;flex-direction:column;gap:4px">
                                @foreach($alert['variants'] as $variant)
                                    @php $vDanger = $variant['status'] === 'danger'; $vWarn = $variant['status'] === 'warning'; @endphp
                                    <div style="
                                        display:flex;justify-content:space-between;align-items:center;
                                        padding:6px 8px;gap:8px;
                                        background:var(--md-surface-container-low);
                                        border-radius:var(--md-shape-xs);
                                    ">
                                        <span style="font-size:12px;font-family:monospace;color:var(--md-on-surface);
                                                     min-width:0;flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $variant['sku'] }}</span>
                                        <span class="md-chip {{ $vDanger ? 'error' : ($vWarn ? 'warning' : 'primary') }}" style="font-size:11px;min-width:52px;text-align:center;flex-shrink:0">
                                            {{ number_format($variant['qty']) }} pcs
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

        @endif

    </div>

    {{-- Ads Performance --}}
    <div class="dash-section-card">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
            <div>
                <p class="dash-section-title">Performa Iklan</p>
                <p class="dash-section-subtitle">7 hari terakhir</p>
            </div>
        </div>

        {{-- Table header --}}
        <div style="
            display:grid;
            grid-template-columns:minmax(0,1fr) 52px 48px 68px;
            gap:6px;
            padding:0 10px 10px;
            border-bottom:1px solid var(--md-outline-variant);
        ">
            <span style="font-size:11px;font-weight:500;letter-spacing:.6px;text-transform:uppercase;color:var(--md-on-surface-variant)">Campaign</span>
            <span style="font-size:11px;font-weight:500;letter-spacing:.6px;text-transform:uppercase;color:var(--md-on-surface-variant);text-align:right">Spend</span>
            <span style="font-size:11px;font-weight:500;letter-spacing:.6px;text-transform:uppercase;color:var(--md-on-surface-variant);text-align:right">ROAS</span>
            <span style="font-size:11px;font-weight:500;letter-spacing:.6px;text-transform:uppercase;color:var(--md-on-surface-variant);text-align:right">Status</span>
        </div>

        <div style="display:flex;flex-direction:column;gap:2px;margin-top:6px">
            <div class="ads-row">
                <div>
                    <p style="font-size:13px;font-weight:600;margin:0;color:var(--md-on-surface)">Flash Sale Topi Baseball</p>
                    <p style="font-size:11px;color:var(--md-on-surface-variant);margin:2px 0 0">TOPI KEREN · TikTok</p>
                </div>
                <span style="font-size:12px;text-align:right;color:var(--md-on-surface)">1,4 jt</span>
                <span style="font-size:13px;font-weight:600;color:var(--md-primary);text-align:right">14,2x</span>
                <div style="text-align:right"><span class="md-chip primary">Aktif</span></div>
            </div>
            <div class="ads-row">
                <div>
                    <p style="font-size:13px;font-weight:600;margin:0;color:var(--md-on-surface)">Promo Aksesoris Bundle</p>
                    <p style="font-size:11px;color:var(--md-on-surface-variant);margin:2px 0 0">CARAMEL · Tokopedia</p>
                </div>
                <span style="font-size:12px;text-align:right;color:var(--md-on-surface)">2,1 jt</span>
                <span style="font-size:13px;font-weight:600;color:var(--md-primary);text-align:right">8,1x</span>
                <div style="text-align:right"><span class="md-chip primary">Aktif</span></div>
            </div>
            <div class="ads-row">
                <div>
                    <p style="font-size:13px;font-weight:600;margin:0;color:var(--md-on-surface)">NOMIDE TikTok Ads</p>
                    <p style="font-size:11px;color:var(--md-on-surface-variant);margin:2px 0 0">NOMIDE STORE · TikTok</p>
                </div>
                <span style="font-size:12px;text-align:right;color:var(--md-on-surface)">0,9 jt</span>
                <span style="font-size:13px;font-weight:600;color:var(--md-primary);text-align:right">9,8x</span>
                <div style="text-align:right"><span class="md-chip primary">Aktif</span></div>
            </div>
            <div class="ads-row low-roas">
                <div>
                    <p style="font-size:13px;font-weight:600;margin:0;color:var(--md-on-error-container)">Brand Awareness TOPI KECE</p>
                    <p style="font-size:11px;color:var(--md-on-error-container);margin:2px 0 0">TOPI KECE · Tokopedia</p>
                </div>
                <span style="font-size:12px;text-align:right;color:var(--md-on-error-container)">1,1 jt</span>
                <span style="font-size:13px;font-weight:700;color:var(--md-error);text-align:right">2,1x</span>
                <div style="text-align:right"><span class="md-chip error" style="font-size:11px">⚠ Low</span></div>
            </div>
            <div class="ads-row">
                <div>
                    <p style="font-size:13px;font-weight:600;margin:0;color:var(--md-on-surface)">Weekend Sale MINZO</p>
                    <p style="font-size:11px;color:var(--md-on-surface-variant);margin:2px 0 0">MINZO STORE · TikTok</p>
                </div>
                <span style="font-size:12px;text-align:right;color:var(--md-on-surface)">1,6 jt</span>
                <span style="font-size:13px;font-weight:600;color:var(--md-warning);text-align:right">4,3x</span>
                <div style="text-align:right"><span class="md-chip surface">Selesai</span></div>
            </div>
        </div>

    </div>

</div>


@endsection

@push('scripts')
<script>
function sendPrompt(text) { console.log('[sendPrompt]', text); }

function toggleStock(idx) {
    var body    = document.getElementById('stock-body-' + idx);
    var chevron = document.getElementById('stock-chevron-' + idx);
    var open    = body.style.display !== 'none';
    body.style.display      = open ? 'none' : 'block';
    chevron.style.transform = open ? 'rotate(0deg)' : 'rotate(180deg)';
}


const COLORS               = @json($jsColors);
const STORES               = @json($jsStores);
const STORE_METRICS        = @json($jsStoreMetrics);
const CHART_DAILY          = @json($chartDaily);
const CHART_WEEKLY         = @json($chartWeekly);
const CHART_METRIC_DATA    = @json($chartMetricData);
const CHART_STORE_METRIC   = @json($chartStoreMetricData);

let curView      = 'harian';
let curStore     = 'all';
let activeMetrics = { gmv: true, pesanan: true };

const METRIC_COLOR = { gmv: '#1D9E75', pesanan: '#378ADD' };

const DEFAULT_METRICS = @json($jsDefaultMetrics);

function setTrend(id, text, up) {
    const el = document.getElementById(id);
    el.textContent = text;
    el.className = up ? 'dash-metric-trend-up' : 'dash-metric-trend-down';
}

function updateMetrics() {
    let d = curStore !== 'all' ? STORE_METRICS[curStore] : DEFAULT_METRICS;
    document.getElementById('m-rev').textContent  = d.rev;
    setTrend('m-rev-t',  d.revT,  d.revUp);
    document.getElementById('m-ord').textContent  = d.ord;
    setTrend('m-ord-t',  d.ordT,  d.ordUp);
    document.getElementById('m-ads').textContent  = d.ads;
    setTrend('m-ads-t',  d.adsT,  d.adsUp);
    document.getElementById('m-roas').textContent = d.roas;
    setTrend('m-roas-t', d.roasT, d.roasUp);
}

function fmtRev(n) {
    if (n >= 1000000) return 'Rp ' + (n / 1000000).toFixed(1) + ' jt';
    return 'Rp ' + (n / 1000).toFixed(0) + ' rb';
}

function renderRanking() {
    const totalRev = STORES.reduce((a, s) => a + s.rev, 0);
    const sorted   = [...STORES].sort((a, b) => b.rev - a.rev);
    document.getElementById('store-ranking').innerHTML = sorted.map((s, i) => {
        const pct = Math.round(s.rev / totalRev * 100);
        return `<div>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:5px">
                <div style="display:flex;align-items:center;gap:8px">
                    <span style="
                        font-size:12px;font-weight:600;
                        min-width:20px;height:20px;
                        background:${i===0?'var(--md-primary-container)':'var(--md-surface-container-high)'};
                        color:${i===0?'var(--md-on-primary-container)':'var(--md-on-surface-variant)'};
                        border-radius:50%;
                        display:flex;align-items:center;justify-content:center;
                    ">${i + 1}</span>
                    <span style="font-size:14px;font-weight:500;color:var(--md-on-surface)">${s.name}</span>
                </div>
                <span style="font-size:13px;font-weight:600;color:var(--md-on-surface)">${fmtRev(s.rev)}</span>
            </div>
            <div style="display:flex;align-items:center;gap:8px">
                <div class="rank-bar-track">
                    <div class="rank-bar-fill" style="width:${pct}%;background:${COLORS[s.id]}"></div>
                </div>
                <span style="font-size:12px;font-weight:500;min-width:40px;text-align:right;color:${s.trendUp?'var(--md-primary)':'var(--md-error)'}">
                    ${s.trendUp ? '↑' : '↓'} ${s.trend}%
                </span>
            </div>
        </div>`;
    }).join('');
}

function toggleMetric(key) {
    // Jangan matikan keduanya
    const otherKey = key === 'gmv' ? 'pesanan' : 'gmv';
    if (activeMetrics[key] && !activeMetrics[otherKey]) return;
    activeMetrics[key] = !activeMetrics[key];

    const btn = document.getElementById('toggle-' + key);
    const color = METRIC_COLOR[key];
    if (activeMetrics[key]) {
        btn.style.background = key === 'gmv' ? 'rgba(29,158,117,.1)' : 'rgba(55,138,221,.1)';
        btn.style.opacity = '1';
        btn.querySelector('i').style.display = '';
    } else {
        btn.style.background = 'transparent';
        btn.style.opacity = '0.45';
        btn.querySelector('i').style.display = 'none';
    }
    drawChart();
}

let _chartState = null;

function fmtGmvTooltip(v) {
    // v dalam ribuan
    if (v >= 1000) return 'Rp ' + (v / 1000).toFixed(1).replace('.', ',') + ' jt';
    if (v > 0)     return 'Rp ' + v.toLocaleString('id') + ' rb';
    return 'Rp 0';
}

function drawChart(hoverIdx = -1) {
    const canvas = document.getElementById('salesCanvas');
    if (!canvas) return;

    const W = canvas.offsetWidth || 380;
    const H = 200;
    const dpr = window.devicePixelRatio || 1;
    canvas.width  = W * dpr;
    canvas.height = H * dpr;
    canvas.style.width  = W + 'px';
    canvas.style.height = H + 'px';
    const ctx = canvas.getContext('2d');
    ctx.scale(dpr, dpr);

    // Pilih sumber data: all-stores total atau per toko
    let src;
    if (curStore !== 'all' && CHART_STORE_METRIC[curStore]) {
        const s = CHART_STORE_METRIC[curStore];
        src = curView === 'harian'
            ? { labels: CHART_METRIC_DATA.daily.labels,  gmv: s.daily.gmv,  pesanan: s.daily.pesanan }
            : { labels: CHART_METRIC_DATA.weekly.labels, gmv: s.weekly.gmv, pesanan: s.weekly.pesanan };
    } else {
        src = curView === 'harian' ? CHART_METRIC_DATA.daily : CHART_METRIC_DATA.weekly;
    }

    const labels   = src.labels;
    const n        = labels.length;
    const showBoth = activeMetrics.gmv && activeMetrics.pesanan;

    // Padding: kiri untuk Y-GMV, kanan untuk Y-pesanan (jika dual)
    const pL = 46, pR = showBoth ? 42 : 12, pT = 12, pB = 28;
    const cW = W - pL - pR, cH = H - pT - pB;
    const xS = n > 1 ? cW / (n - 1) : cW;

    const gc = 'rgba(111,121,119,0.10)';
    const lc = 'rgba(111,121,119,0.55)';

    // Skala GMV
    let maxGmv = 0;
    if (activeMetrics.gmv) src.gmv.forEach(v => maxGmv = Math.max(maxGmv, v));
    maxGmv = Math.ceil(maxGmv / 500) * 500 || 1;

    // Skala pesanan
    let maxPes = 0;
    if (activeMetrics.pesanan) src.pesanan.forEach(v => maxPes = Math.max(maxPes, v));
    maxPes = Math.ceil(maxPes / 50) * 50 || 1;

    // Simpan state untuk hover
    _chartState = { src, labels, n, pL, pR, pT, pB, cW, cH, xS, W, H, maxGmv, maxPes };

    // Grid & Y-axis kiri (GMV)
    ctx.font = '10px -apple-system,BlinkMacSystemFont,sans-serif';
    for (let i = 0; i <= 4; i++) {
        const y = pT + (cH / 4) * i;
        ctx.strokeStyle = gc; ctx.lineWidth = 0.5;
        ctx.beginPath(); ctx.moveTo(pL, y); ctx.lineTo(W - pR, y); ctx.stroke();

        if (activeMetrics.gmv) {
            const val = maxGmv - (maxGmv / 4) * i;
            ctx.fillStyle = METRIC_COLOR.gmv;
            ctx.textAlign = 'right';
            const lbl = val >= 1000 ? (val / 1000).toFixed(0) + ' jt' : val + ' rb';
            ctx.fillText(lbl, pL - 5, y + 3.5);
        }

        if (showBoth) {
            const valP = Math.round(maxPes - (maxPes / 4) * i);
            ctx.fillStyle = METRIC_COLOR.pesanan;
            ctx.textAlign = 'left';
            ctx.fillText(valP.toLocaleString('id'), W - pR + 5, y + 3.5);
        } else if (!activeMetrics.gmv && activeMetrics.pesanan) {
            const valP = Math.round(maxPes - (maxPes / 4) * i);
            ctx.fillStyle = METRIC_COLOR.pesanan;
            ctx.textAlign = 'right';
            ctx.fillText(valP.toLocaleString('id'), pL - 5, y + 3.5);
        }
    }

    // Fungsi helper gambar garis
    function drawLine(pts, maxVal, color) {
        if (!pts || pts.length === 0) return;
        ctx.beginPath();
        pts.forEach((v, i) => {
            const x = pL + i * xS;
            const y = pT + cH - (v / maxVal) * cH;
            i === 0 ? ctx.moveTo(x, y) : ctx.lineTo(x, y);
        });
        ctx.strokeStyle = color;
        ctx.lineWidth   = 2;
        ctx.lineJoin    = 'round';
        ctx.lineCap     = 'round';
        ctx.stroke();

        // Dots hanya jika data sedikit
        if (n <= 14) {
            pts.forEach((v, i) => {
                const x = pL + i * xS;
                const y = pT + cH - (v / maxVal) * cH;
                ctx.beginPath(); ctx.arc(x, y, 2.5, 0, Math.PI * 2);
                ctx.fillStyle = color; ctx.fill();
            });
        }
    }

    if (activeMetrics.gmv)     drawLine(src.gmv,     maxGmv, METRIC_COLOR.gmv);
    if (activeMetrics.pesanan) drawLine(src.pesanan,  maxPes, METRIC_COLOR.pesanan);

    // Label sumbu X
    ctx.fillStyle = lc;
    ctx.textAlign = 'center';
    labels.forEach((l, i) => {
        if (l) ctx.fillText(l, pL + i * xS, H - 7);
    });

    // ── Hover overlay ──────────────────────────────────────────────
    if (hoverIdx >= 0 && hoverIdx < n) {
        const x = pL + hoverIdx * xS;

        // Garis vertikal putus-putus
        ctx.save();
        ctx.strokeStyle = 'rgba(111,121,119,0.35)';
        ctx.lineWidth   = 1;
        ctx.setLineDash([4, 3]);
        ctx.beginPath(); ctx.moveTo(x, pT); ctx.lineTo(x, pT + cH); ctx.stroke();
        ctx.setLineDash([]);
        ctx.restore();

        // Dot highlight GMV
        if (activeMetrics.gmv && src.gmv[hoverIdx] !== undefined) {
            const y = pT + cH - (src.gmv[hoverIdx] / maxGmv) * cH;
            ctx.beginPath(); ctx.arc(x, y, 5, 0, Math.PI * 2);
            ctx.fillStyle = '#fff'; ctx.fill();
            ctx.beginPath(); ctx.arc(x, y, 5, 0, Math.PI * 2);
            ctx.strokeStyle = METRIC_COLOR.gmv; ctx.lineWidth = 2.5; ctx.stroke();
        }

        // Dot highlight pesanan
        if (activeMetrics.pesanan && src.pesanan[hoverIdx] !== undefined) {
            const y = pT + cH - (src.pesanan[hoverIdx] / maxPes) * cH;
            ctx.beginPath(); ctx.arc(x, y, 5, 0, Math.PI * 2);
            ctx.fillStyle = '#fff'; ctx.fill();
            ctx.beginPath(); ctx.arc(x, y, 5, 0, Math.PI * 2);
            ctx.strokeStyle = METRIC_COLOR.pesanan; ctx.lineWidth = 2.5; ctx.stroke();
        }
    }
}

function initChartHover() {
    const canvas  = document.getElementById('salesCanvas');
    const tooltip = document.getElementById('chart-tooltip');
    if (!canvas || !tooltip) return;

    canvas.addEventListener('mousemove', function (e) {
        if (!_chartState) return;
        const { src, labels, n, pL, pR, pT, cH, xS, W } = _chartState;
        const rect = canvas.getBoundingClientRect();
        const mx   = e.clientX - rect.left;

        // Cari index terdekat
        let nearest = -1, minDist = Infinity;
        for (let i = 0; i < n; i++) {
            const cx = pL + i * xS;
            const d  = Math.abs(mx - cx);
            if (d < minDist) { minDist = d; nearest = i; }
        }
        // Hanya aktif jika dalam area chart (toleransi 30px dari tepi kiri/kanan)
        if (nearest < 0 || mx < pL - 8 || mx > W - pR + 8) {
            tooltip.style.display = 'none';
            drawChart();
            return;
        }

        drawChart(nearest);

        // Susun konten tooltip
        const rawLabel = labels[nearest] || '';
        // Cari label yang valid terdekat jika kosong
        let displayLabel = rawLabel;
        if (!displayLabel) {
            for (let k = nearest; k >= 0; k--) {
                if (labels[k]) { displayLabel = labels[k]; break; }
            }
        }

        let html = `<div style="font-size:11px;font-weight:600;color:var(--md-on-surface-variant);margin-bottom:6px">${displayLabel}</div>`;
        if (activeMetrics.gmv && src.gmv[nearest] !== undefined) {
            html += `<div style="display:flex;align-items:center;gap:6px;margin-bottom:3px">
                <span style="width:10px;height:10px;border-radius:50%;background:${METRIC_COLOR.gmv};flex-shrink:0"></span>
                <span style="font-size:12px;color:var(--md-on-surface)">GMV&nbsp;&nbsp;<strong>${fmtGmvTooltip(src.gmv[nearest])}</strong></span>
            </div>`;
        }
        if (activeMetrics.pesanan && src.pesanan[nearest] !== undefined) {
            html += `<div style="display:flex;align-items:center;gap:6px">
                <span style="width:10px;height:10px;border-radius:50%;background:${METRIC_COLOR.pesanan};flex-shrink:0"></span>
                <span style="font-size:12px;color:var(--md-on-surface)">Produk&nbsp;&nbsp;<strong>${src.pesanan[nearest].toLocaleString('id')} pcs</strong></span>
            </div>`;
        }
        tooltip.innerHTML = html;

        // Posisi tooltip: di atas titik, hindari overflow kanan/kiri
        const xOnCanvas = pL + nearest * xS;
        const canvasW   = canvas.offsetWidth;
        const ttW       = 150;
        let left = xOnCanvas - ttW / 2;
        if (left < 4)           left = 4;
        if (left + ttW > canvasW - 4) left = canvasW - ttW - 4;

        tooltip.style.left    = left + 'px';
        tooltip.style.top     = '4px';
        tooltip.style.display = 'block';
    });

    canvas.addEventListener('mouseleave', function () {
        tooltip.style.display = 'none';
        drawChart();
    });
}


window.switchView = function (v) {
    curView = v;
    document.getElementById('btn-h').className = 'dash-view-chip' + (v === 'harian'   ? ' active' : '');
    document.getElementById('btn-m').className = 'dash-view-chip' + (v === 'mingguan' ? ' active' : '');
    drawChart();
};

document.getElementById('storeFilter').addEventListener('change', function () {
    curStore = this.value;
    updateMetrics();
    drawChart();
});

// ── Date Range Picker ────────────────────────────────────
(function () {
    const trigger = document.getElementById('drp-trigger');
    const panel   = document.getElementById('drp-panel');

    function openPanel() {
        panel.style.display = 'block';
        trigger.classList.add('open');
    }
    function closePanel() {
        panel.style.display = 'none';
        trigger.classList.remove('open');
    }

    window.drpToggle = function () {
        panel.style.display === 'none' ? openPanel() : closePanel();
    };

    window.drpPreset = function (key) {
        closePanel();
        const params = new URLSearchParams(window.location.search);
        params.set('period', key);
        params.delete('date_from');
        params.delete('date_to');
        window.location.href = '{{ route("dashboard") }}?' + params.toString();
    };

    window.drpClear = function () {
        closePanel();
        window.location.href = '{{ route("dashboard") }}';
    };

    // Close on outside click
    document.addEventListener('click', function (e) {
        if (!document.getElementById('drp-wrap').contains(e.target)) closePanel();
    });
})();

updateMetrics();
renderRanking();
setTimeout(() => { drawChart(); initChartHover(); }, 80);
</script>
@endpush
