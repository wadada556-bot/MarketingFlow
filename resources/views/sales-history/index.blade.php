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
@endphp

@section('content')

    <div class="md-page-header">
        <div>
            <h1>History Penjualan</h1>
        </div>
    </div>

    @include('components.alert')

    {{-- ── Filter ─────────────────────────────────────────────── --}}
    <form method="GET" action="{{ route('sales-history.index') }}"
          class="md-card mb-4" style="padding:20px;">
        <div class="row g-3 align-items-end">
            <div class="col-6 col-md-3">
                <div class="md-field" style="margin-bottom:0;">
                    <label>Dari Tanggal</label>
                    <input type="date" name="date_from" value="{{ $from }}" class="form-control">
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="md-field" style="margin-bottom:0;">
                    <label>Sampai Tanggal</label>
                    <input type="date" name="date_to" value="{{ $to }}" class="form-control">
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="md-field" style="margin-bottom:0;">
                    <label>Channel</label>
                    <select name="channel_id" class="form-select">
                        <option value="">Semua</option>
                        @foreach ($channels as $id => $name)
                            <option value="{{ $id }}" @selected($channelId === $id)>{{ $name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="md-field" style="margin-bottom:0;">
                    <label>Toko</label>
                    <select name="store_id" class="form-select">
                        <option value="">Semua</option>
                        @foreach ($storeOptions as $s)
                            <option value="{{ $s->store_id }}" @selected($storeId === (int) $s->store_id)>{{ $cleanStore($s->store_name) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="col-12 col-md-2">
                <button type="submit" class="btn-md-filled w-100"
                        style="justify-content:center;padding-top:11px;padding-bottom:11px;">
                    <i class="bi bi-funnel"></i> Filter
                </button>
            </div>
        </div>

        {{-- Quick presets --}}
        <div class="md-chip-choices mt-3">
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
                   class="md-choice-chip {{ $isActive ? 'checked' : '' }}" style="text-decoration:none;">
                    <i class="bi bi-check md-check-icon"></i>{{ $label }}
                </a>
            @endforeach
        </div>
    </form>

    {{-- ── Ringkasan ──────────────────────────────────────────── --}}
    @php
        $cards = [
            ['Total Omzet',       $rp($totals['omzet']),                          true],
            ['Total Qty Terjual', number_format($totals['qty'],      0, ',', '.'), false],
            ['Total Pesanan',     number_format($totals['orders'],   0, ',', '.'), false],
            ['Jumlah Produk',     number_format($totals['products'], 0, ',', '.'), false],
        ];
    @endphp
    <div class="md-card mb-4" style="display:flex;flex-wrap:wrap;">
        @foreach ($cards as $ci => [$label, $val, $isPrimary])
            <div style="flex:1;min-width:140px;padding:16px 20px;
                        {{ $ci > 0 ? 'border-left:1px solid var(--md-outline-variant);' : '' }}">
                <div style="font-size:12px;color:var(--md-on-surface-variant);font-weight:500;margin-bottom:4px;">{{ $label }}</div>
                <div style="font-size:clamp(16px,3vw,22px);font-weight:700;
                            color:{{ $isPrimary ? 'var(--md-primary)' : 'var(--md-on-surface)' }};">{{ $val }}</div>
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
    <div class="mb-3 d-flex align-items-center gap-2 flex-wrap">
        <span style="font-size:12px;color:var(--md-on-surface-variant);font-weight:500;">Urutkan:</span>
        <button class="sort-btn md-choice-chip checked" data-sort="omzet" data-dir="desc" type="button">
            <i class="bi bi-check md-check-icon"></i>Omzet <span class="sort-arrow">↓</span>
        </button>
        <button class="sort-btn md-choice-chip" data-sort="qty" data-dir="desc" type="button">
            <i class="bi bi-check md-check-icon"></i>Qty <span class="sort-arrow">↓</span>
        </button>
        <button class="sort-btn md-choice-chip" data-sort="orders" data-dir="desc" type="button">
            <i class="bi bi-check md-check-icon"></i>Pesanan <span class="sort-arrow">↓</span>
        </button>
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
                @php
                    $pid = 'p_' . md5($p['parent_sku']);
                    $exportRows = [];
                    foreach ($p['variants'] as $v) {
                        foreach ($v['stores'] as $st) {
                            $exportRows[] = [
                                $v['sku'],
                                $cleanStore($st['store_name']),
                                $st['channel_name'],
                                $st['qty'],
                                $st['omzet'],
                                $st['orders'],
                            ];
                        }
                    }
                @endphp
                <div class="accordion-item sales-product-item"
                     data-search="{{ strtolower($p['parent_sku'] . ' ' . $p['product_name'] . ' ' . implode(' ', array_column($p['variants'], 'sku'))) }}"
                     data-omzet="{{ $p['omzet'] }}"
                     data-qty="{{ $p['qty'] }}"
                     data-orders="{{ $p['orders'] }}"
                     data-parent-sku="{{ $p['parent_sku'] }}"
                     data-export="{{ json_encode($exportRows) }}"
                     style="border:1px solid var(--md-outline-variant);border-radius:var(--md-shape-lg);
                            margin-bottom:12px;overflow:hidden;background:var(--md-surface-container-lowest);">
                    <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button"
                                data-bs-toggle="collapse" data-bs-target="#{{ $pid }}"
                                style="background:var(--md-surface-container-lowest);box-shadow:none;">
                            <div class="d-flex flex-wrap align-items-center w-100" style="gap:16px;padding-right:16px;">
                                <div style="min-width:0;">
                                    <div style="font-weight:700;color:var(--md-on-surface);font-size:19px;letter-spacing:.1px;">{{ $p['parent_sku'] }}</div>
                                    <div class="mt-2 d-flex gap-1 flex-wrap">
                                        @foreach ($p['channels'] as $cName)
                                            <span class="md-chip secondary">{!! $channelIcon($cName) !!} {{ $cName }}</span>
                                        @endforeach
                                    </div>
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
                                                <th style="{{ $thBg }}">Shop / Toko</th>
                                                <th style="{{ $thBg }}">Channel</th>
                                                <th style="text-align:right;{{ $thBg }}">Quantity</th>
                                                <th style="text-align:right;{{ $thBg }}">Omzet</th>
                                                <th style="text-align:right;{{ $thBg }}">Orders</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($p['variants'] as $v)
                                                @php $sCount = count($v['stores']); @endphp
                                                @foreach ($v['stores'] as $i => $st)
                                                    @php $sep = ($i === $sCount - 1) ? 'border-bottom:1px solid var(--md-outline-variant);' : ''; @endphp
                                                    <tr data-sku="{{ strtolower($v['sku']) }}">
                                                        @if ($i === 0)
                                                            <td rowspan="{{ $sCount }}"
                                                                style="font-weight:600;border-right:1px solid var(--md-outline-variant);border-bottom:1px solid var(--md-outline-variant);white-space:nowrap;">
                                                                {{ $v['sku'] }}
                                                                <div style="font-size:11px;font-weight:400;color:var(--md-on-surface-variant);margin-top:2px;">
                                                                    Total: {{ number_format($v['qty'], 0, ',', '.') }} Qty &nbsp; {{ $rp($v['omzet']) }}
                                                                </div>
                                                            </td>
                                                        @endif
                                                        <td style="white-space:nowrap;{{ $sep }}">{{ $cleanStore($st['store_name']) }}</td>
                                                        <td style="white-space:nowrap;{{ $sep }}">
                                                            <span style="display:inline-flex;align-items:center;gap:6px;">
                                                                {!! $channelIcon($st['channel_name']) !!}
                                                                {{ $st['channel_name'] }}
                                                            </span>
                                                        </td>
                                                        <td style="text-align:right;{{ $sep }}">{{ number_format($st['qty'], 0, ',', '.') }}</td>
                                                        <td style="text-align:right;color:var(--md-primary);font-weight:600;{{ $sep }}">{{ $rp($st['omzet']) }}</td>
                                                        <td style="text-align:right;{{ $sep }}">{{ number_format($st['orders'], 0, ',', '.') }}</td>
                                                    </tr>
                                                @endforeach
                                            @endforeach
                                        </tbody>
                                        @php $tfCell = 'padding:14px 16px;font-weight:700;background:var(--md-surface-container);border-top:2px solid var(--md-outline-variant);'; @endphp
                                        <tfoot>
                                            <tr>
                                                <td colspan="3" style="{{ $tfCell }}color:var(--md-on-surface);">TOTAL</td>
                                                <td style="{{ $tfCell }}text-align:right;">{{ number_format($p['qty'], 0, ',', '.') }}</td>
                                                <td style="{{ $tfCell }}text-align:right;color:var(--md-primary);">{{ $rp($p['omzet']) }}</td>
                                                <td style="{{ $tfCell }}text-align:right;">{{ number_format($p['orders'], 0, ',', '.') }}</td>
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
<script src="https://cdn.sheetjs.com/xlsx-latest/package/dist/xlsx.full.min.js"></script>
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

    // Expand / collapse all
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

    // Excel export per product
    document.addEventListener('click', function (e) {
        if (!e.target.closest('.export-csv-btn')) return;
        const item = e.target.closest('.sales-product-item');
        const parentSku = item.dataset.parentSku || 'export';
        const rows = JSON.parse(item.dataset.export || '[]');
        const headers = ['SKU Variation', 'Shop / Toko', 'Channel', 'Quantity', 'Omzet', 'Orders'];
        const ws = XLSX.utils.aoa_to_sheet([headers, ...rows]);
        const wb = XLSX.utils.book_new();
        XLSX.utils.book_append_sheet(wb, ws, 'Sales');
        XLSX.writeFile(wb, parentSku + '_sales.xlsx');
    });
</script>
@endpush
