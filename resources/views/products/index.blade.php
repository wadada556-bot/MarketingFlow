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
    const UPDATE_MODEL_URL  = @json(route('products.update-model-id'));
    const UPDATE_PRICE_URL  = @json(route('products.update-price'));
    const BULK_PRICE_URL    = @json(route('products.bulk-update-price'));
    const BULK_SEARCH_URL   = @json(route('products.bulk-price-search'));
    const BULK_APPLY_URL    = @json(route('products.bulk-price-apply'));
    const BULK_EXCEL_PARSE_URL    = @json(route('products.bulk-price-excel-parse'));
    const BULK_EXCEL_MATCH_URL    = @json(route('products.bulk-price-excel-match'));
    const BULK_EXCEL_TEMPLATE_URL = @json(route('products.bulk-price-excel-template'));
    const ALL_STORES = @json($stores->map(fn ($s) => ['id' => $s->id, 'name' => $s->name])->values());
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

    // Escape teks bebas (ID Model diketik user) sebelum masuk template HTML.
    const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));

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
        const w = cols || {varian:240, skuId:170, model:170, stok:110, po:100, hpp:170, harga:220, aksi:110};
        const total = w.varian + w.skuId + w.model + w.stok + w.po + w.hpp + w.harga + w.aksi;
        const rows = variants.map(v => `
            <tr>
                <td style="font-size:13px">
                    <p class="mb-0 fw-medium" style="color:var(--md-on-surface)">${v.label ?? v.sku}</p>
                    <p class="mb-0" style="font-size:11.5px;color:var(--md-on-surface-variant)">${v.sku}</p>
                </td>
                <td style="font-size:12.5px;color:var(--md-on-surface-variant)">${dash(v.sku_id)}</td>
                <td style="font-size:13px">
                    <span class="products-field js-model-edit" data-sku="${encodeURIComponent(v.sku)}" data-product-id="${v.product_id ?? ''}" data-model="${esc(v.model)}" data-default="${esc(v.model_default)}" title="Isi / ubah ID Model SKU ini (berlaku di semua toko; kosongkan untuk pakai bawaan)">
                        <span class="js-model-val">${esc(v.model ?? v.model_default ?? '-')}</span>
                        ${v.model ? '<i class="bi bi-pin-angle-fill js-model-pin" title="Diisi manual" style="font-size:11px;color:var(--md-primary)"></i>' : ''}
                        <i class="bi bi-pencil"></i>
                    </span>
                </td>
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
                    <div class="d-flex align-items-center justify-content-end flex-wrap gap-1" style="margin-top:2px">
                        <span class="products-field js-price-edit" data-sku="${encodeURIComponent(v.sku)}" data-product-id="${v.product_id ?? ''}" data-promo="${v.promo ?? 0}" title="Ubah harga promo (khusus listing ini)">
                            <span style="font-size:11px;color:var(--md-on-surface-variant)">Promo:</span>
                            <span class="js-promo-val">${rupiah(v.promo)}</span>
                            <i class="bi bi-pencil"></i>
                        </span>
                    </div>
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm js-price-history d-inline-flex align-items-center gap-1"
                            data-sku="${encodeURIComponent(v.sku)}" data-product-id="${v.product_id ?? ''}"
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
                <col style="width:${w.skuId}px"><col style="width:${w.model}px">
                <col style="width:${w.stok}px"><col style="width:${w.po}px">
                <col style="width:${w.hpp}px"><col style="width:${w.harga}px">
                <col style="width:${w.aksi}px">
              </colgroup>
              <thead><tr style="color:var(--md-on-surface-variant);font-size:12px">
                <th>Varian</th><th>SKU ID</th><th>ID Model</th>
                <th class="text-end">Stok</th><th class="text-end">PO</th>
                <th class="text-end">HPP</th><th class="text-end">Harga Jual</th>
                <th class="text-center">Aksi</th>
              </tr></thead>
              <tbody>${rows}</tbody>
            </table></div>`;
    }

    // Hitung lebar tiap kolom tabel detail dari geometri NYATA baris induk
    // (bukan CSS auto-layout, yg terbukti tak stabil lintas lebar layar).
    // ID Model/Stok/PO/HPP/Harga Jual = persis sama dgn kolom yg sama di baris induk.
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
        const rModel = rect(1), rStok = rect(2), rPo = rect(3), rHpp = rect(4), rHarga = rect(5);
        const model = rModel ? Math.round(rModel.width) : 170;
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
        const fixedSum = model + stok + po + hpp + harga + aksi;
        let leading = Math.round(targetRight - indentLeft - fixedSum);
        leading = Math.max(80, leading);

        let skuId = 170, varian = leading - skuId;
        if (varian < 40) {
            // Leading kepepet (kolom Produk baris induk sempit) — sisakan
            // Varian min 40px, sisanya ke SKU ID.
            varian = 40;
            skuId = Math.max(30, leading - varian);
        }
        return {varian, skuId, model, stok, po, hpp, harga, aksi};
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
        td.colSpan = 6;
        td.style.background = 'var(--md-surface-container-low)';
        // (kolom header: Produk, ID Model, Total Stok, Total PO, HPP, Harga Jual)
        td.innerHTML = '<div style="padding:16px 44px;color:var(--md-on-surface-variant);font-size:13px">Memuat varian…</div>';
        tr.appendChild(td);
        subrow.after(tr);
        setToggleState(true);

        loadVariantsDetail(subrow.dataset.productId, catalogRow, td)
            .catch(() => { td.innerHTML = '<div style="padding:16px 44px;color:var(--md-error);font-size:13px">Gagal memuat varian.</div>'; });
    });

    // Fetch varian 1 listing (product_id) & render ke td detail yang sudah ada
    // di DOM — dipakai saat expand baris PERTAMA KALI, dan juga untuk me-refresh
    // detail setelah edit harga tanpa reload halaman penuh.
    function loadVariantsDetail(productId, catalogRow, td) {
        // Ukur geometri SETELAH td disisipkan (colspan=6 langsung dpt lebar
        // baris penuh terlepas dari isinya), tapi SEBELUM konten varian
        // memenuhinya, supaya catalogRow jg belum sempat bergeser.
        const cols = computeDetailCols(catalogRow, td);
        const url = `${VARIANTS_URL}?product_id=${encodeURIComponent(productId)}&store_id=${STORE_ID ?? ''}${HPP_EMPTY ? '&hpp_empty=1' : ''}`;
        return fetch(url)
            .then(r => r.json())
            .then(d => {
                td.innerHTML = buildDetail(d, cols);
                alignDetailTableRight(catalogRow, td);
            });
    }

    // computeDetailCols() sudah menghitung lebar tabel supaya ujung kanannya
    // pas di ujung kolom Harga Jual baris induk — fungsi ini cuma koreksi
    // halus (beberapa px) utk selisih rendering riil (mis. padding internal
    // td) yg tak tertangkap oleh perhitungan geometri. Dibatasi kecil (≤40px)
    // supaya tak pernah menciptakan celah lebar seperti margin-left:auto
    // versi sebelumnya (lihat commit 85449e8, direvisi di sini).
    function alignDetailTableRight(catalogRow, tdEl) {
        const table = tdEl.querySelector('table');
        const targetRight = catalogRow.children[5].getBoundingClientRect().right; // kolom Harga Jual
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

        const url = `${PRICE_HISTORY_URL}?sku_code=${skuEnc}&store_id=${STORE_ID ?? ''}&product_id=${btn.dataset.productId ?? ''}`;
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

    // ── Isi / ubah ID Model (modal, per listing) ─────────────────────────────
    const modelOverlay = document.getElementById('model-modal');
    const modelForm    = document.getElementById('model-form');
    const modelInput   = document.getElementById('model-input');
    const modelLabel   = document.getElementById('model-context-label');
    const modelDefault = document.getElementById('model-default-hint');
    const modelSave    = document.getElementById('model-save');
    let   modelTarget  = null;

    function closeModel() { modelOverlay.style.display = 'none'; modelTarget = null; }
    modelOverlay.addEventListener('click', (e) => { if (e.target === modelOverlay || e.target.closest('.js-model-cancel')) closeModel(); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && modelOverlay.style.display === 'flex') closeModel(); });

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.js-model-edit');
        if (!btn) return;
        modelTarget = btn;
        const isBulk = !btn.dataset.sku;
        modelLabel.textContent = isBulk
            ? 'Semua SKU pada Product ID ' + btn.dataset.productId
            : decodeURIComponent(btn.dataset.sku);
        modelDefault.textContent = isBulk ? 'turunan SKU masing-masing variasi' : (btn.dataset.default || '-');
        modelInput.value = btn.dataset.model || '';
        modelOverlay.style.display = 'flex';
        setTimeout(() => modelInput.focus(), 40);
    });

    modelForm.addEventListener('submit', function (e) {
        e.preventDefault();
        if (!modelTarget) return;
        const btn = modelTarget;
        modelSave.disabled = true;
        fetch(UPDATE_MODEL_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            body: JSON.stringify({ store_id: STORE_ID, product_id: btn.dataset.productId, sku_code: btn.dataset.sku ? decodeURIComponent(btn.dataset.sku) : null, model_id: modelInput.value.trim() }),
        })
        .then(r => r.ok ? r.json() : Promise.reject(r))
        .then(d => {
            // Tambal semua listing terdampak di halaman (SKU yang sama bisa muncul
            // di >1 listing pada toko ini; toko lain terbarui saat dibuka).
            Object.entries(d.listings || {}).forEach(([pid, info]) => {
                const row = document.querySelector(`.js-catalog-row[data-product-id="${CSS.escape(pid)}"]`);
                if (!row) return;
                const val = row.querySelector('.js-model-val');
                val.textContent = info.label || '-';
                const pin = row.querySelector('.js-model-pin');
                if (info.manual && !pin) {
                    val.insertAdjacentHTML('afterend',
                        '<i class="bi bi-pin-angle-fill js-model-pin" title="Ada yang diisi manual" style="font-size:11px;color:var(--md-primary)"></i>');
                } else if (!info.manual && pin) {
                    pin.remove();
                }
                // Detail varian sudah ter-load -> reload agar nilai tiap variasi akurat.
                const subrow    = row.nextElementSibling;
                const detailRow = subrow ? subrow.nextElementSibling : null;
                if (detailRow && detailRow.classList.contains('js-detail-row')) {
                    const td = detailRow.querySelector('td');
                    if (td) loadVariantsDetail(pid, row, td).catch(() => {});
                }
            });
            closeModel();
        })
        .catch(() => { alert('Gagal menyimpan ID Model. Coba lagi.'); })
        .finally(() => { modelSave.disabled = false; });
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
            const productId = priceTarget.dataset.productId;
            fetch(UPDATE_PRICE_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                body: JSON.stringify({ sku_code: sku, store_id: STORE_ID, product_id: productId, promotion_price: val }),
            })
            .then(r => r.ok ? r.json() : Promise.reject(r))
            .then(() => {
                // Ambil referensi SEBELUM closePrice() — closePrice() menge-null-kan
                // priceTarget, jadi harus dibaca duluan.
                const detailRow = priceTarget.closest('.js-detail-row');
                const subrow    = detailRow ? detailRow.previousElementSibling : null;
                const catalogRow = subrow ? subrow.previousElementSibling : null;
                const td = detailRow ? detailRow.querySelector('td') : null;
                closePrice();
                // Reload detail listing ini supaya tampilan harga selalu dari server.
                if (td && catalogRow) loadVariantsDetail(productId, catalogRow, td).catch(() => {});
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

                    // Bila detail varian sudah ter-load, reload supaya harga
                    // tiap varian ikut akurat.
                    const subrow    = row.nextElementSibling;
                    const detailRow = subrow ? subrow.nextElementSibling : null;
                    if (detailRow && detailRow.classList.contains('js-detail-row')) {
                        const td = detailRow.querySelector('td');
                        if (td) loadVariantsDetail(productId, row, td).catch(() => {});
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
    const stepExcelStores  = document.getElementById('bulk-price-step-excel-stores');
    const stepExcelPreview = document.getElementById('bulk-price-step-excel-preview');

    const modeTabs        = document.querySelectorAll('.js-bulk-mode-tab');
    const pastePanel       = document.getElementById('bulk-price-paste-panel');
    const excelPanel        = document.getElementById('bulk-price-excel-panel');
    const excelFileIn        = document.getElementById('bulk-excel-file');
    const excelValidateBtn    = document.getElementById('bulk-excel-validate-btn');
    const excelMsg             = document.getElementById('bulk-excel-msg');
    const excelStoreList        = document.getElementById('bulk-excel-store-list');
    const excelCheckAllStores    = document.getElementById('bulk-excel-check-all-stores');
    const excelStoresMsg          = document.getElementById('bulk-excel-stores-msg');
    const excelPreviewSummary      = document.getElementById('bulk-excel-preview-summary');
    const excelPreviewList          = document.getElementById('bulk-excel-preview-list');

    let bulkResults = [];      // hasil pencarian mentah dari server (mode 'paste')
    let bulkChecked = new Set(); // index baris di bulkResults yang dicentang (mode 'paste') — per listing, bukan per (store,sku)
    let bulkStep = 'search';   // 'search' | 'select' | 'confirm' | 'excel-stores' | 'excel-preview'
    let bulkMode = 'paste';    // 'paste' | 'excel'
    // Kunci listing: (store_id, product_id, sku_code) — harga tersimpan per
    // listing (tiktok_listing_prices), jadi 2 Product ID yg kebetulan berbagi
    // sku_code sama punya kunci berbeda & benar-benar independen.
    const bulkPriceKey = (r) => `${r.store_id}|${r.product_id}|${r.sku_code}`;

    let excelItems = [];          // hasil parse: [{seller_sku, new_promotion_price}]
    let excelSelectedStores = new Set(); // store_id terpilih (number)
    let excelPreviewRows = [];    // hasil match: [{store_id, store_name, product_id, sku_code, old_price, new_price}]

    // Dedupe jaga-jaga bila hasil pencarian server kebetulan memuat baris
    // (store_id, product_id, sku_code) yg identik lebih dari sekali.
    function bulkSelectedItems() {
        const seen = new Map();
        bulkChecked.forEach(idx => {
            const r = bulkResults[idx];
            if (!r) return;
            const key = bulkPriceKey(r);
            if (!seen.has(key)) seen.set(key, r);
        });
        return [...seen.values()];
    }

    function bulkShowStep(step) {
        bulkStep = step;
        stepSearch.style.display       = step === 'search'        ? '' : 'none';
        stepSelect.style.display       = step === 'select'         ? '' : 'none';
        stepConfirm.style.display      = step === 'confirm'         ? '' : 'none';
        stepExcelStores.style.display  = step === 'excel-stores'     ? '' : 'none';
        stepExcelPreview.style.display = step === 'excel-preview'     ? '' : 'none';
        bulkBackBtn.style.display = step === 'search' ? 'none' : '';
        bulkNextBtn.textContent = (step === 'confirm' || step === 'excel-preview') ? 'Update Sekarang' : 'Lanjut';
        bulkNextBtn.style.display = step === 'search' ? 'none' : '';
    }

    function setBulkMode(mode) {
        bulkMode = mode;
        modeTabs.forEach(btn => {
            const active = btn.dataset.mode === mode;
            btn.style.borderBottomColor = active ? 'var(--md-primary)' : 'transparent';
            btn.style.color = active ? 'var(--md-primary)' : 'var(--md-on-surface-variant)';
        });
        pastePanel.style.display = mode === 'paste' ? '' : 'none';
        excelPanel.style.display = mode === 'excel' ? '' : 'none';
    }
    modeTabs.forEach(btn => btn.addEventListener('click', () => setBulkMode(btn.dataset.mode)));

    function bulkResetAll() {
        bulkResults = [];
        bulkChecked = new Set();
        bulkKeyword.value = '';
        bulkPriceIn.value = '';
        bulkSearchMsg.textContent = 'Hasil dicari lintas semua toko. Pilih baris yang ingin diupdate di langkah berikutnya.';
        bulkResultsEl.innerHTML = '';

        excelItems = [];
        excelSelectedStores = new Set();
        excelPreviewRows = [];
        excelFileIn.value = '';
        excelMsg.innerHTML = '';
        excelStoreList.innerHTML = '';
        excelCheckAllStores.checked = false;

        setBulkMode('paste');
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
        const total = bulkResults.length;
        bulkCountEl.textContent = `${bulkChecked.size} dari ${total} baris dipilih`;
        bulkCheckAll.checked = total > 0 && bulkChecked.size === total;
    }

    function renderBulkResults() {
        // Kelompokkan: toko → product_id → daftar sku, sambil menyimpan index asli
        // tiap baris (dipakai sebagai key checkbox level SKU) supaya listing yg
        // sku_code-nya sama tapi product_id beda tetap bisa dicentang independen.
        const byStore = new Map();
        bulkResults.forEach((r, idx) => {
            if (!byStore.has(r.store_id)) byStore.set(r.store_id, { name: r.store_name, byProduct: new Map() });
            const store = byStore.get(r.store_id);
            if (!store.byProduct.has(r.product_id)) store.byProduct.set(r.product_id, []);
            store.byProduct.get(r.product_id).push({ row: r, idx });
        });

        let html = '';
        byStore.forEach((store, storeId) => {
            const storeEntries = [...store.byProduct.values()].flat();
            html += `<div style="border-bottom:1px solid var(--md-outline-variant,var(--md-outline))">
                <div class="d-flex align-items-center gap-2 js-bulk-group" data-level="store" data-store="${storeId}"
                     style="padding:8px 12px;background:var(--md-surface-container-low);font-weight:600;font-size:13px;cursor:pointer">
                    <input type="checkbox" class="js-bulk-check" data-level="store" data-store="${storeId}">
                    <i class="bi bi-shop"></i> ${store.name}
                    <span style="font-weight:400;color:var(--md-on-surface-variant);font-size:12px">(${storeEntries.length} SKU)</span>
                </div>`;
            store.byProduct.forEach((entries, productId) => {
                html += `<div style="padding:6px 12px 6px 28px;display:flex;align-items:center;gap:8px;font-size:12.5px;color:var(--md-on-surface-variant)">
                    <input type="checkbox" class="js-bulk-check" data-level="product" data-store="${storeId}" data-product="${productId}">
                    Product ID: <span style="color:var(--md-on-surface)">${productId}</span>
                    <span>(${entries.length} SKU)</span>
                </div>`;
                entries.forEach(({ row: sku, idx }) => {
                    html += `<div style="padding:4px 12px 4px 48px;display:flex;align-items:center;gap:8px;font-size:12.5px">
                        <input type="checkbox" class="js-bulk-check" data-level="sku" data-key="${idx}">
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
            cb.checked = bulkChecked.has(Number(cb.dataset.key));
        });
        bulkResultsEl.querySelectorAll('.js-bulk-check[data-level="product"]').forEach(cb => {
            const storeId = cb.dataset.store, productId = cb.dataset.product;
            const indices = [];
            bulkResults.forEach((r, idx) => { if (String(r.store_id) === storeId && r.product_id === productId) indices.push(idx); });
            const allChecked = indices.length > 0 && indices.every(i => bulkChecked.has(i));
            cb.checked = allChecked;
            cb.indeterminate = !allChecked && indices.some(i => bulkChecked.has(i));
        });
        bulkResultsEl.querySelectorAll('.js-bulk-check[data-level="store"]').forEach(cb => {
            const storeId = cb.dataset.store;
            const indices = [];
            bulkResults.forEach((r, idx) => { if (String(r.store_id) === storeId) indices.push(idx); });
            const allChecked = indices.length > 0 && indices.every(i => bulkChecked.has(i));
            cb.checked = allChecked;
            cb.indeterminate = !allChecked && indices.some(i => bulkChecked.has(i));
        });
    }

    bulkResultsEl.addEventListener('change', function (e) {
        const cb = e.target.closest('.js-bulk-check');
        if (!cb) return;
        const level = cb.dataset.level;
        let affectedIdx = [];
        if (level === 'sku') {
            affectedIdx = [Number(cb.dataset.key)];
        } else if (level === 'product') {
            bulkResults.forEach((r, idx) => { if (String(r.store_id) === cb.dataset.store && r.product_id === cb.dataset.product) affectedIdx.push(idx); });
        } else if (level === 'store') {
            bulkResults.forEach((r, idx) => { if (String(r.store_id) === cb.dataset.store) affectedIdx.push(idx); });
        }
        affectedIdx.forEach(idx => { cb.checked ? bulkChecked.add(idx) : bulkChecked.delete(idx); });
        syncBulkCheckboxUi();
        updateBulkCount();
    });

    bulkCheckAll.addEventListener('change', function () {
        bulkChecked = bulkCheckAll.checked ? new Set(bulkResults.map((_, i) => i)) : new Set();
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
            bulkConfirmList.innerHTML = items.map(r => `<div>${r.store_name} — ${r.sku_code} <span style="color:var(--md-on-surface-variant);font-size:11px">(Product ID: ${r.product_id})</span></div>`).join('');
            bulkShowStep('confirm');
        } else if (bulkStep === 'confirm') {
            const val = Math.max(0, Math.floor(Number(bulkPriceIn.value) || 0));
            const items = bulkSelectedItems().map(r => ({ store_id: r.store_id, product_id: r.product_id, sku_code: r.sku_code }));
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
        } else if (bulkStep === 'excel-stores') {
            if (!excelSelectedStores.size) { alert('Pilih minimal satu toko.'); return; }
            runExcelMatch();
        } else if (bulkStep === 'excel-preview') {
            const items = excelPreviewRows.map(r => ({ store_id: r.store_id, product_id: r.product_id, sku_code: r.sku_code, promotion_price: r.new_price }));
            bulkNextBtn.disabled = true;
            fetch(BULK_APPLY_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
                body: JSON.stringify({ items }),
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
        else if (bulkStep === 'excel-preview') bulkShowStep('excel-stores');
        else if (bulkStep === 'excel-stores') bulkShowStep('search');
    });

    // ── Mode "Upload Excel" ──────────────────────────────────────────────────
    function renderExcelErrors(errors) {
        excelMsg.innerHTML = `<div style="color:var(--md-error,#b3261e)">
            <div class="mb-1">${errors.length > 1 ? 'File ditolak — perbaiki lalu upload ulang:' : 'File ditolak:'}</div>
            <ul style="margin:0;padding-left:18px">${errors.map(e => `<li>${e}</li>`).join('')}</ul>
        </div>`;
    }

    function runExcelValidate() {
        if (!excelFileIn.files.length) { excelMsg.innerHTML = '<span style="color:var(--md-error,#b3261e)">Pilih file terlebih dahulu.</span>'; return; }
        const fd = new FormData();
        fd.append('file', excelFileIn.files[0]);
        excelValidateBtn.disabled = true;
        excelMsg.innerHTML = '<span style="color:var(--md-on-surface-variant)">Memvalidasi…</span>';
        fetch(BULK_EXCEL_PARSE_URL, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            body: fd,
        })
        .then(r => r.json().then(d => ({ ok: r.ok, d })))
        .then(({ ok, d }) => {
            if (!ok) { renderExcelErrors(d.errors || ['Gagal memvalidasi file.']); return; }
            excelItems = d.items;
            excelMsg.innerHTML = '';
            renderExcelStoreList();
            excelStoresMsg.textContent = `${excelItems.length} baris seller_sku terbaca dari file. Toko yang tidak menjual SKU tsb otomatis dilewati.`;
            bulkShowStep('excel-stores');
        })
        .catch(() => { excelMsg.innerHTML = '<span style="color:var(--md-error,#b3261e)">Gagal memvalidasi file. Coba lagi.</span>'; })
        .finally(() => { excelValidateBtn.disabled = false; });
    }
    excelValidateBtn.addEventListener('click', runExcelValidate);

    function renderExcelStoreList() {
        excelSelectedStores = new Set();
        excelCheckAllStores.checked = false;
        excelStoreList.innerHTML = ALL_STORES.map(s => `
            <label class="d-flex align-items-center gap-2" style="padding:6px 12px;font-size:13px;cursor:pointer">
                <input type="checkbox" class="js-excel-store-check" value="${s.id}"> ${s.name}
            </label>
        `).join('');
    }

    excelStoreList.addEventListener('change', function (e) {
        const cb = e.target.closest('.js-excel-store-check');
        if (!cb) return;
        const id = Number(cb.value);
        cb.checked ? excelSelectedStores.add(id) : excelSelectedStores.delete(id);
        excelCheckAllStores.checked = excelSelectedStores.size === ALL_STORES.length;
    });

    excelCheckAllStores.addEventListener('change', function () {
        excelSelectedStores = excelCheckAllStores.checked ? new Set(ALL_STORES.map(s => s.id)) : new Set();
        excelStoreList.querySelectorAll('.js-excel-store-check').forEach(cb => { cb.checked = excelCheckAllStores.checked; });
    });

    function runExcelMatch() {
        bulkNextBtn.disabled = true;
        fetch(BULK_EXCEL_MATCH_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'Accept': 'application/json' },
            body: JSON.stringify({ items: excelItems, store_ids: [...excelSelectedStores] }),
        })
        .then(r => r.json().then(d => ({ ok: r.ok, d })))
        .then(({ ok, d }) => {
            if (!ok) {
                const list = (d.not_found || []).map(s => `<li>${s}</li>`).join('');
                excelStoresMsg.innerHTML = `<span style="color:var(--md-error,#b3261e)">
                    SKU berikut tidak ditemukan di toko manapun yang dipilih — perbaiki file atau pilihan toko lalu ulangi:
                    <ul style="margin:4px 0 0;padding-left:18px">${list}</ul>
                </span>`;
                return;
            }
            excelPreviewRows = d.preview;
            renderExcelPreview();
            bulkShowStep('excel-preview');
        })
        .catch(() => { excelStoresMsg.innerHTML = '<span style="color:var(--md-error,#b3261e)">Gagal mencocokkan SKU. Coba lagi.</span>'; })
        .finally(() => { bulkNextBtn.disabled = false; });
    }

    function renderExcelPreview() {
        const byStore = new Map();
        excelPreviewRows.forEach(r => {
            if (!byStore.has(r.store_id)) byStore.set(r.store_id, { name: r.store_name, rows: [] });
            byStore.get(r.store_id).rows.push(r);
        });
        const uniqueSkus = new Set(excelPreviewRows.map(r => r.sku_code)).size;
        excelPreviewSummary.innerHTML = `Menerapkan harga baru ke <strong>${excelPreviewRows.length}</strong> baris
            (${uniqueSkus} SKU unik di ${byStore.size} toko). Toko lain yang tidak menjual SKU terkait dilewati otomatis.`;

        let html = '';
        byStore.forEach((store, storeId) => {
            html += `<div style="border-bottom:1px solid var(--md-outline-variant,var(--md-outline))">
                <div style="padding:8px 12px;background:var(--md-surface-container-low);font-weight:600;font-size:13px">
                    <i class="bi bi-shop"></i> ${store.name} <span style="font-weight:400;color:var(--md-on-surface-variant);font-size:12px">(${store.rows.length} SKU)</span>
                </div>`;
            store.rows.forEach(r => {
                html += `<div style="padding:5px 12px 5px 28px;display:flex;align-items:center;gap:8px;font-size:12.5px">
                    <span style="word-break:break-all">${r.sku_code} <span style="color:var(--md-on-surface-variant);font-size:11px">(Product ID: ${r.product_id})</span></span>
                    <span style="margin-left:auto;white-space:nowrap;color:var(--md-on-surface-variant)">
                        ${rupiah(r.old_price)} → <strong style="color:var(--md-on-surface)">${rupiah(r.new_price)}</strong>
                    </span>
                </div>`;
            });
            html += `</div>`;
        });
        excelPreviewList.innerHTML = html;
    }
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

                {{-- Arsip snapshot Excel harian otomatis (7 hari terakhir) --}}
                <a href="{{ route('products.snapshots') }}"
                   class="products-export-btn"
                   title="Arsip snapshot Excel harian (7 hari terakhir)">
                    <i class="bi bi-archive"></i>
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
                   placeholder="Cari SKU induk / ID Model…"
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

    {{-- Modal isi/ubah ID Model (dibuka dari ikon pensil pada kolom ID Model) --}}
    <div id="model-modal"
         style="display:none;position:fixed;inset:0;z-index:1060;background:rgba(0,0,0,.45);
                align-items:center;justify-content:center;padding:16px">
        <div style="background:var(--md-surface);color:var(--md-on-surface);border-radius:var(--md-shape-md,12px);
                    width:100%;max-width:420px;box-shadow:var(--md-elevation-3,0 8px 24px rgba(0,0,0,.2))">
            <div class="d-flex align-items-center justify-content-between"
                 style="padding:16px 20px;border-bottom:1px solid var(--md-outline-variant,var(--md-outline))">
                <h2 style="font-size:15px;font-weight:600;margin:0">Isi / Ubah ID Model</h2>
                <button type="button" class="btn btn-sm js-model-cancel" title="Tutup"
                        style="background:transparent;border:none;color:var(--md-on-surface-variant);padding:2px 6px">
                    <i class="bi bi-x-lg" style="font-size:15px"></i>
                </button>
            </div>
            <form id="model-form" style="padding:20px">
                <p class="mb-1" style="font-size:12.5px;color:var(--md-on-surface-variant)">Target</p>
                <p id="model-context-label" class="mb-3 fw-medium" style="font-size:14px;color:var(--md-on-surface);word-break:break-all"></p>

                <label for="model-input" class="mb-1 d-block" style="font-size:12.5px;color:var(--md-on-surface-variant)">ID Model</label>
                <input type="text" id="model-input" maxlength="100" autocomplete="off"
                       class="form-control" placeholder="Kosongkan untuk pakai bawaan"
                       style="border-color:var(--md-outline);font-size:14px;background:var(--md-surface);color:var(--md-on-surface)">
                <p class="mb-0 mt-2" style="font-size:11.5px;color:var(--md-on-surface-variant)">
                    Bawaan: <strong id="model-default-hint"></strong>. Kosongkan lalu simpan untuk kembali ke bawaan.
                    <br><strong>Berlaku di semua toko</strong> yang menjual SKU ini.
                </p>

                <div class="d-flex justify-content-end gap-2 mt-4">
                    <button type="button" class="btn js-model-cancel"
                            style="background:transparent;border:1px solid var(--md-outline);color:var(--md-on-surface);border-radius:var(--md-shape-xs);font-size:13.5px;padding:8px 16px">
                        Batal
                    </button>
                    <button type="submit" id="model-save"
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

            {{-- Langkah 1: pilih mode + cari / upload --}}
            <div id="bulk-price-step-search" style="padding:20px;overflow:auto">
                {{-- Tab toggle mode --}}
                <div class="d-flex gap-2 mb-3" style="border-bottom:1px solid var(--md-outline-variant,var(--md-outline))">
                    <button type="button" id="bulk-mode-paste" class="js-bulk-mode-tab"
                            data-mode="paste"
                            style="background:transparent;border:none;border-bottom:2px solid var(--md-primary);color:var(--md-primary);font-size:13.5px;font-weight:600;padding:8px 4px;margin-bottom:-1px">
                        Tempel SKU
                    </button>
                    <button type="button" id="bulk-mode-excel" class="js-bulk-mode-tab"
                            data-mode="excel"
                            style="background:transparent;border:none;border-bottom:2px solid transparent;color:var(--md-on-surface-variant);font-size:13.5px;font-weight:600;padding:8px 4px;margin-bottom:-1px">
                        Upload Excel
                    </button>
                </div>

                {{-- Panel: tempel SKU (existing) --}}
                <div id="bulk-price-paste-panel">
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

                {{-- Panel: upload Excel (baru) --}}
                <div id="bulk-price-excel-panel" style="display:none">
                    <p class="mb-2" style="font-size:12.5px;color:var(--md-on-surface-variant)">
                        File .xlsx dgn kolom <strong>seller_sku</strong> (A) &amp; <strong>new_promotion_price</strong> (B) mulai baris 2.
                        <a href="{{ route('products.bulk-price-excel-template') }}" style="color:var(--md-primary)">Download template</a>.
                    </p>
                    <div class="d-flex gap-2">
                        <input type="file" id="bulk-excel-file" accept=".xlsx,.xls" class="form-control"
                               style="border-color:var(--md-outline);font-size:13.5px;background:var(--md-surface);color:var(--md-on-surface)">
                        <button type="button" id="bulk-excel-validate-btn"
                                style="background:var(--md-primary);color:var(--md-on-primary);border:none;border-radius:var(--md-shape-xs);font-size:13.5px;font-weight:500;padding:8px 18px;white-space:nowrap">
                            Validasi File
                        </button>
                    </div>
                    <div id="bulk-excel-msg" class="mt-2" style="font-size:12.5px"></div>
                </div>
            </div>

            {{-- Langkah Excel-A: pilih toko target --}}
            <div id="bulk-price-step-excel-stores" style="display:none;padding:16px 20px;overflow:auto;flex:1">
                <label class="d-flex align-items-center gap-2 mb-2" style="font-size:13px;font-weight:600">
                    <input type="checkbox" id="bulk-excel-check-all-stores"> Pilih Semua Toko
                </label>
                <div id="bulk-excel-store-list" style="border:1px solid var(--md-outline-variant,var(--md-outline));border-radius:var(--md-shape-xs);max-height:38vh;overflow:auto;padding:4px 0"></div>
                <p id="bulk-excel-stores-msg" class="mb-0 mt-2" style="font-size:12px;color:var(--md-on-surface-variant)"></p>
            </div>

            {{-- Langkah Excel-B: pratinjau hasil pencocokan --}}
            <div id="bulk-price-step-excel-preview" style="display:none;padding:16px 20px;overflow:auto;flex:1">
                <p id="bulk-excel-preview-summary" class="mb-2" style="font-size:13.5px"></p>
                <div id="bulk-excel-preview-list" style="max-height:44vh;overflow:auto;border:1px solid var(--md-outline-variant,var(--md-outline));border-radius:var(--md-shape-xs)"></div>
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
