@extends('components.layouts.app')

@section('title', 'Selisih Harga')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/products.css') }}?v={{ filemtime(public_path('css/products.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/price-comparison.css') }}?v={{ filemtime(public_path('css/price-comparison.css')) }}">
@endpush

@section('content')

    <div class="products-sticky-header">
        <div class="products-title-block">
            <div style="min-width:0">
                <h1>Selisih Harga</h1>
                <div class="products-freshness-row d-flex align-items-center gap-2">
                    <span class="d-inline-flex align-items-center gap-1">
                        <i class="bi bi-info-circle"></i> Harga terendah/tertinggi/selisih dihitung hanya dari toko yang sedang promo
                    </span>
                </div>
            </div>

            <div class="d-flex align-items-center gap-2" style="flex-shrink:0">
                <a href="{{ route('price-comparison.export', array_filter(['search' => $search ?: null, 'diff_only' => $diffOnly ? 1 : null])) }}"
                   class="products-export-btn"
                   title="{{ $diffOnly ? 'Export SKU yang ada selisih harga ke Excel' : 'Export semua data ke Excel' }}">
                    <i class="bi bi-download"></i>
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

    @php
        $buildSortUrl = function (string $key) use ($search, $diffOnly, $sortKey, $sortDir) {
            $nextDir = ($sortKey === $key && $sortDir === 'asc') ? 'desc' : 'asc';

            return route('price-comparison.index', array_filter([
                'search' => $search ?: null,
                'diff_only' => $diffOnly ? 1 : null,
                'sort' => $key,
                'dir' => $nextDir,
            ]));
        };
        $sortArrow = fn (string $key) => $sortKey === $key ? ($sortDir === 'asc' ? '&#9650;' : '&#9660;') : '';
    @endphp

    <div class="md-table-wrap price-comparison-scroll">
        <table class="md-table table-hover align-middle">
            <thead>
                <tr>
                    <th class="col-sku"><a href="{{ $buildSortUrl('sku') }}">Seller SKU <span class="sort-arrow">{!! $sortArrow('sku') !!}</span></a></th>
                    <th class="text-end"><a href="{{ $buildSortUrl('hpp') }}">HPP <span class="sort-arrow">{!! $sortArrow('hpp') !!}</span></a></th>
                    <th class="text-end"><a href="{{ $buildSortUrl('min') }}">Harga Terendah <span class="sort-arrow">{!! $sortArrow('min') !!}</span></a></th>
                    <th class="text-end"><a href="{{ $buildSortUrl('max') }}">Harga Tertinggi <span class="sort-arrow">{!! $sortArrow('max') !!}</span></a></th>
                    <th class="text-end"><a href="{{ $buildSortUrl('selisih') }}">Selisih (Rp) <span class="sort-arrow">{!! $sortArrow('selisih') !!}</span></a></th>
                    @foreach($stores as $store)
                        <th class="text-end">
                            <a href="{{ $buildSortUrl('store:' . $store->id) }}">{{ ucwords($store->name) }} <span class="sort-arrow">{!! $sortArrow('store:' . $store->id) !!}</span></a>
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td class="col-sku" style="font-size:13.5px;color:var(--md-on-surface)">{{ $row['sku_code'] }}</td>
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
                                $diverges = $row['diverges'][$store->id] ?? false;
                            @endphp
                            <td class="text-end"
                                style="font-size:13.5px;color:{{ $isDiffCell ? 'var(--md-on-error-container)' : 'var(--md-on-surface)' }};
                                       background:{{ $isDiffCell ? 'var(--md-error-container)' : 'transparent' }}">
                                {!! $rupiah($price) !!}
                                @if($diverges)
                                    <i class="bi bi-exclamation-triangle-fill"
                                       style="font-size:10px;color:var(--md-tertiary);margin-left:4px"
                                       title="Harga berbeda antar listing (Product ID) di toko ini — lihat detail per listing di menu Products"></i>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ 5 + $stores->count() }}" class="text-center" style="padding:32px;color:var(--md-on-surface-variant)">
                            Tidak ada data.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($rows->isNotEmpty())
        @include('products.partials.pagination', ['catalog' => $rows, 'perPage' => $perPage])
    @endif

@endsection
