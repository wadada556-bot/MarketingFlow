@extends('components.layouts.app')

@section('title', 'Products')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/products.css') }}?v={{ filemtime(public_path('css/products.css')) }}">
    <style>
        .js-expand { transition: transform .15s ease; }
    </style>
@endpush

@push('scripts')
<script>
(function () {
    const VARIANTS_URL      = @json(route('products.variants'));
    const PRICE_HISTORY_URL = @json(route('products.price-history'));
    const UPDATE_HPP_URL    = @json(route('products.update-hpp'));
    const UPDATE_PRICE_URL  = @json(route('products.update-price'));
    const BULK_PRICE_URL    = @json(route('products.bulk-update-price'));
    const BULK_SEARCH_URL   = @json(route('products.bulk-price-search'));
    const BULK_APPLY_URL    = @json(route('products.bulk-price-apply'));
    const STORE_ID          = @json($storeId);
    const HPP_EMPTY         = @json($hppEmpty);
    const CSRF              = document.querySelector('meta[name="csrf-token"]').content;

    const num    = (v) => Number(v || 0).toLocaleString('id-ID');
    const rupiah = (v) => (v === null || v === undefined)
        ? '<span style="color:var(--md-on-surface-variant)">-</span>'
        : 'Rp' + Number(v).toLocaleString('id-ID');
    const dash   = (v) => (v === null || v === undefined || v === '')
        ? '<span style="color:var(--md-on-surface-variant)">-</span>' : v;
    // HPP: 0 dianggap "belum terisi" → tampilkan "-".
    const hppDisplay = (h) => Number(h) > 0
        ? 'Rp' + num(h)
        : '<span style="color:var(--md-on-surface-variant)">-</span>';

    function buildDetail(d, cols) {
        const variants = d.variants || [];
        if (!variants.length) {
            return '<div style="padding:16px 44px;color:var(--md-on-surface-variant);font-size:13px">Tidak ada varian.</div>';
        }
        // Semua lebar kolom (termasuk Varian/Product ID/SKU ID) dihitung DI LUAR
        // fungsi ini dari geometri nyata baris induk (lihat computeDetailCols),
        // lalu lebar tabel dikunci persis = jumlahnya — supaya table-layout:fixed
        // tak punya sisa/kekurangan ruang utk didistribusikan ulang (itu yg
        // sebelumnya bikin kolom Stok/PO/HPP/Harga Jual melenceng dari baris induk
        // di lebar layar yg berbeda dari saat pertama kali diuji).
        const w = cols || {varian:240, skuId:170, stok:110, po:100, hpp:170, harga:220, aksi:110};
        const total = w.varian + w.skuId + w.stok + w.po + w.hpp + w.harga + w.aksi;
        const rows = variants.map(v => `
            <tr>
                <td style="font-size:13px">
                    <p class="mb-0 fw-medium" style="color:var(--md-on-surface)">${v.label ?? v.sku}</p>
                    <p class="mb-0" style="font-size:11.5px;color:var(--md-on-surface-variant)">${v.sku}</p>
                </td>
                <td style="font-size:12.5px;color:var(--md-on-surface-variant)">${dash(v.sku_id)}</td>
                <td class="text-end" style="font-size:13px">${num(v.stok)}</td>
                <td class="text-end" style="font-size:13px">${num(v.po)}</td>
                <td class="text-end" style="font-size:13px;white-space:nowrap">
                    <span class="products-field js-hpp-edit" data-sku="${encodeURIComponent(v.sku)}" data-hpp="${v.hpp}" title="Isi / ubah HPP">
                        <span class="js-hpp-val">${hppDisplay(v.hpp)}</span>
                        <i class="bi bi-pencil"></i>
                    </span>
                </td>
                <td class="text-end" style="font-size:13px;white-space:nowrap">
                    <div>${rupiah(v.retail)}</div>
                    <span class="products-field js-price-edit" data-sku="${encodeURIComponent(v.sku)}" data-promo="${v.promo ?? 0}" title="Ubah harga promo" style="margin-top:2px">
                        <span style="font-size:11px;color:var(--md-on-surface-variant)">Promo:</span>
                        <span class="js-promo-val">${rupiah(v.promo)}</span>
                        <i class="bi bi-pencil"></i>
                    </span>
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm js-price-history d-inline-flex align-items-center gap-1"
                            data-sku="${encodeURIComponent(v.sku)}"
                            title="Lihat histori perubahan harga"
                            style="background:var(--md-surface-container-high);color:var(--md-on-surface);border:1px solid var(--md-outline);border-radius:var(--md-shape-xs);font-size:12px;padding:2px 8px">
                        <i class="bi bi-clock-history" style="font-size:12px"></i> Histori
                    </button>
                </td>
            </tr>`).join('');
        return `<div style="padding:16px 0 8px 44px;overflow-x:visible">
            <table class="table align-middle mb-0" style="table-layout:fixed;width:${total}px">
              <colgroup>
                <col style="width:${w.varian}px">
                <col style="width:${w.skuId}px">
                <col style="width:${w.stok}px"><col style="width:${w.po}px">
                <col style="width:${w.hpp}px"><col style="width:${w.harga}px">
                <col style="width:${w.aksi}px">
              </colgroup>
              <thead><tr style="color:var(--md-on-surface-variant);font-size:12px">
                <th>Varian</th><th>SKU ID</th>
                <th class="text-end">Stok</th><th class="text-end">PO</th>
                <th class="text-end">HPP</th><th class="text-end">Harga Jual</th>
                <th class="text-center">Aksi</th>
              </tr></thead>
              <tbody>${rows}</tbody>
            </table></div>`;
    }

    // Hitung lebar tiap kolom tabel detail dari geometri NYATA baris induk
    // (bukan CSS auto-layout, yg terbukti tak stabil lintas lebar layar).
    // Stok/PO/HPP/Harga Jual = persis sama dgn kolom yg sama di baris induk.
    // Leading (Varian+SKU ID) = SISA ruang persis dari ujung kiri (indentLeft,
    // tempat tabel detail mulai) sampai ujung kanan kolom Harga Jual baris
    // induk (target kanan) dikurangi Stok/PO/HPP/Harga/Aksi — jadi tabel
    // detail otomatis menempel pas di KEDUA ujung sekaligus (kiri: Varian
    // mulai sejajar dgn indent di bawah Produk; kanan: Aksi berakhir persis
    // di ujung Harga Jual) dari SATU perhitungan geometri, tanpa perlu
    // dorongan margin terpisah. (Percobaan sebelumnya yg men-cap leading lalu
    // mendorongnya via margin-left:auto menyisakan celah kosong lebar di kiri
    // — regresi yg diperbaiki di sini.)
    function computeDetailCols(catalogRow, tdEl) {
        const cells = catalogRow ? catalogRow.children : [];
        const rect  = (i) => cells[i] ? cells[i].getBoundingClientRect() : null;
        const rStok = rect(1), rPo = rect(2), rHpp = rect(3), rHarga = rect(4);
        const stok  = rStok  ? Math.round(rStok.width)  : 110;
        const po    = rPo    ? Math.round(rPo.width)    : 100;
        const hpp   = rHpp   ? Math.round(rHpp.width)   : 170;
        const harga = rHarga ? Math.round(rHarga.width) : 220;
        const aksi  = 110;

        // Indent = X awal tabel detail (kiri td + padding wrapper 44px kiri).
        const tdStyle = getComputedStyle(tdEl);
        const tdPadL  = parseFloat(tdStyle.paddingLeft) || 0;
        const tdRect  = tdEl.getBoundingClientRect();
        const indentLeft = tdRect.left + tdPadL + 44 /* wrapper padding-left */;

        const targetRight = rHarga ? rHarga.right : (indentLeft + 1200);
        const fixedSum = stok + po + hpp + harga + aksi;
        let leading = Math.round(targetRight - indentLeft - fixedSum);
        leading = Math.max(80, leading);

        let skuId = 170, varian = leading - skuId;
        if (varian < 40) {
            // Leading kepepet (kolom Produk baris induk sempit) — sisakan
            // Varian min 40px, sisanya ke SKU ID.
            varian = 40;
            skuId = Math.max(30, leading - varian);
        }
        return {varian, skuId, stok, po, hpp, harga, aksi};
    }

    // ── Salin SKU induk / Product ID ─────────────────────────────────────────
    document.addEventListener('click', function (e) {
        const copyBtn = e.target.closest('.js-copy-value');
        if (!copyBtn) return;
        e.stopPropagation();
        const val = copyBtn.dataset.copy;
        const showCopied = () => {
            const icon = copyBtn.querySelector('i');
            const original = icon.className;
            icon.className = 'bi bi-check-lg';
            copyBtn.style.color = 'var(--md-primary)';
            setTimeout(() => { icon.className = original; copyBtn.style.color = ''; }, 1000);
        };
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(val).then(showCopied).catch(() => {
                const ta = document.createElement('textarea');
                ta.value = val;
                ta.style.position = 'fixed';
                ta.style.opacity = '0';
                document.body.appendChild(ta);
                ta.select();
                try { document.execCommand('copy'); showCopied(); } catch (err) {}
                ta.remove();
            });
        }
    });

    document.addEventListener('click', function (e) {
        const subrow = e.target.closest('.products-subrow');
        if (!subrow) return;
        const btn  = subrow.querySelector('.js-expand');
        const next = subrow.nextElementSibling;

        const setToggleState = (open) => {
            btn.setAttribute('aria-expanded', String(open));
            btn.querySelector('.js-expand-label').textContent = open ? 'Tutup' : 'Buka';
            btn.querySelector('.js-expand-icon').className = 'bi js-expand-icon ' + (open ? 'bi-chevron-up' : 'bi-chevron-down');
        };

        // Toggle bila detail sudah ada
        if (next && next.classList.contains('js-detail-row')) {
            const open = next.style.display !== 'none';
            next.style.display = open ? 'none' : '';
            setToggleState(!open);
            return;
        }

        const catalogRow = subrow.previousElementSibling;

        // Buat baris detail + fetch lazy
        const tr = document.createElement('tr');
        tr.className = 'js-detail-row';
        const td = document.createElement('td');
        td.colSpan = 5;
        td.style.background = 'var(--md-surface-container-low)';
        // (kolom header: Produk, Total Stok, Total PO, HPP, Harga Jual)
        td.innerHTML = '<div style="padding:16px 44px;color:var(--md-on-surface-variant);font-size:13px">Memuat varian…</div>';
        tr.appendChild(td);
        subrow.after(tr);
        setToggleState(true);

        // Ukur geometri SETELAH td disisipkan (colspan=5 langsung dpt lebar
        // baris penuh terlepas dari isinya), tapi SEBELUM konten varian
        // memenuhinya, supaya catalogRow jg belum sempat bergeser.
        const cols = computeDetailCols(catalogRow, td);

        const url = `${VARIANTS_URL}?product_id=${encodeURIComponent(subrow.dataset.productId)}&store_id=${STORE_ID ?? ''}${HPP_EMPTY ? '&hpp_empty=1' : ''}`;
        fetch(url)
            .then(r => r.json())
            .then(d => {
                td.innerHTML = buildDetail(d, cols);
                alignDetailTableRight(catalogRow, td);
            })
            .catch(() => { td.innerHTML = '<div style="padding:16px 44px;color:var(--md-error);font-size:13px">Gagal memuat varian.</div>'; });
    });

    // computeDetailCols() sudah menghitung lebar tabel supaya ujung kanannya
    // pas di ujung kolom Harga Jual baris induk — fungsi ini cuma koreksi
    // halus (beberapa px) utk selisih rendering riil (mis. padding internal
    // td) yg tak tertangkap oleh perhitungan geometri. Dibatasi kecil (≤40px)
    // supaya tak pernah menciptakan celah lebar seperti margin-left:auto
    // versi sebelumnya (lihat commit 85449e8, direvisi di sini).
    function alignDetailTableRight(catalogRow, tdEl) {
        const table = tdEl.querySelector('table');
        const targetRight = catalogRow.children[4].getBoundingClientRect().right; // kolom Harga Jual
        const diff = targetRight - table.getBoundingClientRect().right;
        if (Math.abs(diff) > 0.5 && Math.abs(diff) <= 40) {
            const currentML = parseFloat(getComputedStyle(table).marginLeft) || 0;
            table.style.marginLeft = Math.max(0, currentML + diff) + 'px';
        }
    }

    // ── Histori harga (modal) ────────────────────────────────────────────────
    const typeLabel = (t) => t === 'retail' ? 'Harga Normal' : (t === 'promotion' ? 'Harga Promo' : t);

    function renderHistory(d) {
        const changes = d.changes || [];
        if (!changes.length) {
            return '<div style="padding:24px;text-align:center;color:var(--md-on-surface-variant);font-size:13px">Belum ada perubahan harga tercatat untuk SKU ini.</div>';
        }
        const rows = changes.map(c => `
            <tr>
                <td style="font-size:13px">${typeLabel(c.type)}</td>
                <td class="text-end" style="font-size:13px;color:var(--md-on-surface-variant)">${rupiah(c.old)}</td>
                <td class="text-center" style="color:var(--md-on-surface-variant)"><i class="bi bi-arrow-right"></i></td>
                <td class="text-end" style="font-size:13px;font-weight:600">${rupiah(c.new)}</td>
                <td class="text-end" style="font-size:12.5px;color:var(--md-on-surface-variant)">${c.changed_at}</td>
            </tr>`).join('');
        return `<div style="overflow-x:auto">
            <table class="table align-middle mb-0">
              <thead><tr style="color:var(--md-on-surface-variant);font-size:12px">
                <th>Jenis Harga</th><th class="text-end">Harga Awal</th><th></th>
                <th class="text-end">Harga Berubah</th><th class="text-end">Waktu</th>
              </tr></thead>
              <tbody>${rows}</tbody>
            </table></div>`;
    }

    const overlay = document.getElementById('price-history-modal');
    const modalBody = document.getElementById('price-history-body');
    const modalTitle = document.getElementById('price-history-title');

    function closeModal() { overlay.style.display = 'none'; }
    overlay.addEventListener('click', (e) => { if (e.target === overlay || e.target.closest('.js-modal-close')) closeModal(); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeModal(); });

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.js-price-history');
        if (!btn) return;
        const skuEnc = btn.dataset.sku;
        modalTitle.textContent = 'Histori Harga — ' + decodeURIComponent(skuEnc);
        modalBody.innerHTML = '<div style="padding:24px;text-align:center;color:var(--md-on-surface-variant);font-size:13px">Memuat histori…</div>';
        overlay.style.display = 'flex';

        const url = `${PRICE_HISTORY_URL}?sku_code=${skuEnc}&store_id=${STORE_ID ?? ''}`;
        fetch(url)
            .then(r => r.json())
            .then(d => { modalBody.innerHTML = renderHistory(d); })
            .catch(() => { modalBody.innerHTML = '<div style="padding:24px;text-align:center;color:var(--md-error);font-size:13px">Gagal memuat histori.</div>'; });
    });

    // ── Isi / ubah HPP (modal) ───────────────────────────────────────────────
    const hppOverlay = document.getElementById('hpp-modal');
    const hppForm    = document.getElementById('hpp-form');
    const hppInput   = document.getElementById('hpp-input');
    const hppSkuLbl  = document.getElementById('hpp-sku-label');
    const hppSave    = document.getElementById('hpp-save');
    let   hppTargetBtn = null;

    function closeHpp() { hppOverlay.style.display = 'none'; hppTargetBtn = null; }
    hppOverlay.addEventListener('click', (e) => { if (e.target === hppOverlay || e.target.closest('.js-hpp-cancel')) closeHpp(); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && hppOverlay.style.display === 'flex') closeHpp(); });

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.js-hpp-edit');
        if (!btn) return;
        hppTargetBtn = btn;
        hppSkuLbl.textContent = decodeURIComponent(btn.dataset.sku);
        hppInput.value = Number(btn.dataset.hpp) > 0 ? btn.dataset.hpp : '';
        hppOverlay.style.display = 'flex';
        setTimeout(() => hppInput.focus(), 40);
    });

    hppForm.addEventListener('submit', function (e) {
        e.preventDefault();
        if (!hppTargetBtn) return;
        const sku = decodeURIComponent(hppTargetBtn.dataset.sku);
        const val = Math.max(0, Math.floor(Number(hppInput.value) || 0));
        hppSave.disabled = true;

        fetch(UPDATE_HPP_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            body: JSON.stringify({ sku_code: sku, hpp: val }),
        })
        .then(r => r.ok ? r.json() : Promise.reject(r))
        .then(d => {
            // Saat filter "HPP belum terisi" aktif & HPP kini terisi (>0), baris
            // tak lagi cocok filter → hilangkan otomatis dari daftar varian.
            if (HPP_EMPTY && Number(d.hpp) > 0) {
                const tr        = hppTargetBtn.closest('tr');
                const tbody     = tr.parentElement;
                const detailRow = tr.closest('.js-detail-row');
                tr.remove();
                // Varian terakhir listing ini terisi → hilangkan juga baris induk
                // + baris detailnya dari tabel utama (tak lagi cocok filter).
                if (!tbody.querySelector('tr')) {
                    const subrow    = detailRow ? detailRow.previousElementSibling : null;
                    const catalogRow = subrow ? subrow.previousElementSibling : null;
                    if (detailRow) detailRow.remove();
                    if (subrow && subrow.classList.contains('products-subrow')) subrow.remove();
                    if (catalogRow && catalogRow.classList.contains('js-catalog-row')) catalogRow.remove();
                }
            } else {
                const cell = hppTargetBtn.closest('td');
                cell.querySelector('.js-hpp-val').innerHTML = hppDisplay(d.hpp);
                hppTargetBtn.dataset.hpp = d.hpp;
            }
            closeHpp();
        })
        .catch(() => { alert('Gagal menyimpan HPP. Coba lagi.'); })
        .finally(() => { hppSave.disabled = false; });
    });

    // ── Ubah harga promo (satu SKU atau bulk per product_id) ────────────────
    const priceOverlay = document.getElementById('price-modal');
    const priceForm    = document.getElementById('price-form');
    const priceInput   = document.getElementById('price-input');
    const priceLabel   = document.getElementById('price-context-label');
    const priceSave    = document.getElementById('price-save');
    let   priceMode     = null;   // 'single' | 'bulk'
    let   priceTarget   = null;   // button yg memicu modal

    function closePrice() { priceOverlay.style.display = 'none'; priceMode = null; priceTarget = null; }
    priceOverlay.addEventListener('click', (e) => { if (e.target === priceOverlay || e.target.closest('.js-price-cancel')) closePrice(); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && priceOverlay.style.display === 'flex') closePrice(); });

    document.addEventListener('click', function (e) {
        const single = e.target.closest('.js-price-edit');
        if (single) {
            priceMode = 'single'; priceTarget = single;
            priceLabel.textContent = 'SKU: ' + decodeURIComponent(single.dataset.sku);
            priceInput.value = Number(single.dataset.promo) > 0 ? single.dataset.promo : '';
            priceOverlay.style.display = 'flex';
            setTimeout(() => priceInput.focus(), 40);
            return;
        }
        const bulk = e.target.closest('.js-bulk-price-edit');
        if (bulk) {
            priceMode = 'bulk'; priceTarget = bulk;
            priceLabel.textContent = 'Semua varian pada Product ID ' + bulk.dataset.productId;
            priceInput.value = '';
            priceOverlay.style.display = 'flex';
            setTimeout(() => priceInput.focus(), 40);
        }
    });

    priceForm.addEventListener('submit', function (e) {
        e.preventDefault();
        if (!priceTarget) return;
        const val = Math.max(0, Math.floor(Number(priceInput.value) || 0));
        priceSave.disabled = true;

        if (priceMode === 'single') {
            const sku = decodeURIComponent(priceTarget.dataset.sku);
            fetch(UPDATE_PRICE_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                body: JSON.stringify({ sku_code: sku, store_id: STORE_ID, promotion_price: val }),
            })
            .then(r => r.ok ? r.json() : Promise.reject(r))
            .then(d => {
                const cell = priceTarget.closest('td');
                cell.querySelector('.js-promo-val').innerHTML = rupiah(d.promotion_price);
                priceTarget.dataset.promo = d.promotion_price;
                closePrice();
            })
            .catch(() => { alert('Gagal menyimpan harga promo. Coba lagi.'); })
            .finally(() => { priceSave.disabled = false; });
        } else {
            const productId = priceTarget.dataset.productId;
            fetch(BULK_PRICE_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                body: JSON.stringify({ product_id: productId, store_id: STORE_ID, promotion_price: val }),
            })
            .then(r => r.ok ? r.json() : Promise.reject(r))
            .then(d => {
                // Patch rentang harga promo di baris produk (katalog).
                const row = document.querySelector(`.js-catalog-row[data-product-id="${CSS.escape(productId)}"]`);
                if (row) {
                    const span = row.querySelector('.js-promo-range');
                    if (span) span.innerHTML = rupiah(d.promotion_price);

                    // Bila detail varian sudah ter-load, samakan tampilan tiap varian juga.
                    const subrow    = row.nextElementSibling;
                    const detailRow = subrow ? subrow.nextElementSibling : null;
                    if (detailRow && detailRow.classList.contains('js-detail-row')) {
                        detailRow.querySelectorAll('.js-promo-val').forEach(span => { span.innerHTML = rupiah(d.promotion_price); });
                        detailRow.querySelectorAll('.js-price-edit').forEach(btn => { btn.dataset.promo = d.promotion_price; });
                    }
                }
                closePrice();
            })
            .catch(() => { alert('Gagal menyimpan harga promo. Coba lagi.'); })
            .finally(() => { priceSave.disabled = false; });
        }
    });

    // ── Update harga promo massal (popup) ────────────────────────────────────
    const bulkOverlay   = document.getElementById('bulk-price-modal');
    const bulkOpenBtn   = document.getElementById('bulk-price-open');
    const bulkKeyword   = document.getElementById('bulk-price-keyword');
    const bulkSearchBtn = document.getElementById('bulk-price-search-btn');
    const bulkSearchMsg = document.getElementById('bulk-price-search-msg');
    const bulkResultsEl = document.getElementById('bulk-price-results');
    const bulkCheckAll  = document.getElementById('bulk-price-check-all');
    const bulkCountEl   = document.getElementById('bulk-price-count');
    const bulkPriceIn   = document.getElementById('bulk-price-input');
    const bulkNextBtn   = document.getElementById('bulk-price-next');
    const bulkBackBtn   = document.getElementById('bulk-price-back');
    const bulkConfirmPrice = document.getElementById('bulk-price-confirm-price');
    const bulkConfirmCount = document.getElementById('bulk-price-confirm-count');
    const bulkConfirmList  = document.getElementById('bulk-price-confirm-list');
    const stepSearch = document.getElementById('bulk-price-step-search');
    const stepSelect = document.getElementById('bulk-price-step-select');
    const stepConfirm = document.getElementById('bulk-price-step-confirm');

    let bulkResults = [];      // hasil pencarian mentah dari server
    let bulkChecked = new Set(); // key = `${store_id}|${sku_code}`
    let bulkStep = 'search';   // 'search' | 'select' | 'confirm'
    const bulkKey = (r) => `${r.store_id}|${r.sku_code}`;

    // Satu sku_code bisa muncul di beberapa listing (product_id) pada toko yg
    // sama — checked-state disimpan per (store,sku), jadi harus dedupe di sini
    // supaya SKU yg sama tak dihitung/dikirim dobel.
    function bulkSelectedItems() {
        const seen = new Map();
        bulkResults.forEach(r => {
            const key = bulkKey(r);
            if (bulkChecked.has(key) && !seen.has(key)) seen.set(key, r);
        });
        return [...seen.values()];
    }

    function bulkShowStep(step) {
        bulkStep = step;
        stepSearch.style.display  = step === 'search'  ? '' : 'none';
        stepSelect.style.display  = step === 'select'  ? '' : 'none';
        stepConfirm.style.display = step === 'confirm' ? '' : 'none';
        bulkBackBtn.style.display = step === 'search' ? 'none' : '';
        bulkNextBtn.textContent = step === 'confirm' ? 'Update Sekarang' : 'Lanjut';
        bulkNextBtn.style.display = step === 'search' ? 'none' : '';
    }

    function bulkResetAll() {
        bulkResults = [];
        bulkChecked = new Set();
        bulkKeyword.value = '';
        bulkPriceIn.value = '';
        bulkSearchMsg.textContent = 'Hasil dicari lintas semua toko. Pilih baris yang ingin diupdate di langkah berikutnya.';
        bulkResultsEl.innerHTML = '';
        bulkShowStep('search');
    }

    function openBulk() {
        bulkResetAll();
        bulkOverlay.style.display = 'flex';
        setTimeout(() => bulkKeyword.focus(), 40);
    }
    function closeBulk() { bulkOverlay.style.display = 'none'; }

    bulkOpenBtn.addEventListener('click', openBulk);
    bulkOverlay.addEventListener('click', (e) => { if (e.target === bulkOverlay || e.target.closest('.js-bulk-price-cancel')) closeBulk(); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && bulkOverlay.style.display === 'flex') closeBulk(); });

    function runBulkSearch() {
        const keyword = bulkKeyword.value.trim();
        if (keyword.length < 2) {
            bulkSearchMsg.textContent = 'Masukkan minimal 2 karakter.';
            return;
        }
        bulkSearchBtn.disabled = true;
        bulkSearchMsg.textContent = 'Mencari…';
        fetch(`${BULK_SEARCH_URL}?keyword=${encodeURIComponent(keyword)}`)
            .then(r => r.ok ? r.json() : Promise.reject(r))
            .then(d => {
                bulkResults = d.results || [];
                bulkChecked = new Set();
                if (!bulkResults.length) {
                    bulkSearchMsg.textContent = 'Tidak ada SKU yang cocok.';
                    return;
                }
                renderBulkResults();
                bulkShowStep('select');
            })
            .catch(() => { bulkSearchMsg.textContent = 'Gagal mencari. Coba lagi.'; })
            .finally(() => { bulkSearchBtn.disabled = false; });
    }
    bulkSearchBtn.addEventListener('click', runBulkSearch);
    bulkKeyword.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); runBulkSearch(); } });

    function updateBulkCount() {
        bulkCountEl.textContent = `${bulkChecked.size} dari ${bulkResults.length} SKU dipilih`;
        bulkCheckAll.checked = bulkResults.length > 0 && bulkChecked.size === bulkResults.length;
    }

    function renderBulkResults() {
        // Kelompokkan: toko → product_id → daftar sku.
        const byStore = new Map();
        bulkResults.forEach(r => {
            if (!byStore.has(r.store_id)) byStore.set(r.store_id, { name: r.store_name, byProduct: new Map() });
            const store = byStore.get(r.store_id);
            if (!store.byProduct.has(r.product_id)) store.byProduct.set(r.product_id, []);
            store.byProduct.get(r.product_id).push(r);
        });

        let html = '';
        byStore.forEach((store, storeId) => {
            const storeSkus = [...store.byProduct.values()].flat();
            html += `<div style="border-bottom:1px solid var(--md-outline-variant,var(--md-outline))">
                <div class="d-flex align-items-center gap-2 js-bulk-group" data-level="store" data-store="${storeId}"
                     style="padding:8px 12px;background:var(--md-surface-container-low);font-weight:600;font-size:13px;cursor:pointer">
                    <input type="checkbox" class="js-bulk-check" data-level="store" data-store="${storeId}">
                    <i class="bi bi-shop"></i> ${store.name}
                    <span style="font-weight:400;color:var(--md-on-surface-variant);font-size:12px">(${storeSkus.length} SKU)</span>
                </div>`;
            store.byProduct.forEach((skus, productId) => {
                html += `<div style="padding:6px 12px 6px 28px;display:flex;align-items:center;gap:8px;font-size:12.5px;color:var(--md-on-surface-variant)">
                    <input type="checkbox" class="js-bulk-check" data-level="product" data-store="${storeId}" data-product="${productId}">
                    Product ID: <span style="color:var(--md-on-surface)">${productId}</span>
                    <span>(${skus.length} SKU)</span>
                </div>`;
                skus.forEach(sku => {
                    const key = bulkKey(sku);
                    html += `<div style="padding:4px 12px 4px 48px;display:flex;align-items:center;gap:8px;font-size:12.5px">
                        <input type="checkbox" class="js-bulk-check" data-level="sku" data-key="${encodeURIComponent(key)}">
                        <span style="word-break:break-all">${sku.label ? sku.label + ' — ' : ''}${sku.sku_code}</span>
                        <span style="margin-left:auto;color:var(--md-on-surface-variant);white-space:nowrap">${rupiah(sku.promotion_price)}</span>
                    </div>`;
                });
            });
            html += `</div>`;
        });
        bulkResultsEl.innerHTML = html;
        syncBulkCheckboxUi();
        updateBulkCount();
    }

    function syncBulkCheckboxUi() {
        bulkResultsEl.querySelectorAll('.js-bulk-check[data-level="sku"]').forEach(cb => {
            const key = decodeURIComponent(cb.dataset.key);
            cb.checked = bulkChecked.has(key);
        });
        bulkResultsEl.querySelectorAll('.js-bulk-check[data-level="product"]').forEach(cb => {
            const storeId = cb.dataset.store, productId = cb.dataset.product;
            const skus = bulkResults.filter(r => String(r.store_id) === storeId && r.product_id === productId);
            cb.checked = skus.length > 0 && skus.every(r => bulkChecked.has(bulkKey(r)));
        });
        bulkResultsEl.querySelectorAll('.js-bulk-check[data-level="store"]').forEach(cb => {
            const storeId = cb.dataset.store;
            const skus = bulkResults.filter(r => String(r.store_id) === storeId);
            cb.checked = skus.length > 0 && skus.every(r => bulkChecked.has(bulkKey(r)));
        });
    }

    bulkResultsEl.addEventListener('change', function (e) {
        const cb = e.target.closest('.js-bulk-check');
        if (!cb) return;
        const level = cb.dataset.level;
        let affected = [];
        if (level === 'sku') {
            affected = [bulkResults.find(r => bulkKey(r) === decodeURIComponent(cb.dataset.key))].filter(Boolean);
        } else if (level === 'product') {
            affected = bulkResults.filter(r => String(r.store_id) === cb.dataset.store && r.product_id === cb.dataset.product);
        } else if (level === 'store') {
            affected = bulkResults.filter(r => String(r.store_id) === cb.dataset.store);
        }
        affected.forEach(r => { cb.checked ? bulkChecked.add(bulkKey(r)) : bulkChecked.delete(bulkKey(r)); });
        syncBulkCheckboxUi();
        updateBulkCount();
    });

    bulkCheckAll.addEventListener('change', function () {
        bulkChecked = bulkCheckAll.checked ? new Set(bulkResults.map(bulkKey)) : new Set();
        syncBulkCheckboxUi();
        updateBulkCount();
    });

    bulkNextBtn.addEventListener('click', function () {
        if (bulkStep === 'select') {
            if (!bulkChecked.size) { alert('Pilih minimal satu SKU.'); return; }
            const val = Math.max(0, Math.floor(Number(bulkPriceIn.value) || 0));
            if (!bulkPriceIn.value || Number(bulkPriceIn.value) < 0) { alert('Isi harga promo baru.'); return; }
            const items = bulkSelectedItems();
            bulkConfirmPrice.textContent = rupiah(val).replace(/<[^>]+>/g, '-');
            bulkConfirmCount.textContent = items.length;
            bulkConfirmList.innerHTML = items.map(r => `<div>${r.store_name} — ${r.sku_code}</div>`).join('');
            bulkShowStep('confirm');
        } else if (bulkStep === 'confirm') {
            const val = Math.max(0, Math.floor(Number(bulkPriceIn.value) || 0));
            const items = bulkSelectedItems().map(r => ({ store_id: r.store_id, sku_code: r.sku_code }));
            bulkNextBtn.disabled = true;
            fetch(BULK_APPLY_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                body: JSON.stringify({ items, promotion_price: val }),
            })
            .then(r => r.ok ? r.json() : Promise.reject(r))
            .then(() => {
                alert('Harga promo berhasil diperbarui.');
                closeBulk();
                window.location.reload();
            })
            .catch(() => { alert('Gagal menerapkan harga massal. Coba lagi.'); })
            .finally(() => { bulkNextBtn.disabled = false; });
        }
    });

    bulkBackBtn.addEventListener('click', function () {
        if (bulkStep === 'confirm') bulkShowStep('select');
        else if (bulkStep === 'select') bulkShowStep('search');
    });
})();
</script>
@endpush

@section('content')

    <div class="products-sticky-header">
        {{-- Judul + kebaruan data disatukan dalam satu blok setinggi 72px,
             sejajar dgn header "Marketing Flow" di sidebar. --}}
        <div class="products-title-block">
            <div style="min-width:0">
                <h1>Products</h1>

                {{-- Kebaruan data per sumber (toko terpilih) — cek sebelum export.
                     Hover tiap item untuk tanggal & jam pasti. --}}
                <div class="products-freshness-row d-flex align-items-center gap-2">
                    <span class="d-inline-flex align-items-center gap-1">
                        <i class="bi bi-clock-history"></i> Diperbarui
                    </span>
                    @foreach($freshness as $f)
                        <span class="d-inline-flex align-items-center gap-1"
                              style="background:var(--md-surface-container-high);border-radius:var(--md-shape-xs);padding:2px 10px"
                              title="{{ $f['label'] }} ({{ $f['hint'] }}){{ $f['exact'] ? ' — ' . $f['exact'] : '' }}">
                            <strong style="color:var(--md-on-surface);font-weight:500">{{ $f['label'] }}:</strong>
                            {{ $f['rel'] }}
                        </span>
                    @endforeach
                </div>
            </div>

            <div class="d-flex align-items-center gap-2" style="flex-shrink:0">
                {{-- Update harga promo massal (lintas toko/produk, dari tempel SKU) --}}
                <button type="button" id="bulk-price-open" class="products-export-btn"
                        title="Update harga promo massal">
                    <i class="bi bi-tags"></i>
                </button>

                {{-- Export seluruh listing+varian toko terpilih ke .xlsx --}}
                <a href="{{ route('products.export', ['store_id' => $storeId]) }}"
                   class="products-export-btn"
                   title="Export semua data toko ini ke Excel">
                    <i class="bi bi-download"></i>
                </a>
            </div>
        </div>

        @include('components.alert')

        <form method="GET" action="{{ route('products.index') }}" class="products-filter-row mb-0 d-flex flex-wrap align-items-center gap-2" autocomplete="off">
        {{-- Pencarian SKU induk --}}
        <div class="input-group" style="max-width:340px;flex:1 1 240px">
            <span class="input-group-text"
                  style="background:var(--md-surface-container-high);border-color:var(--md-outline);border-right:0;border-radius:var(--md-shape-xs) 0 0 var(--md-shape-xs);color:var(--md-on-surface-variant)">
                <i class="bi bi-search" style="font-size:14px"></i>
            </span>
            <input type="text" name="search" value="{{ $search ?? '' }}"
                   class="form-control"
                   placeholder="Cari SKU induk…"
                   style="border-color:var(--md-outline);border-left:0;border-right:{{ !empty($search) ? '0' : '' }};font-size:13.5px;background:transparent;color:var(--md-on-surface)">
            @if(!empty($search))
                <a href="{{ route('products.index', array_filter(['store_id' => $storeId, 'hpp_empty' => $hppEmpty ? 1 : null])) }}"
                   class="input-group-text"
                   style="background:transparent;border-color:var(--md-outline);border-left:0;border-radius:0 var(--md-shape-xs) var(--md-shape-xs) 0;color:var(--md-on-surface-variant);text-decoration:none"
                   title="Hapus pencarian">
                    <i class="bi bi-x-lg" style="font-size:12px"></i>
                </a>
            @endif
        </div>

        {{-- Pemilih toko: harga jual ditampilkan per toko --}}
        @if($stores->isNotEmpty())
            <div class="input-group" style="width:auto">
                <span class="input-group-text"
                      style="background:var(--md-surface-container-high);border-color:var(--md-outline);border-right:0;border-radius:var(--md-shape-xs) 0 0 var(--md-shape-xs);color:var(--md-on-surface-variant)">
                    <i class="bi bi-shop" style="font-size:14px"></i>
                </span>
                <select name="store_id" class="form-select" onchange="this.form.submit()"
                        title="Pilih toko untuk melihat harga"
                        style="border-color:var(--md-outline);border-left:0;border-radius:0 var(--md-shape-xs) var(--md-shape-xs) 0;font-size:13.5px;font-weight:500;min-width:180px;background:transparent;color:var(--md-on-surface)">
                    @foreach($stores as $store)
                        <option value="{{ $store->id }}" @selected($storeId == $store->id)>
                            {{ ucwords($store->name) }}
                        </option>
                    @endforeach
                </select>
            </div>
        @endif

        {{-- Filter chip: hanya listing yang punya varian ber-HPP belum terisi --}}
        <div class="input-group" style="width:auto">
            <span class="input-group-text"
                  style="background:var(--md-surface-container-high);border-color:var(--md-outline);border-right:0;border-radius:var(--md-shape-xs) 0 0 var(--md-shape-xs);color:var(--md-on-surface-variant)">
                <i class="bi bi-cash-stack" style="font-size:14px"></i>
            </span>
            <label class="btn d-inline-flex align-items-center gap-2 m-0"
                   title="Tampilkan hanya produk yang masih ada HPP kosong (mis. bundling)"
                   style="border:1px solid {{ $hppEmpty ? 'var(--md-tertiary)' : 'var(--md-outline)' }};
                          border-left:0;
                          background:{{ $hppEmpty ? 'var(--md-tertiary-container)' : 'transparent' }};
                          color:{{ $hppEmpty ? 'var(--md-on-tertiary-container)' : 'var(--md-on-surface)' }};
                          border-radius:0 var(--md-shape-xs) var(--md-shape-xs) 0;font-size:13.5px;font-weight:{{ $hppEmpty ? 600 : 400 }}">
                <input type="checkbox" name="hpp_empty" value="1" onchange="this.form.submit()" @checked($hppEmpty) class="d-none">
                HPP
                @if($hppEmpty)<i class="bi bi-check-lg" style="font-size:14px"></i>@endif
            </label>
        </div>
    </form>
    </div>

    @include('products.partials.table', ['catalog' => $catalog, 'meta' => $meta])

    @if($catalog->isNotEmpty())
        @include('products.partials.pagination', ['catalog' => $catalog, 'perPage' => $perPage])
    @endif

    {{-- Modal histori harga (dibuka dari tombol "Histori" pada detail varian) --}}
    <div id="price-history-modal"
         style="display:none;position:fixed;inset:0;z-index:1060;background:rgba(0,0,0,.45);
                align-items:center;justify-content:center;padding:16px">
        <div style="background:var(--md-surface);color:var(--md-on-surface);border-radius:var(--md-shape-md,12px);
                    width:100%;max-width:640px;max-height:80vh;overflow:auto;box-shadow:var(--md-elevation-3,0 8px 24px rgba(0,0,0,.2))">
            <div class="d-flex align-items-center justify-content-between"
                 style="padding:16px 20px;border-bottom:1px solid var(--md-outline-variant,var(--md-outline))">
                <h2 id="price-history-title" style="font-size:15px;font-weight:600;margin:0">Histori Harga</h2>
                <button type="button" class="btn btn-sm js-modal-close"
                        title="Tutup"
                        style="background:transparent;border:none;color:var(--md-on-surface-variant);padding:2px 6px">
                    <i class="bi bi-x-lg" style="font-size:15px"></i>
                </button>
            </div>
            <div id="price-history-body" style="padding:12px 8px"></div>
        </div>
    </div>

    {{-- Modal isi/ubah HPP (dibuka dari ikon pensil pada kolom HPP detail varian) --}}
    <div id="hpp-modal"
         style="display:none;position:fixed;inset:0;z-index:1060;background:rgba(0,0,0,.45);
                align-items:center;justify-content:center;padding:16px">
        <div style="background:var(--md-surface);color:var(--md-on-surface);border-radius:var(--md-shape-md,12px);
                    width:100%;max-width:420px;box-shadow:var(--md-elevation-3,0 8px 24px rgba(0,0,0,.2))">
            <div class="d-flex align-items-center justify-content-between"
                 style="padding:16px 20px;border-bottom:1px solid var(--md-outline-variant,var(--md-outline))">
                <h2 style="font-size:15px;font-weight:600;margin:0">Isi / Ubah HPP</h2>
                <button type="button" class="btn btn-sm js-hpp-cancel" title="Tutup"
                        style="background:transparent;border:none;color:var(--md-on-surface-variant);padding:2px 6px">
                    <i class="bi bi-x-lg" style="font-size:15px"></i>
                </button>
            </div>
            <form id="hpp-form" style="padding:20px">
                <p class="mb-1" style="font-size:12.5px;color:var(--md-on-surface-variant)">Seller SKU</p>
                <p id="hpp-sku-label" class="mb-3 fw-medium" style="font-size:14px;color:var(--md-on-surface);word-break:break-all"></p>

                <label for="hpp-input" class="mb-1 d-block" style="font-size:12.5px;color:var(--md-on-surface-variant)">HPP (Rp)</label>
                <input type="number" id="hpp-input" min="0" step="1" inputmode="numeric"
                       class="form-control" placeholder="0"
                       style="border-color:var(--md-outline);font-size:14px;background:var(--md-surface);color:var(--md-on-surface)">
                <p class="mb-0 mt-2" style="font-size:11.5px;color:var(--md-on-surface-variant)">
                    Isi 0 untuk mengosongkan. Nilai ini tidak akan tertimpa saat sync harian.
                </p>

                <div class="d-flex justify-content-end gap-2 mt-4">
                    <button type="button" class="btn js-hpp-cancel"
                            style="background:transparent;border:1px solid var(--md-outline);color:var(--md-on-surface);border-radius:var(--md-shape-xs);font-size:13.5px;padding:8px 16px">
                        Batal
                    </button>
                    <button type="submit" id="hpp-save"
                            style="background:var(--md-primary);color:var(--md-on-primary);border:none;border-radius:var(--md-shape-xs);font-size:13.5px;font-weight:500;padding:8px 20px">
                        Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal ubah harga promo (satu SKU dari detail varian, atau bulk dari baris produk) --}}
    <div id="price-modal"
         style="display:none;position:fixed;inset:0;z-index:1060;background:rgba(0,0,0,.45);
                align-items:center;justify-content:center;padding:16px">
        <div style="background:var(--md-surface);color:var(--md-on-surface);border-radius:var(--md-shape-md,12px);
                    width:100%;max-width:420px;box-shadow:var(--md-elevation-3,0 8px 24px rgba(0,0,0,.2))">
            <div class="d-flex align-items-center justify-content-between"
                 style="padding:16px 20px;border-bottom:1px solid var(--md-outline-variant,var(--md-outline))">
                <h2 style="font-size:15px;font-weight:600;margin:0">Ubah Harga Promo</h2>
                <button type="button" class="btn btn-sm js-price-cancel" title="Tutup"
                        style="background:transparent;border:none;color:var(--md-on-surface-variant);padding:2px 6px">
                    <i class="bi bi-x-lg" style="font-size:15px"></i>
                </button>
            </div>
            <form id="price-form" style="padding:20px">
                <p class="mb-1" style="font-size:12.5px;color:var(--md-on-surface-variant)">Berlaku untuk</p>
                <p id="price-context-label" class="mb-3 fw-medium" style="font-size:14px;color:var(--md-on-surface);word-break:break-all"></p>

                <label for="price-input" class="mb-1 d-block" style="font-size:12.5px;color:var(--md-on-surface-variant)">Harga Promo Baru (Rp)</label>
                <input type="number" id="price-input" min="0" step="1" inputmode="numeric"
                       class="form-control" placeholder="0"
                       style="border-color:var(--md-outline);font-size:14px;background:var(--md-surface);color:var(--md-on-surface)">
                <p class="mb-0 mt-2" style="font-size:11.5px;color:var(--md-on-surface-variant)">
                    Nilai ini akan tertimpa lagi saat sync harga toko harian berjalan.
                </p>

                <div class="d-flex justify-content-end gap-2 mt-4">
                    <button type="button" class="btn js-price-cancel"
                            style="background:transparent;border:1px solid var(--md-outline);color:var(--md-on-surface);border-radius:var(--md-shape-xs);font-size:13.5px;padding:8px 16px">
                        Batal
                    </button>
                    <button type="submit" id="price-save"
                            style="background:var(--md-primary);color:var(--md-on-primary);border:none;border-radius:var(--md-shape-xs);font-size:13.5px;font-weight:500;padding:8px 20px">
                        Simpan
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Modal update harga promo massal: tempel (potongan) seller SKU → cari lintas
         toko → pilih baris (grup toko/produk atau individual) → pratinjau → terapkan. --}}
    <div id="bulk-price-modal"
         style="display:none;position:fixed;inset:0;z-index:1060;background:rgba(0,0,0,.45);
                align-items:center;justify-content:center;padding:16px">
        <div style="background:var(--md-surface);color:var(--md-on-surface);border-radius:var(--md-shape-md,12px);
                    width:100%;max-width:720px;max-height:88vh;display:flex;flex-direction:column;
                    box-shadow:var(--md-elevation-3,0 8px 24px rgba(0,0,0,.2))">
            <div class="d-flex align-items-center justify-content-between"
                 style="padding:16px 20px;border-bottom:1px solid var(--md-outline-variant,var(--md-outline))">
                <h2 style="font-size:15px;font-weight:600;margin:0">Update Harga Promo Massal</h2>
                <button type="button" class="btn btn-sm js-bulk-price-cancel" title="Tutup"
                        style="background:transparent;border:none;color:var(--md-on-surface-variant);padding:2px 6px">
                    <i class="bi bi-x-lg" style="font-size:15px"></i>
                </button>
            </div>

            {{-- Langkah 1: cari --}}
            <div id="bulk-price-step-search" style="padding:20px;overflow:auto">
                <label for="bulk-price-keyword" class="mb-1 d-block" style="font-size:12.5px;color:var(--md-on-surface-variant)">
                    Tempel / ketik seller SKU (boleh sebagian, mis. "T01-PTAA")
                </label>
                <div class="d-flex gap-2">
                    <input type="text" id="bulk-price-keyword" class="form-control" placeholder="cth: T01-PTAA"
                           style="border-color:var(--md-outline);font-size:14px;background:var(--md-surface);color:var(--md-on-surface)">
                    <button type="button" id="bulk-price-search-btn"
                            style="background:var(--md-primary);color:var(--md-on-primary);border:none;border-radius:var(--md-shape-xs);font-size:13.5px;font-weight:500;padding:8px 18px;white-space:nowrap">
                        Cari
                    </button>
                </div>
                <p id="bulk-price-search-msg" class="mb-0 mt-2" style="font-size:12px;color:var(--md-on-surface-variant)">
                    Hasil dicari lintas semua toko. Pilih baris yang ingin diupdate di langkah berikutnya.
                </p>
            </div>

            {{-- Langkah 2: hasil + pilih + isi harga --}}
            <div id="bulk-price-step-select" style="display:none;padding:16px 20px;overflow:auto;flex:1">
                <div class="d-flex align-items-center justify-content-between mb-2" style="flex-wrap:wrap;gap:8px">
                    <label class="d-flex align-items-center gap-2 m-0" style="font-size:13px">
                        <input type="checkbox" id="bulk-price-check-all"> Pilih semua
                    </label>
                    <span id="bulk-price-count" style="font-size:12.5px;color:var(--md-on-surface-variant)"></span>
                </div>
                <div id="bulk-price-results" style="border:1px solid var(--md-outline-variant,var(--md-outline));border-radius:var(--md-shape-xs);max-height:38vh;overflow:auto"></div>

                <div class="mt-3">
                    <label for="bulk-price-input" class="mb-1 d-block" style="font-size:12.5px;color:var(--md-on-surface-variant)">
                        Harga Promo Baru (Rp) — berlaku untuk semua baris terpilih
                    </label>
                    <input type="number" id="bulk-price-input" min="0" step="1" inputmode="numeric"
                           class="form-control" placeholder="0" style="max-width:220px;
                           border-color:var(--md-outline);font-size:14px;background:var(--md-surface);color:var(--md-on-surface)">
                </div>
            </div>

            {{-- Langkah 3: konfirmasi --}}
            <div id="bulk-price-step-confirm" style="display:none;padding:20px;overflow:auto">
                <p style="font-size:14px">
                    Terapkan harga promo <strong id="bulk-price-confirm-price"></strong>
                    ke <strong id="bulk-price-confirm-count"></strong> SKU terpilih?
                </p>
                <div id="bulk-price-confirm-list" style="max-height:34vh;overflow:auto;border:1px solid var(--md-outline-variant,var(--md-outline));border-radius:var(--md-shape-xs);padding:8px 12px;font-size:12.5px;color:var(--md-on-surface-variant)"></div>
            </div>

            <div class="d-flex justify-content-between align-items-center" style="padding:14px 20px;border-top:1px solid var(--md-outline-variant,var(--md-outline))">
                <button type="button" id="bulk-price-back" style="display:none;background:transparent;border:1px solid var(--md-outline);color:var(--md-on-surface);border-radius:var(--md-shape-xs);font-size:13.5px;padding:8px 16px">
                    Kembali
                </button>
                <div class="d-flex gap-2 ms-auto">
                    <button type="button" class="js-bulk-price-cancel" style="background:transparent;border:1px solid var(--md-outline);color:var(--md-on-surface);border-radius:var(--md-shape-xs);font-size:13.5px;padding:8px 16px">
                        Batal
                    </button>
                    <button type="button" id="bulk-price-next"
                            style="background:var(--md-primary);color:var(--md-on-primary);border:none;border-radius:var(--md-shape-xs);font-size:13.5px;font-weight:500;padding:8px 20px">
                        Lanjut
                    </button>
                </div>
            </div>
        </div>
    </div>

@endsection
