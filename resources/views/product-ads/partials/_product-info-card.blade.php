<div class="md-card mb-4" style="overflow:hidden">

    {{-- Card header --}}
    <div style="background:var(--md-surface-container-low);padding:18px 24px;border-bottom:1px solid var(--md-outline-variant)">
        <div class="d-flex align-items-center gap-2">
            <span style="width:32px;height:32px;border-radius:50%;background:var(--md-primary-container);display:inline-flex;align-items:center;justify-content:center;color:var(--md-on-primary-container);font-size:15px">
                <i class="bi bi-box-seam"></i>
            </span>
            <p class="mb-0" style="font-size:14px;font-weight:500;color:var(--md-on-surface)">Informasi Produk</p>
        </div>
    </div>

    <div style="padding:24px">

        {{-- ── Info grid row 1 ─────────────────────────────── --}}
        <div class="row g-4 mb-4">
            <div class="col-6">
                <p class="mb-1" style="font-size:11px;font-weight:500;letter-spacing:.8px;text-transform:uppercase;color:var(--md-on-surface-variant)">SKU</p>
                <p class="mb-0" style="font-size:15px;font-weight:500;color:var(--md-on-surface)">{{ $product->parent_sku ?? '–' }}</p>
            </div>
            <div class="col-6">
                <p class="mb-1" style="font-size:11px;font-weight:500;letter-spacing:.8px;text-transform:uppercase;color:var(--md-on-surface-variant)">Kategori</p>
                <p class="mb-0" style="font-size:15px;color:var(--md-on-surface)">{{ $product->category->name ?? '–' }}</p>
            </div>
            <div class="col-6">
                <p class="mb-1" style="font-size:11px;font-weight:500;letter-spacing:.8px;text-transform:uppercase;color:var(--md-on-surface-variant)">Status Iklan</p>
                <div class="mt-1">{!! str_replace(['Active', 'Completed', 'Stopped'], ['Aktif', 'Selesai', 'Dihentikan'], $status_badge) !!}</div>
            </div>
            <div class="col-6">
                <p class="mb-1" style="font-size:11px;font-weight:500;letter-spacing:.8px;text-transform:uppercase;color:var(--md-on-surface-variant)">Ditambahkan</p>
                <p class="mb-0" style="font-size:13px;color:var(--md-on-surface)">{{ $productAd->created_at->format('d M Y, H:i') }}</p>
            </div>
        </div>

        <hr class="md-divider">

        {{-- ── Info grid row 2 ─────────────────────────────── --}}
        <div class="row g-4 mt-0 mb-4">
            <div class="col-4">
                <p class="mb-1" style="font-size:11px;font-weight:500;letter-spacing:.8px;text-transform:uppercase;color:var(--md-on-surface-variant)">Tahap Testing</p>
                <div class="mt-1">{!! str_replace(['Testing', 'Success', 'Fail'], ['Pengujian', 'Berhasil', 'Gagal'], $status_testing) !!}</div>
            </div>
            <div class="col-4">
                <p class="mb-1" style="font-size:11px;font-weight:500;letter-spacing:.8px;text-transform:uppercase;color:var(--md-on-surface-variant)">Mulai</p>
                <p class="mb-0" style="font-size:13px;color:var(--md-on-surface)">
                    {{ $testing_started_at ? \Carbon\Carbon::parse($testing_started_at)->format('d M Y, H:i') : '–' }}
                </p>
            </div>
            <div class="col-4">
                <p class="mb-1" style="font-size:11px;font-weight:500;letter-spacing:.8px;text-transform:uppercase;color:var(--md-on-surface-variant)">Selesai</p>
                <p class="mb-0" style="font-size:13px;color:var(--md-on-surface)">
                    {{ $testing_completed_at ? \Carbon\Carbon::parse($testing_completed_at)->format('d M Y, H:i') : '–' }}
                </p>
            </div>
        </div>

        <hr class="md-divider">

        {{-- ── Stok & Penjualan Varian (di-load lazy via AJAX) ── --}}
        <div class="mt-4" id="variant-stock-lazy"
             data-url="{{ route('product-ads.stock-detail', $productAd->id) }}">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <p class="mb-0" style="font-size:11px;font-weight:500;letter-spacing:.8px;text-transform:uppercase;color:var(--md-on-surface-variant)">
                    <i class="bi bi-layers me-1"></i> Stok & Penjualan Varian
                </p>
                <span class="erp-spinner"></span>
            </div>
            <div style="text-align:center;padding:28px 16px;background:var(--md-surface-container-low);border-radius:var(--md-shape-md)">
                <span class="erp-spinner" style="width:20px;height:20px;border-width:3px"></span>
                <p class="mb-0" style="font-size:13px;color:var(--md-on-surface-variant);margin-top:10px">Memuat stok &amp; penjualan…</p>
            </div>
        </div>

    </div>
</div>
