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
    const STORE_ID          = @json($storeId);

    const num    = (v) => Number(v || 0).toLocaleString('id-ID');
    const rupiah = (v) => (v === null || v === undefined)
        ? '<span style="color:var(--md-on-surface-variant)">-</span>'
        : 'Rp' + Number(v).toLocaleString('id-ID');
    const dash   = (v) => (v === null || v === undefined || v === '')
        ? '<span style="color:var(--md-on-surface-variant)">-</span>' : v;

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
                <td class="text-end" style="font-size:13px">${rupiah(v.hpp)}</td>
                <td class="text-end" style="font-size:13px">${rupiah(v.retail)}</td>
                <td class="text-end" style="font-size:13px">${rupiah(v.promo)}</td>
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

        const url = `${VARIANTS_URL}?product_id=${encodeURIComponent(row.dataset.productId)}&store_id=${STORE_ID ?? ''}`;
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
        <div style="position:relative;max-width:360px;flex:1 1 260px">
            <div class="input-group">
                <span class="input-group-text"
                      style="background:var(--md-surface);border-color:var(--md-outline);border-radius:var(--md-shape-xs) 0 0 var(--md-shape-xs)">
                    <i class="bi bi-search" style="color:var(--md-on-surface-variant);font-size:14px"></i>
                </span>
                <input type="text" name="search" value="{{ $search ?? '' }}"
                       class="form-control"
                       placeholder="Cari SKU induk…"
                       style="border-color:var(--md-outline);font-size:13.5px;background:var(--md-surface);color:var(--md-on-surface)">
                @if(!empty($search))
                    <a href="{{ route('products.index', ['store_id' => $storeId]) }}"
                       class="input-group-text"
                       style="background:var(--md-surface);border-color:var(--md-outline);border-radius:0 var(--md-shape-xs) var(--md-shape-xs) 0;color:var(--md-on-surface-variant);text-decoration:none"
                       title="Hapus pencarian">
                        <i class="bi bi-x-lg" style="font-size:12px"></i>
                    </a>
                @endif
            </div>
        </div>

        {{-- Pemilih toko: harga jual ditampilkan per toko --}}
        @if($stores->isNotEmpty())
            <div style="min-width:200px">
                <select name="store_id" class="form-select" onchange="this.form.submit()"
                        title="Pilih toko untuk melihat harga"
                        style="border-color:var(--md-outline);font-size:13.5px;background:var(--md-surface);color:var(--md-on-surface)">
                    @foreach($stores as $store)
                        <option value="{{ $store->id }}" @selected($storeId == $store->id)>
                            {{ ucwords($store->name) }}
                        </option>
                    @endforeach
                </select>
            </div>
        @endif

        {{-- Export seluruh listing+varian toko terpilih ke .xlsx --}}
        <a href="{{ route('products.export', ['store_id' => $storeId]) }}"
           class="btn d-inline-flex align-items-center gap-2"
           title="Export semua data toko ini ke Excel"
           style="background:var(--md-secondary-container);color:var(--md-on-secondary-container);border:none;border-radius:var(--md-shape-xs);font-size:13.5px;font-weight:500;padding:8px 16px">
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

@endsection
