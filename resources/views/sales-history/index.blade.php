@extends('components.layouts.app')

@section('title', 'History Penjualan')

@php
    $rp = fn ($n) => 'Rp ' . number_format((int) $n, 0, ',', '.');
    $today = \Carbon\Carbon::today();
    $cleanStore = fn ($name) => trim(preg_replace(['/^(?:Shop \| [^-]+-|[A-Z]+-)/i', '/\s*\(TTS\)$/i'], '', $name ?? '')) ?: ($name ?? '—');
@endphp

@section('content')

    <div class="md-page-header">
        <div>
            <h1>History Penjualan</h1>
            <p class="subtitle">Penjualan selesai per produk, SKU variasi & toko (Tokopedia + TikTok)</p>
        </div>
    </div>

    @include('components.alert')

    {{-- ── Filter ─────────────────────────────────────────────── --}}
    <form method="GET" action="{{ route('sales-history.index') }}"
          style="background:var(--md-surface-container-low);border:1px solid var(--md-outline-variant);
                 border-radius:var(--md-shape-md,16px);padding:16px;margin-bottom:20px;">
        <div class="row g-3 align-items-end">
            <div class="col-6 col-md-3">
                <label style="font-size:12px;color:var(--md-on-surface-variant);font-weight:600;">Dari Tanggal</label>
                <input type="date" name="date_from" value="{{ $from }}" class="form-control form-control-sm">
            </div>
            <div class="col-6 col-md-3">
                <label style="font-size:12px;color:var(--md-on-surface-variant);font-weight:600;">Sampai Tanggal</label>
                <input type="date" name="date_to" value="{{ $to }}" class="form-control form-control-sm">
            </div>
            <div class="col-6 col-md-2">
                <label style="font-size:12px;color:var(--md-on-surface-variant);font-weight:600;">Channel</label>
                <select name="channel_id" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    @foreach ($channels as $id => $name)
                        <option value="{{ $id }}" @selected($channelId === $id)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label style="font-size:12px;color:var(--md-on-surface-variant);font-weight:600;">Toko</label>
                <select name="store_id" class="form-select form-select-sm">
                    <option value="">Semua</option>
                    @foreach ($storeOptions as $s)
                        <option value="{{ $s->store_id }}" @selected($storeId === (int) $s->store_id)>{{ $cleanStore($s->store_name) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-md-2 d-flex gap-2">
                <button type="submit" class="btn-md-filled w-100"><i class="bi bi-funnel"></i> Filter</button>
            </div>
        </div>

        {{-- Quick presets --}}
        <div class="d-flex flex-wrap gap-2 mt-3">
            @php
                $presets = [
                    'Hari ini'    => [$today->toDateString(), $today->toDateString()],
                    '7 hari'      => [$today->copy()->subDays(6)->toDateString(), $today->toDateString()],
                    '30 hari'     => [$today->copy()->subDays(29)->toDateString(), $today->toDateString()],
                    'Bulan ini'   => [$today->copy()->startOfMonth()->toDateString(), $today->toDateString()],
                    'Tahun ini'   => [$today->copy()->startOfYear()->toDateString(), $today->toDateString()],
                ];
            @endphp
            @foreach ($presets as $label => [$pf, $pt])
                @php $isActive = ($from === $pf && $to === $pt); @endphp
                <a href="{{ route('sales-history.index', array_filter(['date_from' => $pf, 'date_to' => $pt, 'channel_id' => $channelId, 'store_id' => $storeId])) }}"
                   style="font-size:12px;padding:5px 12px;border-radius:var(--md-shape-full,999px);text-decoration:none;
                          border:1px solid var(--md-outline-variant);
                          background:{{ $isActive ? 'var(--md-secondary-container)' : 'transparent' }};
                          color:{{ $isActive ? 'var(--md-on-secondary-container)' : 'var(--md-on-surface-variant)' }};
                          font-weight:{{ $isActive ? '600' : '500' }};">{{ $label }}</a>
            @endforeach
        </div>
    </form>

    {{-- ── Ringkasan ──────────────────────────────────────────── --}}
    <div class="row g-3 mb-4">
        @php
            $cards = [
                ['Total Omzet', $rp($totals['omzet']), 'bi-cash-stack', 'var(--md-primary)'],
                ['Total Qty Terjual', number_format($totals['qty'], 0, ',', '.'), 'bi-box-seam', 'var(--md-tertiary, #7F77DD)'],
                ['Total Pesanan', number_format($totals['orders'], 0, ',', '.'), 'bi-receipt', '#1D9E75'],
                ['Jumlah Produk', number_format($totals['products'], 0, ',', '.'), 'bi-grid', '#BA7517'],
            ];
        @endphp
        @foreach ($cards as [$label, $val, $icon, $color])
            <div class="col-6 col-md-3">
                <div style="background:var(--md-surface-container-low);border:1px solid var(--md-outline-variant);
                            border-radius:var(--md-shape-md,16px);padding:clamp(10px,3vw,16px);height:100%;">
                    <div style="display:flex;align-items:center;gap:clamp(5px,1.5vw,8px);margin-bottom:6px;">
                        <i class="bi {{ $icon }}" style="font-size:clamp(13px,3vw,16px);color:{{ $color }}"></i>
                        <span style="font-size:clamp(11px,2.5vw,12px);color:var(--md-on-surface-variant);font-weight:600;line-height:1.2;">{{ $label }}</span>
                    </div>
                    <div style="font-size:clamp(14px,4vw,20px);font-weight:700;color:var(--md-on-surface);word-break:break-word;">{{ $val }}</div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- ── Pencarian produk + tombol expand (client-side) ──────── --}}
    <div class="mb-3 d-flex align-items-center gap-2 flex-wrap">
        <div style="position:relative;flex:1;min-width:200px;max-width:360px;">
            <i class="bi bi-search" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:var(--md-on-surface-variant);font-size:14px;"></i>
            <input type="text" id="productSearch" placeholder="Cari produk / SKU..."
                   class="form-control form-control-sm" style="padding-left:34px;">
        </div>
        <button id="btnExpandAll" type="button" class="btn-md-outlined" style="font-size:12px;padding:5px 12px;">
            <i class="bi bi-arrows-expand"></i> Buka Semua
        </button>
        <button id="btnCollapseAll" type="button" class="btn-md-outlined" style="font-size:12px;padding:5px 12px;">
            <i class="bi bi-arrows-collapse"></i> Tutup Semua
        </button>
    </div>

    {{-- ── Sort produk (client-side) ────────────────────────────── --}}
    @if (!empty($products))
    <div class="mb-2 d-flex align-items-center gap-2 flex-wrap">
        <span style="font-size:12px;color:var(--md-on-surface-variant);">Urutkan:</span>
        <button class="sort-btn btn-md-tonal active" data-sort="omzet" type="button" style="font-size:12px;padding:4px 12px;">Omzet ↓</button>
        <button class="sort-btn btn-md-outlined" data-sort="qty"   type="button" style="font-size:12px;padding:4px 12px;">Qty ↓</button>
        <button class="sort-btn btn-md-outlined" data-sort="orders" type="button" style="font-size:12px;padding:4px 12px;">Pesanan ↓</button>
    </div>
    @endif

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
                @php $pid = 'p_' . md5($p['parent_sku']); @endphp
                <div class="accordion-item sales-product-item"
                     data-search="{{ strtolower($p['parent_sku'] . ' ' . $p['product_name'] . ' ' . implode(' ', array_column($p['variants'], 'sku'))) }}"
                     data-omzet="{{ $p['omzet'] }}"
                     data-qty="{{ $p['qty'] }}"
                     data-orders="{{ $p['orders'] }}"
                     style="border:1px solid var(--md-outline-variant);border-radius:var(--md-shape-md,16px);
                            margin-bottom:10px;overflow:hidden;background:var(--md-surface);">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button"
                                data-bs-toggle="collapse" data-bs-target="#{{ $pid }}"
                                style="background:var(--md-surface);box-shadow:none;">
                            <div class="d-flex flex-wrap align-items-center justify-content-between w-100" style="gap:10px;padding-right:8px;">
                                <div style="min-width:0;">
                                    <div style="font-weight:700;color:var(--md-on-surface);font-size:14px;">{{ $p['parent_sku'] }}</div>
                                    <div style="font-size:12px;color:var(--md-on-surface-variant);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:340px;">
                                        {{ $p['product_name'] ?: '—' }}
                                    </div>
                                    <div class="mt-1 d-flex gap-1 flex-wrap">
                                        @foreach ($p['channels'] as $cName)
                                            <span style="font-size:10px;font-weight:600;padding:1px 8px;border-radius:999px;
                                                         background:var(--md-secondary-container);color:var(--md-on-secondary-container);">{{ $cName }}</span>
                                        @endforeach
                                    </div>
                                </div>
                                <div class="d-flex" style="gap:18px;text-align:right;">
                                    <div>
                                        <div style="font-size:11px;color:var(--md-on-surface-variant);">Qty</div>
                                        <div style="font-weight:700;color:var(--md-on-surface);">{{ number_format($p['qty'], 0, ',', '.') }}</div>
                                    </div>
                                    <div>
                                        <div style="font-size:11px;color:var(--md-on-surface-variant);">Omzet</div>
                                        <div style="font-weight:700;color:var(--md-primary);">{{ $rp($p['omzet']) }}</div>
                                    </div>
                                </div>
                            </div>
                        </button>
                    </h2>
                    <div id="{{ $pid }}" class="accordion-collapse collapse">
                        <div class="accordion-body" style="padding:0;">
                            <div class="table-responsive">
                                <table class="table table-sm mb-0" style="font-size:13px;">
                                    <thead>
                                        <tr style="background:var(--md-surface-container-low);">
                                            <th style="font-size:11px;color:var(--md-on-surface-variant);">SKU Variasi</th>
                                            <th style="font-size:11px;color:var(--md-on-surface-variant);">Toko</th>
                                            <th style="font-size:11px;color:var(--md-on-surface-variant);">Channel</th>
                                            <th class="text-end" style="font-size:11px;color:var(--md-on-surface-variant);">Qty</th>
                                            <th class="text-end" style="font-size:11px;color:var(--md-on-surface-variant);">Omzet</th>
                                            <th class="text-end" style="font-size:11px;color:var(--md-on-surface-variant);">Pesanan</th>
                                            <th class="text-end" style="font-size:11px;color:var(--md-on-surface-variant);">Avg/Pesanan</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($p['variants'] as $v)
                                            @foreach ($v['stores'] as $i => $st)
                                                <tr>
                                                    @if ($i === 0)
                                                        <td rowspan="{{ count($v['stores']) }}" style="vertical-align:top;font-weight:600;color:var(--md-on-surface);border-right:1px solid var(--md-outline-variant);">
                                                            {{ $v['sku'] }}
                                                            <div style="font-size:11px;font-weight:500;color:var(--md-on-surface-variant);">
                                                                Σ {{ number_format($v['qty'], 0, ',', '.') }} · {{ $rp($v['omzet']) }}
                                                            </div>
                                                        </td>
                                                    @endif
                                                    <td>{{ $cleanStore($st['store_name']) }}</td>
                                                    <td>{{ $st['channel_name'] }}</td>
                                                    <td class="text-end">{{ number_format($st['qty'], 0, ',', '.') }}</td>
                                                    <td class="text-end" style="color:var(--md-primary);font-weight:600;">{{ $rp($st['omzet']) }}</td>
                                                    <td class="text-end">{{ number_format($st['orders'], 0, ',', '.') }}</td>
                                                    <td class="text-end" style="color:var(--md-on-surface-variant);">{{ $rp($st['orders'] > 0 ? $st['omzet'] / $st['orders'] : 0) }}</td>
                                                </tr>
                                            @endforeach
                                        @endforeach
                                    </tbody>
                                </table>
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
    document.getElementById('productSearch')?.addEventListener('input', function (e) {
        const q = e.target.value.toLowerCase().trim();
        document.querySelectorAll('.sales-product-item').forEach(function (el) {
            el.style.display = el.dataset.search.includes(q) ? '' : 'none';
        });
    });

    document.querySelectorAll('.sort-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const key = this.dataset.sort;
            const container = document.getElementById('salesAccordion');
            if (!container) return;

            const items = Array.from(container.querySelectorAll('.sales-product-item'));
            items.sort((a, b) => Number(b.dataset[key]) - Number(a.dataset[key]));
            items.forEach(el => container.appendChild(el));

            document.querySelectorAll('.sort-btn').forEach(b => {
                b.classList.remove('active', 'btn-md-tonal');
                b.classList.add('btn-md-outlined');
            });
            this.classList.remove('btn-md-outlined');
            this.classList.add('btn-md-tonal', 'active');
        });
    });

    document.getElementById('btnExpandAll')?.addEventListener('click', function () {
        document.querySelectorAll('.sales-product-item .accordion-collapse').forEach(function (el) {
            bootstrap.Collapse.getOrCreateInstance(el).show();
        });
    });

    document.getElementById('btnCollapseAll')?.addEventListener('click', function () {
        document.querySelectorAll('.sales-product-item .accordion-collapse').forEach(function (el) {
            bootstrap.Collapse.getOrCreateInstance(el).hide();
        });
    });
</script>
@endpush
