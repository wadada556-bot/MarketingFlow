/* Dashboard JS — data injected via window.DASH (set in blade before this script) */
/* global Chart */

function sendPrompt(text) { console.log('[sendPrompt]', text); }

function toggleStock(uid) {
    var body    = document.getElementById('stock-body-' + uid);
    var chevron = document.getElementById('stock-chevron-' + uid);
    var open    = body.style.display !== 'none';
    body.style.display      = open ? 'none' : 'block';
    chevron.style.transform = open ? 'rotate(0deg)' : 'rotate(180deg)';
}

// ── Stock alerts offcanvas ───────────────────────────────
function openStockPanel() {
    var panel   = document.getElementById('stock-panel');
    var overlay = document.getElementById('stock-panel-overlay');
    if (!panel || !overlay) return;
    overlay.classList.add('open');
    panel.classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeStockPanel() {
    var panel   = document.getElementById('stock-panel');
    var overlay = document.getElementById('stock-panel-overlay');
    if (!panel || !overlay) return;
    overlay.classList.remove('open');
    panel.classList.remove('open');
    document.body.style.overflow = '';
}
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { closeStockPanel(); closeAdsPanel(); }
});

// ── Ads performance offcanvas ────────────────────────────
function openAdsPanel() {
    var panel   = document.getElementById('ads-panel');
    var overlay = document.getElementById('ads-panel-overlay');
    if (!panel || !overlay) return;
    overlay.classList.add('open');
    panel.classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeAdsPanel() {
    var panel   = document.getElementById('ads-panel');
    var overlay = document.getElementById('ads-panel-overlay');
    if (!panel || !overlay) return;
    overlay.classList.remove('open');
    panel.classList.remove('open');
    document.body.style.overflow = '';
}

// ── Filter chips panel iklan ─────────────────────────────
let adsActiveFilters = new Set();

function adsFilter(key) {
    if (key === 'all') {
        adsActiveFilters.clear();
    } else {
        adsActiveFilters.has(key) ? adsActiveFilters.delete(key) : adsActiveFilters.add(key);
    }
    applyAdsFilter();
}

function applyAdsFilter() {
    const showAll = adsActiveFilters.size === 0;

    document.querySelectorAll('.ads-filter-chip, #ads-panel .stock-filter-chip').forEach(chip => {
        const f  = chip.dataset.filter;
        const on = (f === 'all') ? showAll : adsActiveFilters.has(f);
        chip.classList.toggle('active', on);
    });

    let visible = 0;
    document.querySelectorAll('#ads-panel-body .ads-row').forEach(row => {
        const status  = row.dataset.status;
        const lowRoas = row.dataset.lowroas === '1';
        const match   = showAll
            || (adsActiveFilters.has('aktif')    && status === 'active' && !lowRoas)
            || (adsActiveFilters.has('lowroas')  && lowRoas)
            || (adsActiveFilters.has('nonaktif') && status !== 'active');
        row.style.display = match ? '' : 'none';
        if (match) visible++;
    });

    const empty = document.getElementById('ads-filter-empty');
    if (empty) empty.style.display = visible === 0 ? 'block' : 'none';
}

// ── Filter chips panel stok (multi-select, OR) ───────────
let stockActiveFilters = new Set();

function stockFilter(key) {
    if (key === 'all') {
        stockActiveFilters.clear();
    } else {
        stockActiveFilters.has(key) ? stockActiveFilters.delete(key) : stockActiveFilters.add(key);
    }
    applyStockFilter();
}

function applyStockFilter() {
    const showAll = stockActiveFilters.size === 0;

    document.querySelectorAll('.stock-filter-chip').forEach(chip => {
        const f  = chip.dataset.filter;
        const on = (f === 'all') ? showAll : stockActiveFilters.has(f);
        chip.classList.toggle('active', on);
    });

    let visible = 0;
    document.querySelectorAll('#stock-panel .stock-panel-body .stock-group').forEach(g => {
        const isUrgent    = g.dataset.urgent === '1';
        const isKritis    = g.dataset.status === 'danger';
        const isMenipis   = g.dataset.status === 'warning';
        const isMissingPo = g.dataset.missingPo === '1';
        const match = showAll
            || (stockActiveFilters.has('urgent')   && isUrgent)
            || (stockActiveFilters.has('kritis')   && isKritis)
            || (stockActiveFilters.has('menipis')  && isMenipis)
            || (stockActiveFilters.has('belum-po') && isMissingPo);
        g.style.display = match ? '' : 'none';
        if (match) visible++;
    });

    const empty = document.getElementById('stock-filter-empty');
    if (empty) empty.style.display = visible === 0 ? 'block' : 'none';
}

// ── Data dari server (injected via window.DASH) ──────────
const COLORS             = window.DASH.colors;
const STORES             = window.DASH.stores;
const STORE_METRICS      = window.DASH.storeMetrics;
const CHART_METRIC_DATA  = window.DASH.chartMetricData;
const CHART_STORE_METRIC = window.DASH.chartStoreMetric;
const DEFAULT_METRICS    = window.DASH.defaultMetrics;

let curView       = 'harian';
let curStore      = 'all';
let activeMetrics = { gmv: true, pesanan: true };

const METRIC_COLOR = { gmv: '#1D9E75', pesanan: '#378ADD' };

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
    const otherKey = key === 'gmv' ? 'pesanan' : 'gmv';
    if (activeMetrics[key] && !activeMetrics[otherKey]) return;
    activeMetrics[key] = !activeMetrics[key];

    const btn = document.getElementById('toggle-' + key);
    if (activeMetrics[key]) {
        btn.style.background = key === 'gmv' ? 'rgba(29,158,117,.1)' : 'rgba(55,138,221,.1)';
        btn.style.opacity = '1';
        btn.querySelector('i').style.display = '';
    } else {
        btn.style.background = 'transparent';
        btn.style.opacity = '0.45';
        btn.querySelector('i').style.display = 'none';
    }
    updateChart();
}

// ── Chart.js (replaces custom canvas) ───────────────────
let _chart = null;

function fmtGmvTooltip(v) {
    if (v >= 1000) return 'Rp ' + (v / 1000).toFixed(1).replace('.', ',') + ' jt';
    if (v > 0)     return 'Rp ' + v.toLocaleString('id') + ' rb';
    return 'Rp 0';
}

function getChartSrc() {
    if (curStore !== 'all' && CHART_STORE_METRIC[curStore]) {
        const s = CHART_STORE_METRIC[curStore];
        return curView === 'harian'
            ? { labels: CHART_METRIC_DATA.daily.labels,  gmv: s.daily.gmv,  pesanan: s.daily.pesanan }
            : { labels: CHART_METRIC_DATA.weekly.labels, gmv: s.weekly.gmv, pesanan: s.weekly.pesanan };
    }
    return curView === 'harian' ? CHART_METRIC_DATA.daily : CHART_METRIC_DATA.weekly;
}

function buildChartDatasets(src) {
    const n = src.labels.length;
    const showDots = n <= 14;
    const datasets = [];

    if (activeMetrics.gmv) {
        datasets.push({
            label: 'GMV',
            data: src.gmv,
            borderColor: METRIC_COLOR.gmv,
            backgroundColor: METRIC_COLOR.gmv,
            borderWidth: 2,
            pointRadius: showDots ? 3 : 0,
            pointHoverRadius: 5,
            tension: 0.3,
            yAxisID: 'yGmv',
        });
    }
    if (activeMetrics.pesanan) {
        datasets.push({
            label: 'Pesanan',
            data: src.pesanan,
            borderColor: METRIC_COLOR.pesanan,
            backgroundColor: METRIC_COLOR.pesanan,
            borderWidth: 2,
            pointRadius: showDots ? 3 : 0,
            pointHoverRadius: 5,
            tension: 0.3,
            yAxisID: activeMetrics.gmv ? 'yPes' : 'yGmv',
        });
    }
    return datasets;
}

function buildChartScales(src) {
    const maxGmv = src.gmv ? Math.max(...src.gmv) : 0;
    const maxPes = src.pesanan ? Math.max(...src.pesanan) : 0;
    const showBoth = activeMetrics.gmv && activeMetrics.pesanan;

    const gmvTick = {
        color: METRIC_COLOR.gmv,
        font: { size: 10 },
        callback: v => v >= 1000 ? (v / 1000).toFixed(0) + ' jt' : v + ' rb',
        maxTicksLimit: 5,
    };
    const pesTick = {
        color: METRIC_COLOR.pesanan,
        font: { size: 10 },
        callback: v => v.toLocaleString('id'),
        maxTicksLimit: 5,
    };

    const scales = {};
    if (activeMetrics.gmv) {
        scales.yGmv = {
            type: 'linear', position: 'left',
            ticks: gmvTick,
            grid: { color: 'rgba(111,121,119,0.10)', lineWidth: 0.5 },
            border: { display: false },
        };
    }
    if (activeMetrics.pesanan && showBoth) {
        scales.yPes = {
            type: 'linear', position: 'right',
            ticks: pesTick,
            grid: { drawOnChartArea: false },
            border: { display: false },
        };
    } else if (activeMetrics.pesanan && !activeMetrics.gmv) {
        scales.yGmv = {
            type: 'linear', position: 'left',
            ticks: pesTick,
            grid: { color: 'rgba(111,121,119,0.10)', lineWidth: 0.5 },
            border: { display: false },
        };
    }
    return scales;
}

function initChart() {
    const canvas = document.getElementById('salesCanvas');
    if (!canvas) return;
    const src = getChartSrc();

    _chart = new Chart(canvas, {
        type: 'line',
        data: {
            labels: src.labels,
            datasets: buildChartDatasets(src),
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: { duration: 200 },
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'var(--md-surface-container-lowest, #fff)',
                    borderColor: 'rgba(111,121,119,0.2)',
                    borderWidth: 1,
                    titleColor: 'var(--md-on-surface-variant, #555)',
                    bodyColor: 'var(--md-on-surface, #1c1c1c)',
                    titleFont: { size: 11, weight: '600' },
                    bodyFont: { size: 12 },
                    padding: 10,
                    callbacks: {
                        label: function (ctx) {
                            const label = ctx.dataset.label || '';
                            const val   = ctx.parsed.y;
                            if (label === 'GMV') return ' GMV  ' + fmtGmvTooltip(val);
                            return ' Pesanan  ' + val.toLocaleString('id') + ' pcs';
                        },
                        labelColor: function (ctx) {
                            return { borderColor: ctx.dataset.borderColor, backgroundColor: ctx.dataset.backgroundColor };
                        },
                    },
                },
            },
            scales: buildChartScales(src),
        },
    });
}

function updateChart() {
    if (!_chart) return;
    const src = getChartSrc();
    _chart.data.labels   = src.labels;
    _chart.data.datasets = buildChartDatasets(src);
    _chart.options.scales = buildChartScales(src);
    _chart.update();
}

window.switchView = function (v) {
    curView = v;
    const btnH = document.getElementById('btn-h');
    const btnM = document.getElementById('btn-m');
    if (btnH) btnH.className = 'dash-view-chip' + (v === 'harian'   ? ' active' : '');
    if (btnM) btnM.className = 'dash-view-chip' + (v === 'mingguan' ? ' active' : '');
    updateChart();
};

document.getElementById('storeFilter').addEventListener('change', function () {
    curStore = this.value;
    updateMetrics();
    updateChart();
});

// ── Date Range Picker ────────────────────────────────────
(function () {
    const trigger = document.getElementById('drp-trigger');
    const panel   = document.getElementById('drp-panel');
    const DASH    = window.DASH;

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
        window.location.href = DASH.routes.dashboard + '?' + params.toString();
    };

    window.drpClear = function () {
        closePanel();
        window.location.href = DASH.routes.dashboard;
    };

    document.addEventListener('click', function (e) {
        if (!document.getElementById('drp-wrap').contains(e.target)) closePanel();
    });
})();

// ── DRP: kalender rentang kustom ─────────────────────────
(function () {
    const DRP_TODAY      = window.DASH.today;
    const DRP_INIT_START = window.DASH.initStart;
    const DRP_INIT_END   = window.DASH.initEnd;
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
            const disabled = ds > DRP_TODAY;
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
        if (ds > DRP_TODAY) return;
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
        params.set('period', 'custom');
        params.set('date_from', selStart);
        params.set('date_to', selEnd);
        window.location.href = window.DASH.routes.dashboard + '?' + params.toString();
    };

    render();
})();

updateMetrics();
renderRanking();

// Init Chart.js after first paint
requestAnimationFrame(() => requestAnimationFrame(() => {
    initChart();
    if (window.ResizeObserver) {
        const canvasWrap = document.getElementById('salesCanvas').parentElement;
        new ResizeObserver(() => updateChart()).observe(canvasWrap);
    } else {
        window.addEventListener('resize', () => updateChart());
    }
}));

// ── Lazy-load Peringatan Stok via AJAX ───────────────────
(function () {
    var cardWrap  = document.getElementById('stock-card-lazy');
    var panelWrap = document.getElementById('stock-panel-lazy');
    if (!cardWrap) return;

    fetch(window.DASH.routes.stockAlerts, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.ok ? r.json() : Promise.reject(r.status); })
        .then(function (data) {
            cardWrap.innerHTML = data.card || '';
            if (panelWrap) panelWrap.innerHTML = data.panel || '';
        })
        .catch(function () {
            cardWrap.innerHTML =
                '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">' +
                '<div><p class="dash-section-title">Peringatan Stok</p>' +
                '<p class="dash-section-subtitle">Produk butuh perhatian</p></div>' +
                '<span class="md-chip surface"><i class="bi bi-wifi-off" style="font-size:11px"></i> Offline</span></div>' +
                '<div style="padding:24px 16px;background:var(--md-surface-container-low);border-radius:var(--md-shape-sm);text-align:center">' +
                '<i class="bi bi-wifi-off" style="font-size:2rem;color:var(--md-outline);display:block;margin-bottom:8px"></i>' +
                '<p style="font-size:14px;color:var(--md-on-surface-variant);margin:0">Data stok tidak tersedia saat ini</p></div>';
        });
})();
