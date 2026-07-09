@extends('components.layouts.app')

@section('title', 'Product Ads New')

@push('styles')
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
    <link href="{{ asset('css/tomselect-md3.css') }}" rel="stylesheet">
@endpush

@section('content')
    @php
        $sortLink = function (string $key, string $label) use ($sortKey, $sortDir) {
            $nextDir = ($sortKey === $key && $sortDir === 'desc') ? 'asc' : 'desc';
            $icon = $sortKey !== $key ? 'bi-arrow-down-up opacity-25'
                  : ($sortDir === 'desc' ? 'bi-arrow-down' : 'bi-arrow-up');
            $url = request()->fullUrlWithQuery(['sort' => $key, 'dir' => $nextDir, 'page' => null]);
            return '<a href="' . $url . '" class="text-decoration-none d-inline-flex align-items-center gap-1"
                       style="color:inherit">' . e($label) . ' <i class="bi ' . $icon . '" style="font-size:11px"></i></a>';
        };
    @endphp

    {{-- ── Header ──────────────────────────────────────────────── --}}
    <div class="mb-4">
        <h4 class="mb-0" style="font-size:22px;font-weight:400;color:var(--md-on-surface)">Product Ads New</h4>
        <p class="mb-0" style="font-size:13px;color:var(--md-on-surface-variant)">
            Performa iklan aktual dari TikTok Ads, per produk per minggu. Tandai testing &amp; catat evaluasinya.
        </p>
    </div>

    @include('components.alert')

    {{-- ── Tab ─────────────────────────────────────────────────── --}}
    <nav class="md-tabs mb-4 overflow-auto" style="padding-bottom:2px">
        @foreach(['semua' => 'Semua', 'perlu_dicek' => 'Perlu Dicek', 'berhasil' => 'Berhasil', 'gagal' => 'Gagal'] as $key => $label)
            <a href="{{ request()->fullUrlWithQuery(['testing_status' => $key === 'semua' ? null : $key, 'page' => null]) }}"
               class="md-tab {{ $currentTab === $key ? 'active' : '' }}">
                {{ $label }}
                @if($tabCounts[$key] > 0)
                    <span class="tab-count">{{ $tabCounts[$key] }}</span>
                    @if($key === 'perlu_dicek')<span class="tab-dot"></span>@endif
                @endif
            </a>
        @endforeach
    </nav>

    @include('product-ads-new.partials._filter-bar')

    {{-- ── Tabel (gaya mengikuti menu Product Ads lama) ─────────── --}}
    @php
        // Warna pil stok: sama seperti menu lama (≤100 merah, ≤300 kuning, sisanya hijau).
        $stockChip = fn (int $q) => $q <= 100 ? 'error' : ($q <= 300 ? 'warning' : 'primary');
    @endphp
    <div class="md-table-wrap">
        <table class="md-table table-hover align-middle">
            <thead>
                <tr>
                    <th style="width:280px">{!! $sortLink('produk', 'Produk') !!}</th>
                    <th style="width:150px">Toko</th>
                    <th class="text-center" style="width:110px">Testing</th>
                    <th class="text-center" style="width:100px">Status</th>
                    <th class="text-center" style="width:90px">Stok</th>
                    <th class="text-end" style="width:80px">{!! $sortLink('roi', 'ROI') !!}</th>
                    <th class="text-center" style="width:130px">Tindakan</th>
                </tr>
            </thead>
            <tbody>
            @forelse($ads as $ad)
                @php
                    $tb     = $ad->testing_badge;
                    $sb     = $ad->status_badge;
                    $roi    = $ad->roi;
                    $lowRoi = $roi !== null && (float) $roi < $threshold;
                    $stok   = (int) ($ad->agg_stock ?? 0);
                @endphp
                <tr class="js-ad-row" data-ad-id="{{ $ad->id }}">
                    <td>
                        {{-- SKU induk: aturan sama dengan menu Products (ProductController::indukLabel).
                             Sengaja TIDAK text-truncate — kalau tak muat, wrap ke bawah (bukan "..."). --}}
                        <p class="mb-0 fw-bold" style="max-width:260px;font-size:15px;line-height:1.3;
                           color:var(--md-on-surface);white-space:normal;word-break:break-word">
                            {{ $ad->induk_label ?: '—' }}
                        </p>
                        {{-- product_id 19 digit: cetak apa adanya. Nama produk & campaign di tooltip. --}}
                        <p class="mb-1 font-monospace" style="font-size:11px;color:var(--md-on-surface-variant)"
                           title="{{ $ad->product_name }}@if($ad->campaign_name) &#10;Campaign: {{ $ad->campaign_name }}@endif">
                            {{ $ad->product_id }}
                        </p>
                        <button type="button" class="btn-md-text js-expand p-0" aria-expanded="false"
                                style="font-size:12px;color:var(--md-on-surface-variant);gap:4px"
                                title="Lihat performa &amp; stok/penjualan varian">
                            <i class="bi bi-chevron-right" style="transition:transform .15s ease;font-size:11px"></i>
                            {{ $ad->sku_count ?? 0 }} SKU
                        </button>
                    </td>
                    <td>
                        <span class="md-chip info" title="{{ $ad->store->name ?? '' }}">{{ $ad->store->name ?? '—' }}</span>
                    </td>
                    <td class="text-center">
                        <span class="badge rounded-pill bg-{{ $tb['color'] }} bg-opacity-10 text-{{ $tb['color'] }} border border-{{ $tb['color'] }}">
                            <i class="bi bi-circle-fill me-1" style="font-size:.5rem"></i>{{ $tb['label'] }}
                        </span>
                    </td>
                    <td class="text-center">
                        <div class="form-check form-switch d-flex justify-content-center align-items-center gap-2 mb-0"
                             title="Klik untuk {{ $ad->status === 'active' ? 'nonaktifkan' : 'aktifkan' }}">
                            <input type="checkbox" role="switch" class="form-check-input js-toggle-status"
                                   data-id="{{ $ad->id }}" style="cursor:pointer"
                                   {{ $ad->status === 'active' ? 'checked' : '' }}>
                            <span style="font-size:11px;color:var(--md-on-surface-variant);min-width:52px;text-align:left">
                                {{ $sb['label'] }}
                            </span>
                        </div>
                    </td>
                    <td class="text-center">
                        <span class="md-chip {{ $stockChip($stok) }}">{{ number_format($stok, 0, ',', '.') }}</span>
                    </td>
                    <td class="text-end">
                        @if($roi === null)
                            <span style="color:var(--md-on-surface-variant)">—</span>
                        @else
                            <span class="fw-medium" @if($lowRoi) style="color:var(--md-error)" title="ROI di bawah ambang {{ $threshold }}" @endif>
                                {{ number_format((float) $roi, 2, ',', '.') }}
                                @if($lowRoi)<i class="bi bi-exclamation-triangle-fill ms-1" style="font-size:11px"></i>@endif
                            </span>
                        @endif
                    </td>
                    <td>
                        <div class="d-flex justify-content-center align-items-center gap-0">
                            <button type="button" class="btn-md-icon primary js-mark" data-action="mark-success" data-id="{{ $ad->id }}"
                                    title="Tandai Berhasil"><i class="bi bi-check2-circle"></i></button>
                            <button type="button" class="btn-md-icon error js-mark" data-action="mark-fail" data-id="{{ $ad->id }}"
                                    title="Tandai Gagal"><i class="bi bi-x-circle"></i></button>
                            <button type="button" class="btn-md-icon tertiary js-extend" data-id="{{ $ad->id }}"
                                    title="Perpanjang Testing"><i class="bi bi-arrow-repeat"></i></button>
                            <button type="button" class="btn-md-icon warning js-logs" data-id="{{ $ad->id }}"
                                    data-name="{{ $ad->induk_label ?: $ad->product_id }}" title="Catatan"><i class="bi bi-journal-text"></i></button>
                        </div>
                    </td>
                </tr>
                <tr class="js-expand-row d-none">
                    <td colspan="7" style="background:var(--md-surface-container-low);padding:0">
                        <div class="js-expand-body" style="padding:16px">
                            <div class="text-center py-3" style="color:var(--md-on-surface-variant);font-size:13px">
                                <span class="spinner-border spinner-border-sm me-2"></span>Memuat performa &amp; varian…
                            </div>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="text-center py-5" style="color:var(--md-on-surface-variant)">
                        <i class="bi bi-megaphone d-block mb-2" style="font-size:28px;opacity:.4"></i>
                        Tidak ada iklan berjalan di periode ini.
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="d-flex justify-content-between align-items-center mt-3" style="flex-wrap:wrap;gap:8px">
        <span style="font-size:13px;color:var(--md-on-surface-variant)">
            Menampilkan {{ $ads->firstItem() ?? 0 }}–{{ $ads->lastItem() ?? 0 }} dari {{ $ads->total() }} iklan
        </span>
        <div>{{ $ads->links() }}</div>
    </div>

    @include('product-ads-new.partials._modals')
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
<script>
(function () {
    const period = @json($period);
    const csrf   = document.querySelector('meta[name="csrf-token"]')?.content;
    const urlTpl = {
        variants: @json(route('product-ads-new.variants', ['ad' => '__ID__'])),
        logs:     @json(route('product-ads-new.logs', ['ad' => '__ID__'])),
        mark:     @json(url('product-ads-new/__ID__/__ACTION__')),
    };
    const fill = (tpl, map) => Object.entries(map).reduce((s, [k, v]) => s.replaceAll(k, v), tpl);

    /* ── Filter bar ────────────────────────────────────────────── */
    const storeFilter = document.getElementById('filter_store');
    if (storeFilter && window.TomSelect) {
        new TomSelect(storeFilter, {
            plugins: ['remove_button'], maxItems: null, hideSelected: true, placeholder: 'Semua toko',
        });
    }
    document.getElementById('filter_period')?.addEventListener('change', e => e.target.form.submit());

    /* ── Expand: performa + stok/penjualan varian (lazy, sekali muat) ── */
    document.querySelectorAll('.js-expand').forEach(btn => {
        btn.addEventListener('click', async () => {
            const row  = btn.closest('tr');
            const next = row.nextElementSibling;
            const open = btn.getAttribute('aria-expanded') === 'true';

            btn.setAttribute('aria-expanded', String(!open));
            const chev = btn.querySelector('.bi-chevron-right');
            if (chev) chev.style.transform = open ? '' : 'rotate(90deg)';
            next.classList.toggle('d-none', open);
            if (open || next.dataset.loaded) return;

            try {
                const url  = fill(urlTpl.variants, {'__ID__': row.dataset.adId});
                const res  = await fetch(url + '?period=' + encodeURIComponent(period ?? ''));
                const data = await res.json();
                next.querySelector('.js-expand-body').innerHTML = data.html;
                next.dataset.loaded = '1';
            } catch (e) {
                next.querySelector('.js-expand-body').innerHTML =
                    '<p class="text-danger mb-0" style="font-size:13px">Gagal memuat data.</p>';
            }
        });
    });

    /* ── Aksi baris: submit form tersembunyi (agar redirect + flash jalan) ── */
    const submitAction = (url, method, fields = {}) => {
        const f = document.createElement('form');
        f.method = 'POST'; f.action = url;
        f.innerHTML = `<input type="hidden" name="_token" value="${csrf}">
                       <input type="hidden" name="_method" value="${method}">`;
        Object.entries(fields).forEach(([k, v]) => {
            const i = document.createElement('input');
            i.type = 'hidden'; i.name = k; i.value = v;
            f.appendChild(i);
        });
        document.body.appendChild(f);
        f.submit();
    };

    document.querySelectorAll('.js-mark').forEach(b => b.addEventListener('click', () => {
        submitAction(fill(urlTpl.mark, {'__ID__': b.dataset.id, '__ACTION__': b.dataset.action}), 'PATCH');
    }));

    /* ── Toggle Status (active/stopped) ───────────────────────── */
    document.querySelectorAll('.js-toggle-status').forEach(el => el.addEventListener('change', () => {
        submitAction(fill(urlTpl.mark, {'__ID__': el.dataset.id, '__ACTION__': 'toggle-status'}), 'PATCH');
    }));

    /* ── Modal: perpanjang testing ─────────────────────────────── */
    const extModal = new bootstrap.Modal('#extendModal');
    document.querySelectorAll('.js-extend').forEach(b => b.addEventListener('click', () => {
        document.getElementById('extendForm').action =
            fill(urlTpl.mark, {'__ID__': b.dataset.id, '__ACTION__': 'extend'});
        extModal.show();
    }));
    document.getElementById('extend_days')?.addEventListener('change', e => {
        document.getElementById('extend_custom_wrap').classList.toggle('d-none', e.target.value !== 'custom');
    });

    /* ── Modal: catatan ────────────────────────────────────────── */
    const logModal = new bootstrap.Modal('#logsModal');
    document.querySelectorAll('.js-logs').forEach(b => b.addEventListener('click', async () => {
        document.getElementById('logs_product').textContent = b.dataset.name || '';
        const body = document.getElementById('logs_body');
        body.innerHTML = '<div class="text-center py-3"><span class="spinner-border spinner-border-sm"></span></div>';
        logModal.show();
        const res = await fetch(fill(urlTpl.logs, {'__ID__': b.dataset.id}));
        body.innerHTML = (await res.json()).html;
    }));
})();
</script>
@endpush
