@extends('components.layouts.app')

@section('title', 'Perbandingan Harga Promo')

@section('content')

    <div class="products-sticky-header">
        <div class="products-title-block">
            <div style="min-width:0">
                <h1>Perbandingan Harga Promo</h1>
                <div class="products-freshness-row d-flex align-items-center gap-2">
                    <span class="d-inline-flex align-items-center gap-1">
                        <i class="bi bi-info-circle"></i> Harga terendah/tertinggi/selisih dihitung hanya dari toko yang sedang promo
                    </span>
                </div>
            </div>

            <div class="d-flex align-items-center gap-2" style="flex-shrink:0">
                <a href="{{ route('price-comparison.export', array_filter(['search' => $search ?: null])) }}"
                   class="products-export-btn"
                   title="Export semua data ke Excel">
                    <i class="bi bi-download"></i> Semua
                </a>
                <a href="{{ route('price-comparison.export', array_filter(['search' => $search ?: null, 'diff_only' => 1])) }}"
                   class="products-export-btn"
                   title="Export hanya SKU yang ada selisih harga ke Excel">
                    <i class="bi bi-download"></i> Selisih Saja
                </a>
            </div>
        </div>

        <form method="GET" action="{{ route('price-comparison.index') }}" class="products-filter-row mb-0 d-flex flex-wrap align-items-center gap-2" autocomplete="off">
            <div class="input-group" style="max-width:340px;flex:1 1 240px">
                <span class="input-group-text"
                      style="background:var(--md-surface-container-high);border-color:var(--md-outline);border-right:0;border-radius:var(--md-shape-xs) 0 0 var(--md-shape-xs);color:var(--md-on-surface-variant)">
                    <i class="bi bi-search" style="font-size:14px"></i>
                </span>
                <input type="text" name="search" value="{{ $search ?? '' }}"
                       class="form-control"
                       placeholder="Cari seller SKU…"
                       style="border-color:var(--md-outline);border-left:0;border-right:{{ !empty($search) ? '0' : '' }};font-size:13.5px;background:transparent;color:var(--md-on-surface)">
                @if(!empty($search))
                    <a href="{{ route('price-comparison.index', array_filter(['diff_only' => $diffOnly ? 1 : null])) }}"
                       class="input-group-text"
                       style="background:transparent;border-color:var(--md-outline);border-left:0;border-radius:0 var(--md-shape-xs) var(--md-shape-xs) 0;color:var(--md-on-surface-variant);text-decoration:none"
                       title="Hapus pencarian">
                        <i class="bi bi-x-lg" style="font-size:12px"></i>
                    </a>
                @endif
            </div>

            <div class="input-group" style="width:auto">
                <span class="input-group-text"
                      style="background:var(--md-surface-container-high);border-color:var(--md-outline);border-right:0;border-radius:var(--md-shape-xs) 0 0 var(--md-shape-xs);color:var(--md-on-surface-variant)">
                    <i class="bi bi-tags" style="font-size:14px"></i>
                </span>
                <label class="btn d-inline-flex align-items-center gap-2 m-0"
                       title="Tampilkan hanya SKU yang harga promonya beda antar toko"
                       style="border:1px solid {{ $diffOnly ? 'var(--md-tertiary)' : 'var(--md-outline)' }};
                              border-left:0;
                              background:{{ $diffOnly ? 'var(--md-tertiary-container)' : 'transparent' }};
                              color:{{ $diffOnly ? 'var(--md-on-tertiary-container)' : 'var(--md-on-surface)' }};
                              border-radius:0 var(--md-shape-xs) var(--md-shape-xs) 0;font-size:13.5px;font-weight:{{ $diffOnly ? 600 : 400 }}">
                    <input type="checkbox" name="diff_only" value="1" onchange="this.form.submit()" @checked($diffOnly) class="d-none">
                    Ada selisih harga
                    @if($diffOnly)<i class="bi bi-check-lg" style="font-size:14px"></i>@endif
                </label>
            </div>
        </form>
    </div>

    @include('components.alert')

    @php
        $rupiah = fn ($v) => $v === null
            ? '<span style="color:var(--md-on-surface-variant)">-</span>'
            : 'Rp' . number_format((int) $v, 0, ',', '.');
        $hppDisplay = fn ($h) => (int) $h > 0
            ? 'Rp' . number_format((int) $h, 0, ',', '.')
            : '<span style="color:var(--md-on-surface-variant)">-</span>';
    @endphp

    <div class="md-table-wrap">
        <table class="md-table table-hover align-middle">
            <thead>
                <tr>
                    <th>Seller SKU</th>
                    <th>Variasi</th>
                    <th class="text-end" style="width:100px">HPP</th>
                    <th class="text-end" style="width:130px">Harga Terendah</th>
                    <th class="text-end" style="width:130px">Harga Tertinggi</th>
                    <th class="text-end" style="width:120px">Selisih (Rp)</th>
                    @foreach($stores as $store)
                        <th class="text-end" style="width:130px">{{ ucwords($store->name) }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td style="font-size:13.5px;color:var(--md-on-surface)">{{ $row['sku_code'] }}</td>
                        <td style="font-size:13.5px;color:var(--md-on-surface)">{{ $row['variasi'] ?? '-' }}</td>
                        <td class="text-end" style="font-size:13.5px;color:var(--md-on-surface)">{!! $hppDisplay($row['hpp']) !!}</td>
                        <td class="text-end" style="font-size:13.5px;color:var(--md-on-surface)">{!! $rupiah($row['min']) !!}</td>
                        <td class="text-end" style="font-size:13.5px;color:var(--md-on-surface)">{!! $rupiah($row['max']) !!}</td>
                        <td class="text-end" style="font-size:13.5px;color:var(--md-on-surface);font-weight:{{ $row['has_diff'] ? 600 : 400 }}">
                            {!! $rupiah($row['selisih']) !!}
                        </td>
                        @foreach($stores as $store)
                            @php
                                $price = $row['prices'][$store->id] ?? null;
                                $isDiffCell = $price !== null && $row['mode'] !== null && $price !== $row['mode'];
                            @endphp
                            <td class="text-end"
                                style="font-size:13.5px;color:{{ $isDiffCell ? 'var(--md-on-error-container)' : 'var(--md-on-surface)' }};
                                       background:{{ $isDiffCell ? 'var(--md-error-container)' : 'transparent' }}">
                                {!! $rupiah($price) !!}
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ 6 + $stores->count() }}" class="text-center" style="padding:32px;color:var(--md-on-surface-variant)">
                            Tidak ada data.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($rows->isNotEmpty())
        <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mt-4 products-pagination-bar">
            <p class="mb-0" style="font-size:13px;color:var(--md-on-surface-variant)">
                Total baris:
                <strong style="color:var(--md-on-surface)">{{ number_format($rows->total(), 0, ',', '.') }}</strong>
            </p>
            <nav class="products-pager" aria-label="Navigasi halaman">
                @if ($rows->onFirstPage())
                    <span class="products-pager-btn disabled"><i class="bi bi-chevron-left"></i></span>
                @else
                    <a href="{{ $rows->previousPageUrl() }}" class="products-pager-btn"><i class="bi bi-chevron-left"></i></a>
                @endif
                <span class="products-pager-btn active">{{ $rows->currentPage() }} / {{ $rows->lastPage() }}</span>
                @if ($rows->hasMorePages())
                    <a href="{{ $rows->nextPageUrl() }}" class="products-pager-btn"><i class="bi bi-chevron-right"></i></a>
                @else
                    <span class="products-pager-btn disabled"><i class="bi bi-chevron-right"></i></span>
                @endif
            </nav>
        </div>
    @endif

@endsection
