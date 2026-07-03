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
                <th>SKU Induk</th>
                <th class="text-center" style="width:100px">Varian</th>
                <th class="text-end" style="width:100px">Total Stok</th>
                <th class="text-end" style="width:100px">Terjual 30h</th>
                <th>Harga Normal</th>
                <th>Harga Promo</th>
                <th class="text-center" style="width:80px">Aksi</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($catalog as $row)
                @php $p = ($prices ?? collect())->get($row->parent_sku); @endphp
                <tr class="js-catalog-row" data-parent="{{ $row->parent_sku }}">
                    <td class="text-center">
                        <button type="button" class="btn-md-icon js-expand"
                                title="Lihat varian" aria-expanded="false"
                                style="transition:transform .15s ease">
                            <i class="bi bi-chevron-right"></i>
                        </button>
                    </td>
                    <td>
                        <p class="mb-0 fw-medium" style="font-size:14px;color:var(--md-on-surface)">
                            {{ $row->parent_sku }}
                        </p>
                    </td>
                    <td class="text-center">
                        <span class="md-chip secondary">{{ number_format($row->variant_count, 0, ',', '.') }}</span>
                    </td>
                    <td class="text-end" style="font-size:13.5px;color:var(--md-on-surface)">
                        {{ number_format((int) $row->total_stok, 0, ',', '.') }}
                    </td>
                    <td class="text-end" style="font-size:13.5px;color:var(--md-on-surface)">
                        {{ number_format((int) (($sales ?? collect())->get($row->parent_sku) ?? 0), 0, ',', '.') }}
                    </td>
                    <td style="font-size:13.5px;color:var(--md-on-surface);white-space:nowrap">
                        {!! $fmtRange($p->retail_min ?? null, $p->retail_max ?? null) !!}
                    </td>
                    <td style="font-size:13.5px;color:var(--md-on-surface);white-space:nowrap">
                        {!! $fmtRange($p->promo_min ?? null, $p->promo_max ?? null) !!}
                    </td>
                    <td class="text-center">
                        <a href="{{ route('product-ads.index', ['create_sku' => $row->parent_sku]) }}"
                           class="btn-md-icon" title="Iklankan produk ini">
                            <i class="bi bi-megaphone"></i>
                        </a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" style="padding:48px 24px;text-align:center">
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
