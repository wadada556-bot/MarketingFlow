@extends('components.layouts.app')

@section('title', 'History Penjualan')

@php
    $rp = fn ($n) => 'Rp ' . number_format((int) $n, 0, ',', '.');
    $today = \Carbon\Carbon::today();
    $cleanStore = fn ($name) => trim(preg_replace([
        '/^(?:Shop\s*\|\s*)?(?:tokopedia|tiktok)\s*-\s*/i',  // strip prefix channel (spasi/tanpa spasi)
        '/\s*\(TTS\)\s*$/i',                                  // strip suffix (TTS)
    ], '', $name ?? '')) ?: ($name ?? '—');
    $channelIcon = fn ($name) => str_contains(strtolower($name), 'tiktok')
        ? '<i class="bi bi-tiktok" style="color:#010101;"></i>'
        : '<i class="bi bi-shop" style="color:#42B549;"></i>';
    $medalIcons = [1 => 'bi-trophy-fill', 2 => 'bi-award-fill', 3 => 'bi-award-fill'];
    $medalTitles = [1 => 'Best seller #1 (emas)', 2 => 'Best seller #2 (perak)', 3 => 'Best seller #3 (perunggu)'];
    $medal = function ($rank) use ($medalIcons, $medalTitles) {
        if (!$rank) return '';
        return '<span class="sh-medal sh-medal-' . $rank . '" title="' . $medalTitles[$rank] . '"><i class="bi ' . $medalIcons[$rank] . '"></i></span>';
    };
@endphp

@push('styles')
<link rel="stylesheet" href="{{ asset('css/dashboard.css') }}?v={{ filemtime(public_path('css/dashboard.css')) }}">
<style>
    .sh-panel {
        background: var(--md-surface-container-lowest);
        border: 1px solid var(--md-outline-variant);
        border-radius: var(--md-shape-lg);
        padding: 20px;
    }
    .sh-panel-head {
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 18px;
    }
    .sh-panel-head-icon {
        width: 38px; height: 38px;
        border-radius: var(--md-shape-full);
        background: var(--md-primary-container);
        color: var(--md-primary);
        display: flex; align-items: center; justify-content: center;
        font-size: 16px;
        flex-shrink: 0;
    }
    .sh-panel-title { font-size: 15px; font-weight: 700; color: var(--md-on-surface); }
    .sh-panel-subtitle { font-size: 12px; color: var(--md-on-surface-variant); margin-top: 1px; }

    .sh-toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        flex-wrap: wrap;
    }
    .sh-stat-group {
        display: flex;
        align-items: center;
        gap: 28px;
        flex-wrap: wrap;
    }
    .sh-stat { display: flex; align-items: center; gap: 12px; }
    .sh-stat-icon {
        width: 42px; height: 42px;
        border-radius: var(--md-shape-full);
        display: flex; align-items: center; justify-content: center;
        font-size: 18px;
        flex-shrink: 0;
    }
    .sh-stat-label { font-size: 12px; color: var(--md-on-surface-variant); font-weight: 500; }
    .sh-stat-value { font-size: 20px; font-weight: 700; color: var(--md-on-surface); line-height: 1.3; }

    .sh-toolbar-actions {
        display: flex;
        align-items: center;
        gap: 16px;
        flex-wrap: wrap;
        margin-left: auto;
    }
    .sh-search {
        position: relative;
        min-width: 220px;
    }
    .sh-search i {
        position: absolute; left: 12px; top: 50%; transform: translateY(-50%);
        color: var(--md-on-surface-variant); font-size: 14px;
    }
    .sh-search input {
        width: 100%;
        padding: 8px 12px 8px 34px;
        font-size: 13px;
        border: 1px solid var(--md-outline);
        border-radius: var(--md-shape-full);
        background: var(--md-surface);
        color: var(--md-on-surface);
        transition: border-color .12s;
    }
    .sh-search input:focus {
        outline: none;
        border-color: var(--md-primary);
    }
    .sh-sort { display: flex; align-items: center; gap: 8px; }
    .sh-sort-label { font-size: 12px; color: var(--md-on-surface-variant); font-weight: 500; }

    .sh-medal {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 18px; height: 18px;
        border-radius: 50%;
        font-size: 10px;
        margin-left: 6px;
        vertical-align: middle;
        box-shadow: inset 0 0 0 1px rgba(0,0,0,.08);
    }
    .sh-medal-1 { background: #FFD700; color: #6b5500; }
    .sh-medal-2 { background: #C0C0C0; color: #4d4d4d; }
    .sh-medal-3 { background: #CD7F32; color: #4a2e12; }

    @media (max-width: 768px) {
        .sh-toolbar-actions { margin-left: 0; width: 100%; }
        .sh-search { flex: 1; min-width: 0; }
    }
</style>
@endpush

@section('content')

    @php
        $presets = [
            'today' => ['Hari ini',        $today->toDateString(),                              $today->toDateString()],
            '7d'    => ['7 hari terakhir', $today->copy()->subDays(6)->toDateString(),           $today->toDateString()],
            '30d'   => ['30 hari terakhir',$today->copy()->subDays(29)->toDateString(),          $today->toDateString()],
            'month' => ['Bulan ini',       $today->copy()->startOfMonth()->toDateString(),       $today->toDateString()],
            'year'  => ['Tahun ini',       $today->copy()->startOfYear()->toDateString(),         $today->toDateString()],
        ];
        $activeKey = null;
        foreach ($presets as $key => [$label, $pf, $pt]) {
            $pf = max($pf, $minDate);
            $pt = min($pt, $maxDate);
            if ($from === $pf && $to === $pt) { $activeKey = $key; break; }
        }
        $periodLabel = $activeKey ? $presets[$activeKey][0] : 'Kustom';
    @endphp

    <div class="md-page-header">
        <div>
            <h1>History Penjualan</h1>
        </div>
        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
            <select class="dash-select" id="storeFilter">
                <option value="">Semua toko</option>
                @foreach ($storeOptions as $s)
                    <option value="{{ $s->store_id }}" @selected($storeId === (int) $s->store_id)>{{ $cleanStore($s->store_name) }}</option>
                @endforeach
            </select>

            {{-- ── Date Range Picker ── --}}
            <div style="position:relative;" id="drp-wrap">
                <div id="drp-trigger" class="drp-trigger" onclick="drpToggle()">
                    <div class="drp-prefix" id="drp-prefix">{{ $periodLabel }}:</div>
                    <div class="drp-inputs">
                        <input id="drp-from" class="drp-input" readonly
                               value="{{ \Carbon\Carbon::parse($from)->format('M d, Y') }}">
                        <span class="drp-sep">-</span>
                        <input id="drp-to" class="drp-input" readonly
                               value="{{ \Carbon\Carbon::parse($to)->format('M d, Y') }}">
                    </div>
                    <span class="drp-icon">
                        <svg fill="none" stroke="currentColor" stroke-width="4" viewBox="0 0 48 48" width="16" height="16">
                            <path d="M7 22h34M14 5v8m20-8v8M8 41h32a1 1 0 0 0 1-1V10a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v30a1 1 0 0 0 1 1Z"/>
                        </svg>
                    </span>
                </div>

                <div id="drp-panel" class="drp-panel" style="display:none;">
                    <div class="drp-body">
                        {{-- Preset cepat --}}
                        <div class="drp-side">
                            @foreach ($presets as $key => [$label, $pf, $pt])
                                <button type="button" class="drp-preset {{ $activeKey === $key ? 'active' : '' }}"
                                        onclick="drpPreset('{{ $pf }}','{{ $pt }}')">{{ $label }}</button>
                            @endforeach
                            <button type="button" class="drp-preset drp-preset-clear" onclick="drpClear()">
                                <i class="bi bi-x-circle" style="font-size:12px;margin-right:5px"></i>Hapus filter
                            </button>
                        </div>

                        {{-- Kalender rentang kustom --}}
                        <div class="drp-cal">
                            <div class="drp-cal-tab">
                                <span class="drp-kustom-tab {{ $activeKey === null ? 'active' : '' }}">Kustom</span>
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

    @include('components.alert')

    {{-- ── Ringkasan + Toolbar pencarian/urut ────────────────── --}}
    @php
        $summaryStats = [
            ['icon' => 'bi-boxes', 'bg' => 'var(--md-primary-container)',   'fg' => 'var(--md-primary)',   'label' => 'Total Qty Terjual', 'value' => number_format($totals['qty'], 0, ',', '.')],
            ['icon' => 'bi-tags',  'bg' => 'var(--md-tertiary-container)',  'fg' => 'var(--md-tertiary)',  'label' => 'Jumlah Produk',     'value' => number_format($totals['products'], 0, ',', '.')],
        ];
    @endphp
    <div class="sh-panel mb-4">
        <div class="sh-toolbar">
            <div class="sh-stat-group">
                @foreach ($summaryStats as $stat)
                    <div class="sh-stat">
                        <div class="sh-stat-icon" style="background:{{ $stat['bg'] }};color:{{ $stat['fg'] }};">
                            <i class="bi {{ $stat['icon'] }}"></i>
                        </div>
                        <div>
                            <div class="sh-stat-label">{{ $stat['label'] }}</div>
                            <div class="sh-stat-value">{{ $stat['value'] }}</div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="sh-toolbar-actions">
                <div class="sh-search">
                    <i class="bi bi-search"></i>
                    <input type="text" id="productSearch" placeholder="Cari produk / SKU...">
                </div>
                @if (!empty($products))
                    <div class="sh-sort">
                        <span class="sh-sort-label">Urutkan</span>
                        <button class="sort-btn md-choice-chip checked" data-sort="qty" data-dir="desc" type="button">
                            <i class="bi bi-check md-check-icon"></i>Qty <span class="sort-arrow">↓</span>
                        </button>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- ── Daftar produk ──────────────────────────────────────── --}}
    @if (empty($products))
        <div style="text-align:center;padding:48px 16px;color:var(--md-on-surface-variant);">
            <i class="bi bi-inbox" style="font-size:40px;opacity:.5;"></i>
            <p style="margin-top:12px;">Belum ada data penjualan untuk filter ini.</p>
            <p style="font-size:12px;">Jalankan <code>php artisan sales:backfill</code> untuk menarik histori.</p>
        </div>
    @else
        <div class="accordion" id="salesAccordion">
            @foreach ($products as $p)
                @php
                    $pid = 'p_' . md5($p['parent_sku']);
                    $exportRows = [];
                    foreach ($p['variants'] as $v) {
                        $row = [$v['sku']];
                        foreach ($storeColumns as $sc) {
                            $q = $v['store_qty'][(int) $sc->store_id] ?? null;
                            $row[] = $q === null ? '-' : $q;
                        }
                        $row[] = $v['qty'];
                        $exportRows[] = $row;
                    }
                @endphp
                <div class="accordion-item sales-product-item"
                     data-search="{{ strtolower($p['parent_sku'] . ' ' . $p['product_name'] . ' ' . implode(' ', array_column($p['variants'], 'sku'))) }}"
                     data-qty="{{ $p['qty'] }}"
                     data-parent-sku="{{ $p['parent_sku'] }}"
                     data-export="{{ json_encode($exportRows) }}"
                     style="border:1px solid var(--md-outline-variant);border-radius:var(--md-shape-lg);
                            margin-bottom:12px;overflow:hidden;background:var(--md-surface-container-lowest);">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button"
                                data-bs-toggle="collapse" data-bs-target="#{{ $pid }}"
                                style="background:var(--md-surface-container-lowest);box-shadow:none;">
                            <div class="d-flex flex-wrap align-items-center w-100" style="gap:16px;padding-right:16px;">
                                <div style="min-width:0;flex:1;">
                                    <div style="font-weight:700;color:var(--md-on-surface);font-size:19px;letter-spacing:.1px;">{{ $p['parent_sku'] }}</div>
                                    <div class="mt-2 d-flex gap-1 flex-wrap">
                                        @foreach ($p['channels'] as $cName)
                                            <span class="md-chip secondary">{!! $channelIcon($cName) !!} {{ $cName }}</span>
                                        @endforeach
                                    </div>
                                </div>
                                <div style="text-align:right;">
                                    <div style="font-size:11px;color:var(--md-on-surface-variant);font-weight:500;">Total Qty Terjual</div>
                                    <div style="font-size:18px;font-weight:700;color:var(--md-primary);">{{ number_format($p['qty'], 0, ',', '.') }}</div>
                                </div>
                            </div>
                        </button>
                    </h2>
                    <div id="{{ $pid }}" class="accordion-collapse collapse">
                        <div class="accordion-body" style="padding:16px;background:var(--md-surface-container-low);">
                            {{-- Toolbar --}}
                            <div class="d-flex align-items-center gap-2 flex-wrap mb-3">
                                <div style="position:relative;flex:1;min-width:160px;max-width:280px;">
                                    <i class="bi bi-search" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);font-size:13px;color:var(--md-on-surface-variant);"></i>
                                    <input type="text" class="sku-search form-control form-control-sm"
                                           placeholder="Cari SKU variasi..." style="padding-left:34px;">
                                </div>
                                <button type="button" class="btn-md-filled export-csv-btn" style="padding:7px 16px;font-size:13px;">
                                    <i class="bi bi-download"></i> Export
                                </button>
                            </div>
                            {{-- Table --}}
                            <div class="md-table-wrap">
                                <div class="table-responsive">
                                    <table class="md-table">
                                        @php $thBg = 'background:var(--md-secondary-container);color:var(--md-on-secondary-container);'; @endphp
                                        <thead>
                                            <tr>
                                                <th style="{{ $thBg }}">SKU Variation</th>
                                                @foreach ($storeColumns as $sc)
                                                    <th style="text-align:right;white-space:nowrap;{{ $thBg }}">{{ $cleanStore($sc->store_name) }}</th>
                                                @endforeach
                                                <th style="text-align:right;white-space:nowrap;{{ $thBg }}">Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($p['variants'] as $v)
                                                <tr data-sku="{{ strtolower($v['sku']) }}">
                                                    <td style="font-weight:600;border-right:1px solid var(--md-outline-variant);border-bottom:1px solid var(--md-outline-variant);white-space:nowrap;">
                                                        {{ $v['sku'] }}
                                                    </td>
                                                    @foreach ($storeColumns as $sc)
                                                        @php
                                                            $sid = (int) $sc->store_id;
                                                            $q = $v['store_qty'][$sid] ?? null;
                                                            $rank = $p['store_rank'][$sid][$v['sku']] ?? null;
                                                        @endphp
                                                        <td style="text-align:right;border-bottom:1px solid var(--md-outline-variant);white-space:nowrap;
                                                                   {{ $q === null ? 'color:var(--md-on-surface-variant);' : '' }}">
                                                            {{ $q === null ? '-' : number_format($q, 0, ',', '.') }}{!! $medal($rank) !!}
                                                        </td>
                                                    @endforeach
                                                    <td style="text-align:right;font-weight:600;border-bottom:1px solid var(--md-outline-variant);border-left:1px solid var(--md-outline-variant);">
                                                        {{ number_format($v['qty'], 0, ',', '.') }}
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                        @php $tfCell = 'padding:14px 16px;font-weight:700;background:var(--md-surface-container);border-top:2px solid var(--md-outline-variant);'; @endphp
                                        <tfoot>
                                            <tr>
                                                <td style="{{ $tfCell }}color:var(--md-on-surface);">TOTAL</td>
                                                @foreach ($storeColumns as $sc)
                                                    @php $q = $p['store_qty'][(int) $sc->store_id] ?? null; @endphp
                                                    <td style="{{ $tfCell }}text-align:right;{{ $q === null ? 'color:var(--md-on-surface-variant);font-weight:400;' : '' }}">
                                                        {{ $q === null ? '-' : number_format($q, 0, ',', '.') }}
                                                    </td>
                                                @endforeach
                                                <td style="{{ $tfCell }}text-align:right;border-left:1px solid var(--md-outline-variant);">{{ number_format($p['qty'], 0, ',', '.') }}</td>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

@endsection

@push('scripts')
<script>
window.SH = {
    route:     "{{ route('sales-history.index') }}",
    today:     @json($maxDate),
    minDate:   @json($minDate),
    initStart: @json($activeKey === null ? $from : null),
    initEnd:   @json($activeKey === null ? $to   : null),
};

document.getElementById('storeFilter').addEventListener('change', function () {
    const params = new URLSearchParams(window.location.search);
    if (this.value) params.set('store_id', this.value); else params.delete('store_id');
    window.location.href = window.SH.route + '?' + params.toString();
});

// ── Date Range Picker (trigger + preset) ─────────────────
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

    window.drpPreset = function (from, to) {
        closePanel();
        const params = new URLSearchParams(window.location.search);
        params.set('date_from', from);
        params.set('date_to', to);
        window.location.href = window.SH.route + '?' + params.toString();
    };

    window.drpClear = function () {
        closePanel();
        window.location.href = window.SH.route;
    };

    document.addEventListener('click', function (e) {
        if (!document.getElementById('drp-wrap').contains(e.target)) closePanel();
    });
})();

// ── DRP: kalender rentang kustom ─────────────────────────
(function () {
    const DRP_TODAY      = window.SH.today;
    const DRP_MIN        = window.SH.minDate;
    const DRP_INIT_START = window.SH.initStart;
    const DRP_INIT_END   = window.SH.initEnd;
    const MONTHS = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];

    let selStart = DRP_INIT_START;
    let selEnd   = DRP_INIT_END;

    const pad      = n => (n < 10 ? '0' + n : '' + n);
    const isoOf    = (y, m, d) => y + '-' + pad(m + 1) + '-' + pad(d);
    const parseISO = ds => { const p = ds.split('-').map(Number); return new Date(p[0], p[1] - 1, p[2]); };
    const fmt      = ds => { const d = parseISO(ds); return d.getDate() + ' ' + MONTHS[d.getMonth()] + ' ' + d.getFullYear(); };

    const anchor = parseISO(selStart || DRP_TODAY);
    let base = new Date(anchor.getFullYear(), anchor.getMonth(), 1);

    function renderMonth(daysId, labelId, year, month) {
        document.getElementById(labelId).textContent = pad(month + 1) + ' / ' + year;
        const first = new Date(year, month, 1);
        const lead  = (first.getDay() + 6) % 7;
        const total = new Date(year, month + 1, 0).getDate();
        let html = '';
        for (let i = 0; i < lead; i++) html += '<span class="drp-day empty"></span>';
        for (let d = 1; d <= total; d++) {
            const ds       = isoOf(year, month, d);
            const disabled = ds > DRP_TODAY || ds < DRP_MIN;
            let cls = 'drp-day';
            if (disabled) cls += ' disabled';
            if (ds === selStart || ds === selEnd) cls += ' selected';
            else if (selStart && selEnd && ds > selStart && ds < selEnd) cls += ' in-range';
            if (ds === DRP_TODAY) cls += ' today';
            html += `<button type="button" class="${cls}" ${disabled ? 'disabled' : ''} onclick="drpPickDay('${ds}', event)">${d}</button>`;
        }
        document.getElementById(daysId).innerHTML = html;
    }

    function render() {
        const y = base.getFullYear(), m = base.getMonth();
        renderMonth('drp-m1-days', 'drp-m1-label', y, m);
        const nxt = new Date(y, m + 1, 1);
        renderMonth('drp-m2-days', 'drp-m2-label', nxt.getFullYear(), nxt.getMonth());

        const txt = document.getElementById('drp-range-text');
        if (selStart && selEnd) txt.textContent = fmt(selStart) + '  –  ' + fmt(selEnd);
        else if (selStart)      txt.textContent = fmt(selStart) + '  –  …';
        else                    txt.textContent = 'Pilih rentang tanggal';

        document.getElementById('drp-apply').disabled = !(selStart && selEnd);
    }

    window.drpPickDay = function (ds, ev) {
        if (ev) ev.stopPropagation();
        if (ds > DRP_TODAY || ds < DRP_MIN) return;
        if (!selStart || (selStart && selEnd)) { selStart = ds; selEnd = null; }
        else if (ds < selStart)                { selEnd = selStart; selStart = ds; }
        else                                   { selEnd = ds; }
        render();
    };

    window.drpNav = function (delta) {
        base = new Date(base.getFullYear(), base.getMonth() + delta, 1);
        render();
    };

    window.drpApplyCustom = function () {
        if (!(selStart && selEnd)) return;
        const params = new URLSearchParams(window.location.search);
        params.set('date_from', selStart);
        params.set('date_to', selEnd);
        window.location.href = window.SH.route + '?' + params.toString();
    };

    render();
})();
</script>
<script>
    // Lazy-load SheetJS (≈1 MB) hanya saat pertama kali klik Export — bukan tiap
    // buka halaman. Versi di-pin (hindari redirect "latest"). Di-cache via promise.
    let __xlsxPromise = null;
    function ensureXLSX() {
        if (window.XLSX) return Promise.resolve(window.XLSX);
        if (__xlsxPromise) return __xlsxPromise;
        __xlsxPromise = new Promise((resolve, reject) => {
            const s = document.createElement('script');
            s.src = 'https://cdn.sheetjs.com/xlsx-0.20.3/package/dist/xlsx.full.min.js';
            s.onload = () => resolve(window.XLSX);
            s.onerror = () => { __xlsxPromise = null; reject(new Error('Gagal memuat modul export')); };
            document.head.appendChild(s);
        });
        return __xlsxPromise;
    }
</script>
<script>
    // Global product search
    document.getElementById('productSearch')?.addEventListener('input', function (e) {
        const q = e.target.value.toLowerCase().trim();
        document.querySelectorAll('.sales-product-item').forEach(function (el) {
            el.style.display = el.dataset.search.includes(q) ? '' : 'none';
        });
    });

    // Global sort (reorder product accordion items) — toggle asc/desc
    document.querySelectorAll('.sort-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const key = this.dataset.sort;
            const container = document.getElementById('salesAccordion');
            if (!container) return;

            // Tombol aktif diklik lagi → balik arah; tombol baru → mulai dari desc
            if (this.classList.contains('checked')) {
                this.dataset.dir = this.dataset.dir === 'asc' ? 'desc' : 'asc';
            } else {
                this.dataset.dir = 'desc';
            }
            const dir = this.dataset.dir;

            const items = Array.from(container.querySelectorAll('.sales-product-item'));
            items.sort((a, b) => {
                const diff = Number(a.dataset[key]) - Number(b.dataset[key]);
                return dir === 'asc' ? diff : -diff;
            });
            items.forEach(el => container.appendChild(el));

            // Reset tombol lain ke non-aktif + panah default
            document.querySelectorAll('.sort-btn').forEach(b => {
                if (b !== this) {
                    b.classList.remove('checked');
                    b.dataset.dir = 'desc';
                    const ar = b.querySelector('.sort-arrow');
                    if (ar) ar.textContent = '↓';
                }
            });
            this.classList.add('checked');
            const arrow = this.querySelector('.sort-arrow');
            if (arrow) arrow.textContent = dir === 'asc' ? '↑' : '↓';
        });
    });

    // Per-accordion SKU variation search
    document.addEventListener('input', function (e) {
        if (!e.target.classList.contains('sku-search')) return;
        const q = e.target.value.toLowerCase().trim();
        const tbody = e.target.closest('.accordion-body').querySelector('tbody');
        if (!tbody) return;
        tbody.querySelectorAll('tr').forEach(function (row) {
            row.style.display = (!q || (row.dataset.sku || '').includes(q)) ? '' : 'none';
        });
    });

    // Excel export per product (SheetJS dimuat lazy di klik pertama)
    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.export-csv-btn');
        if (!btn) return;
        const item = e.target.closest('.sales-product-item');
        const parentSku = item.dataset.parentSku || 'export';
        const rows = JSON.parse(item.dataset.export || '[]');
        const headers = @json(array_merge(['SKU Variation'], $storeColumns->map(fn ($sc) => $cleanStore($sc->store_name))->all(), ['Total']));

        btn.disabled = true;
        ensureXLSX().then((XLSX) => {
            const ws = XLSX.utils.aoa_to_sheet([headers, ...rows]);
            const wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, ws, 'Sales');
            XLSX.writeFile(wb, parentSku + '_sales.xlsx');
        }).catch((err) => {
            alert(err.message || 'Gagal export.');
        }).finally(() => {
            btn.disabled = false;
        });
    });
</script>
@endpush
