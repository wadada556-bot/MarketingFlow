/* Dashboard JS — data injected via window.DASH (set in blade before this script) */

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
    if (e.key === 'Escape') closeStockPanel();
});

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
