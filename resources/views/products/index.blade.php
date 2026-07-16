@extends('components.layouts.app')

@section('title', 'Products')

@push('styles')
    <style>
        .pagination-wrapper nav > div:first-child { display: none !important; }
        .pagination-wrapper nav { margin-bottom: 0 !important; }
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

    function buildDetail(d) {
        const variants = d.variants || [];
        if (!variants.length) {
            return '<div style="padding:16px 44px;color:var(--md-on-surface-variant);font-size:13px">Tidak ada varian.</div>';
        }
        const rows = variants.map(v => `
            <tr>
                <td style="font-size:13px">${dash(v.product_id)}</td>
                <td style="font-size:13px">${dash(v.sku_id)}</td>
                <td style="font-size:13px">${v.sku}</td>
                <td style="font-size:13px;color:var(--md-on-surface-variant)">${v.label ?? '-'}</td>
                <td class="text-end" style="font-size:13px">${num(v.stok)}</td>
                <td class="text-end" style="font-size:13px">${num(v.po)}</td>
                <td class="text-end" style="font-size:13px;white-space:nowrap">
                    <span class="js-hpp-val">${hppDisplay(v.hpp)}</span>
                    <button type="button" class="js-hpp-edit"
                            data-sku="${encodeURIComponent(v.sku)}" data-hpp="${v.hpp}"
                            title="Isi / ubah HPP"
                            style="background:transparent;border:none;color:var(--md-on-surface-variant);padding:0 2px;margin-left:4px;cursor:pointer">
                        <i class="bi bi-pencil" style="font-size:12px"></i>
                    </button>
                </td>
                <td class="text-end" style="font-size:13px">${rupiah(v.retail)}</td>
                <td class="text-end" style="font-size:13px;white-space:nowrap">
                    <span class="js-promo-val">${rupiah(v.promo)}</span>
                    <button type="button" class="js-price-edit"
                            data-sku="${encodeURIComponent(v.sku)}" data-promo="${v.promo ?? 0}"
                            title="Ubah harga promo"
                            style="background:transparent;border:none;color:var(--md-on-surface-variant);padding:0 2px;margin-left:4px;cursor:pointer">
                        <i class="bi bi-pencil" style="font-size:12px"></i>
                    </button>
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
        return `<div style="padding:16px 8px 8px 44px;overflow-x:auto">
            <table class="table align-middle mb-0">
              <thead><tr style="color:var(--md-on-surface-variant);font-size:12px">
                <th>Product ID</th><th>SKU ID</th><th>Seller SKU</th><th>Variasi</th>
                <th class="text-end">Stok</th><th class="text-end">PO</th>
                <th class="text-end">HPP</th><th class="text-end">Harga Normal</th><th class="text-end">Harga Promo</th>
                <th class="text-center">Aksi</th>
              </tr></thead>
              <tbody>${rows}</tbody>
            </table></div>`;
    }

    document.addEventListener('click', function (e) {
        const btn = e.target.closest('.js-expand');
        if (!btn) return;
        const row  = btn.closest('.js-catalog-row');
        const next = row.nextElementSibling;

        // Toggle bila detail sudah ada
        if (next && next.classList.contains('js-detail-row')) {
            const open = next.style.display !== 'none';
            next.style.display = open ? 'none' : '';
            btn.setAttribute('aria-expanded', String(!open));
            btn.style.transform = open ? '' : 'rotate(90deg)';
            return;
        }

        // Buat baris detail + fetch lazy
        const tr = document.createElement('tr');
        tr.className = 'js-detail-row';
        const td = document.createElement('td');
        td.colSpan = 8;
        td.style.background = 'var(--md-surface-container-low)';
        // (kolom header: expand, SKU Induk, Product ID, Varian, Total Stok, Harga Normal, Harga Promo, Aksi)
        td.innerHTML = '<div style="padding:16px 44px;color:var(--md-on-surface-variant);font-size:13px">Memuat varian…</div>';
        tr.appendChild(td);
        row.after(tr);
        btn.setAttribute('aria-expanded', 'true');
        btn.style.transform = 'rotate(90deg)';

        const url = `${VARIANTS_URL}?product_id=${encodeURIComponent(row.dataset.productId)}&store_id=${STORE_ID ?? ''}${HPP_EMPTY ? '&hpp_empty=1' : ''}`;
        fetch(url)
            .then(r => r.json())
            .then(d => { td.innerHTML = buildDetail(d); })
            .catch(() => { td.innerHTML = '<div style="padding:16px 44px;color:var(--md-error);font-size:13px">Gagal memuat varian.</div>'; });
    });

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
                    const catalogRow = detailRow ? detailRow.previousElementSibling : null;
                    if (detailRow) detailRow.remove();
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
                    const detailRow = row.nextElementSibling;
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
})();
</script>
@endpush

@section('content')

    <div class="md-page-header">
        <div>
            <h1>Products</h1>
            <p class="subtitle">Katalog produk dari Jubelio — stok &amp; harga per toko</p>
        </div>
    </div>

    {{-- Kebaruan data per sumber (toko terpilih) — cek sebelum export.
         Hover tiap item untuk tanggal & jam pasti. --}}
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3"
         style="font-size:12.5px;color:var(--md-on-surface-variant)">
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

    @include('components.alert')

    <form method="GET" action="{{ route('products.index') }}" class="mb-3 d-flex flex-wrap align-items-center gap-2" autocomplete="off">
        {{-- Pencarian SKU induk --}}
        <div class="input-group" style="max-width:340px;flex:1 1 240px">
            <span class="input-group-text"
                  style="background:var(--md-surface);border-color:var(--md-outline);border-right:0;border-radius:var(--md-shape-xs) 0 0 var(--md-shape-xs)">
                <i class="bi bi-search" style="color:var(--md-on-surface-variant);font-size:14px"></i>
            </span>
            <input type="text" name="search" value="{{ $search ?? '' }}"
                   class="form-control"
                   placeholder="Cari SKU induk…"
                   style="border-color:var(--md-outline);border-left:0;border-right:{{ !empty($search) ? '0' : '' }};font-size:13.5px;background:var(--md-surface);color:var(--md-on-surface)">
            @if(!empty($search))
                <a href="{{ route('products.index', array_filter(['store_id' => $storeId, 'hpp_empty' => $hppEmpty ? 1 : null])) }}"
                   class="input-group-text"
                   style="background:var(--md-surface);border-color:var(--md-outline);border-left:0;border-radius:0 var(--md-shape-xs) var(--md-shape-xs) 0;color:var(--md-on-surface-variant);text-decoration:none"
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
                        style="border-color:var(--md-outline);border-left:0;border-radius:0 var(--md-shape-xs) var(--md-shape-xs) 0;font-size:13.5px;font-weight:500;min-width:180px;background:var(--md-surface);color:var(--md-on-surface)">
                    @foreach($stores as $store)
                        <option value="{{ $store->id }}" @selected($storeId == $store->id)>
                            {{ ucwords($store->name) }}
                        </option>
                    @endforeach
                </select>
            </div>
        @endif

        {{-- Filter chip: hanya listing yang punya varian ber-HPP belum terisi --}}
        <label class="btn d-inline-flex align-items-center gap-2 m-0"
               title="Tampilkan hanya produk yang masih ada HPP kosong (mis. bundling)"
               style="border:1px solid {{ $hppEmpty ? 'var(--md-primary)' : 'var(--md-outline)' }};
                      background:{{ $hppEmpty ? 'var(--md-secondary-container)' : 'var(--md-surface)' }};
                      color:{{ $hppEmpty ? 'var(--md-on-secondary-container)' : 'var(--md-on-surface)' }};
                      border-radius:var(--md-shape-xs);font-size:13.5px;font-weight:{{ $hppEmpty ? 600 : 400 }}">
            <input type="checkbox" name="hpp_empty" value="1" onchange="this.form.submit()" @checked($hppEmpty) class="d-none">
            <i class="bi {{ $hppEmpty ? 'bi-funnel-fill' : 'bi-funnel' }}" style="font-size:13px"></i>
            HPP belum terisi
            @if($hppEmpty)<i class="bi bi-check-lg" style="font-size:14px"></i>@endif
        </label>

        {{-- Export seluruh listing+varian toko terpilih ke .xlsx (aksi → dorong ke kanan) --}}
        <a href="{{ route('products.export', ['store_id' => $storeId]) }}"
           class="btn d-inline-flex align-items-center gap-2 ms-auto"
           title="Export semua data toko ini ke Excel"
           style="background:var(--md-secondary-container);color:var(--md-on-secondary-container);border:none;border-radius:var(--md-shape-xs);font-size:13.5px;font-weight:500">
            <i class="bi bi-file-earmark-excel" style="font-size:15px"></i>
            Export
        </a>
    </form>

    @include('products.partials.table', ['catalog' => $catalog, 'meta' => $meta])

    @if($catalog->isNotEmpty())
        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-center mt-4 gap-2">
            <p class="mb-0" style="font-size:13px;color:var(--md-on-surface-variant)">
                Menampilkan
                <strong style="color:var(--md-on-surface)">{{ $catalog->firstItem() ?? 0 }}</strong>
                –
                <strong style="color:var(--md-on-surface)">{{ $catalog->lastItem() ?? 0 }}</strong>
                dari
                <strong style="color:var(--md-on-surface)">{{ $catalog->total() }}</strong>
                produk (listing)
            </p>
            <div class="pagination-wrapper">
                {{ $catalog->withQueryString()->links() }}
            </div>
        </div>
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

@endsection
