@php
    // Format rentang harga: satu nilai bila min==max, "Rp.. – Rp.." bila beda, "-" bila kosong.
    $fmtRange = function ($min, $max) {
        if (is_null($min) && is_null($max)) {
            return '<span style="color:var(--md-on-surface-variant)">-</span>';
        }
        $min = $min ?? $max;
        $max = $max ?? $min;
        if ((int) $min === (int) $max) {
            return 'Rp' . number_format((int) $min, 0, ',', '.');
        }
        return 'Rp' . number_format((int) $min, 0, ',', '.')
             . ' <span style="color:var(--md-on-surface-variant)">–</span> Rp'
             . number_format((int) $max, 0, ',', '.');
    };
@endphp

<div class="md-table-wrap">
    <table class="md-table table-hover align-middle">
        <thead>
            <tr>
                <th style="width:44px"></th>
                <th>Produk</th>
                <th class="text-center" style="width:110px">Varian</th>
                <th class="text-end" style="width:100px">Total Stok</th>
                <th class="text-end" style="width:200px">Harga Jual</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($catalog as $row)
                @php $m = ($meta ?? collect())->get($row->product_id); @endphp
                <tr class="js-catalog-row" data-product-id="{{ $row->product_id }}">
                    <td class="text-center">
                        <button type="button" class="btn-md-icon js-expand"
                                title="Lihat varian" aria-expanded="false"
                                style="transition:transform .15s ease">
                            <i class="bi bi-chevron-right"></i>
                        </button>
                    </td>
                    <td>
                        <div class="d-flex align-items-center gap-3">
                            <div class="products-thumb"><i class="bi bi-box-seam"></i></div>
                            <div style="min-width:0">
                                <p class="mb-0 fw-medium" style="font-size:14px;color:var(--md-on-surface)">
                                    {{ $m->induk ?? '-' }}
                                </p>
                                <p class="mb-0" style="font-size:12px;color:var(--md-on-surface-variant);font-variant-numeric:tabular-nums">
                                    {{ $row->product_id }}
                                </p>
                            </div>
                        </div>
                    </td>
                    <td class="text-center">
                        <span class="md-chip secondary">{{ number_format($row->variant_count, 0, ',', '.') }}</span>
                    </td>
                    <td class="text-end" style="font-size:13.5px;color:var(--md-on-surface)">
                        {{ number_format((int) $row->total_stok, 0, ',', '.') }}
                    </td>
                    <td class="text-end" style="font-size:13.5px;color:var(--md-on-surface);white-space:nowrap">
                        <div>{!! $fmtRange($m->retail_min ?? null, $m->retail_max ?? null) !!}</div>
                        <div style="font-size:12px;color:var(--md-on-surface-variant);margin-top:2px">
                            Promosi: <span class="js-promo-range">{!! $fmtRange($m->promo_min ?? null, $m->promo_max ?? null) !!}</span>
                            <button type="button" class="js-bulk-price-edit"
                                    data-product-id="{{ $row->product_id }}"
                                    title="Ubah harga promo semua varian produk ini"
                                    style="background:transparent;border:none;color:var(--md-on-surface-variant);padding:0 2px;margin-left:2px;cursor:pointer">
                                <i class="bi bi-pencil" style="font-size:11px"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" style="padding:48px 24px;text-align:center">
                        <i class="bi bi-inbox d-block mb-3" style="font-size:2.5rem;color:var(--md-outline)"></i>
                        <p class="mb-1" style="font-size:15px;font-weight:500;color:var(--md-on-surface)">Tidak Ada Produk</p>
                        <p class="mb-0" style="font-size:13px;color:var(--md-on-surface-variant)">
                            Katalog Jubelio kosong atau tidak cocok dengan pencarian.
                        </p>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
