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
                <th style="width:44px" class="text-center">
                    <input type="checkbox" class="form-check-input" id="select_all"
                           style="width:16px;height:16px;cursor:pointer;border-color:var(--md-outline)">
                </th>
                <th>SKU / Produk</th>
                <th>Kategori</th>
                <th>Harga Normal</th>
                <th>Harga Promo</th>
                <th class="text-center" style="width:110px">Tindakan</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($products as $product)
                <tr>
                    <td class="text-center" style="vertical-align:middle">
                        <input type="checkbox" name="ids[]" value="{{ $product->id }}"
                               class="form-check-input sub_chk"
                               style="width:16px;height:16px;cursor:pointer;border-color:var(--md-outline)">
                    </td>
                    <td>
                        <p class="mb-0 fw-medium" style="font-size:14px;color:var(--md-on-surface)">
                            {{ $product->parent_sku ?? 'N/A' }}
                        </p>
                    </td>
                    <td>
                        <span class="md-chip secondary">{{ $product->category->name ?? 'Uncategorized' }}</span>
                    </td>
                    @php $p = ($prices ?? collect())->get(strtoupper($product->parent_sku)); @endphp
                    <td style="font-size:13.5px;color:var(--md-on-surface);white-space:nowrap">
                        {!! $fmtRange($p->retail_min ?? null, $p->retail_max ?? null) !!}
                    </td>
                    <td style="font-size:13.5px;color:var(--md-on-surface);white-space:nowrap">
                        {!! $fmtRange($p->promo_min ?? null, $p->promo_max ?? null) !!}
                    </td>
                    <td>
                        <div class="d-flex justify-content-center align-items-center gap-0">
                            <button type="button"
                                    class="btn-md-icon warning"
                                    title="Edit Produk"
                                    data-bs-toggle="modal"
                                    data-bs-target="#editProductModal"
                                    data-action="{{ route('products.update', $product->id) }}"
                                    data-sku="{{ $product->parent_sku }}"
                                    data-category="{{ $product->category_id }}">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button type="button"
                                    class="btn-md-icon error"
                                    title="Hapus Produk"
                                    data-bs-toggle="modal"
                                    data-bs-target="#deleteConfirmModal"
                                    data-action="{{ route('products.destroy', $product->id) }}">
                                <i class="bi bi-trash3"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" style="padding:48px 24px;text-align:center">
                        <i class="bi bi-inbox d-block mb-3" style="font-size:2.5rem;color:var(--md-outline)"></i>
                        <p class="mb-1" style="font-size:15px;font-weight:500;color:var(--md-on-surface)">Tidak Ada Produk</p>
                        <p class="mb-0" style="font-size:13px;color:var(--md-on-surface-variant)">Tambahkan produk baru untuk mulai.</p>
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
