@php $stockData = $stockData ?? []; @endphp

<div class="md-table-wrap">
<table class="md-table table-hover align-middle">
    <thead>
        <tr>
            <th style="width:44px" class="text-center">
                <input type="checkbox" class="form-check-input" id="select_all"
                       style="width:16px;height:16px;cursor:pointer;border-color:var(--md-outline)">
            </th>
            <th>Produk</th>
            <th>Toko</th>
            <th class="text-center">Testing</th>
            <th class="text-center">Status</th>
            <th class="text-center">Stok</th>
            <th class="text-center">
                @if(request('testing_status') === 'perlu_dicek') Tgl Berakhir @else Tgl Ditambah @endif
            </th>
            <th class="text-center" style="width:130px">Tindakan</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($productAds as $productAd)
            @php
                $parentSku  = $productAd->product->parent_sku ?? 'N/A';
                $variants   = $stockData[$parentSku] ?? [];
                $totalStock = array_sum(array_column($variants, 'qty'));
                $skuCount   = count($variants);

                $rowBg       = '';
                $urgencyChip = '';

                // Stock color helpers (thresholds match dashboard)
                $stockChipClass = function (int $qty): string {
                    if ($qty <= 20)  return 'error';
                    if ($qty <= 100) return 'warning';
                    return 'primary';
                };
                $minQty          = !empty($variants) ? min(array_column($variants, 'qty')) : null;
                $totalChipClass  = $minQty !== null ? $stockChipClass($minQty) : 'surface';

                if (request('testing_status') === 'perlu_dicek' && $productAd->testing_completed_at) {
                    $endDate  = \Carbon\Carbon::parse($productAd->testing_completed_at)->startOfDay();
                    $diffDays = \Carbon\Carbon::now()->startOfDay()->diffInDays($endDate, false);

                    if ($diffDays < 0) {
                        $rowBg       = 'background:rgba(186,26,26,.05)';
                        $urgencyChip = '<span class="md-chip error ms-1">Terlewat</span>';
                    } elseif ($diffDays <= 3) {
                        $rowBg       = 'background:rgba(122,88,0,.05)';
                        $urgencyChip = $diffDays == 0
                            ? '<span class="md-chip warning ms-1">Hari Ini</span>'
                            : '<span class="md-chip warning ms-1">H-'.$diffDays.'</span>';
                    } else {
                        $urgencyChip = '<span class="md-chip primary ms-1">Aman H-'.$diffDays.'</span>';
                    }
                }
            @endphp

            {{-- ── Baris utama produk ───────────────────────── --}}
            <tr class="product-row" style="{{ $rowBg }}">
                <td class="text-center" style="vertical-align:middle">
                    <input type="checkbox" name="ids[]" value="{{ $productAd->id }}"
                           class="form-check-input sub_chk"
                           style="width:16px;height:16px;cursor:pointer;border-color:var(--md-outline)">
                </td>
                <td>
                    <p class="mb-0 fw-medium" style="font-size:14px;color:var(--md-on-surface)">{{ $parentSku }}</p>
                    <p class="mb-0 mt-1" style="font-size:12px;color:var(--md-on-surface-variant)">
                        {{ $productAd->product->category->name ?? 'Uncategorized' }}
                    </p>
                </td>
                <td>
                    <div class="d-flex flex-wrap gap-1">
                        @foreach ($productAd->stores as $store)
                            <span class="md-chip info">{{ $store->name }}</span>
                        @endforeach
                    </div>
                </td>
                <td class="text-center">{!! $productAd->status_testing !!}</td>
                <td class="text-center">{!! $productAd->status_badge !!}</td>
                <td class="text-center">
                    @if($skuCount > 0)
                        <span class="md-chip {{ $totalChipClass }}" style="font-size:13px;font-weight:500">
                            {{ number_format($totalStock) }}
                        </span>
                    @else
                        <span style="color:var(--md-outline)">–</span>
                    @endif
                </td>
                @if(request('testing_status') === 'perlu_dicek')
                    <td class="text-center" style="font-size:13px">
                        @if($productAd->testing_completed_at)
                            <span style="color:var(--md-on-surface);font-weight:500">
                                {{ \Carbon\Carbon::parse($productAd->testing_completed_at)->format('d M Y') }}
                            </span>
                            {!! $urgencyChip !!}
                        @else
                            <span style="color:var(--md-outline)">–</span>
                        @endif
                    </td>
                @else
                    <td class="text-center" style="font-size:12px;color:var(--md-on-surface-variant)">
                        {{ $productAd->created_at->format('d M Y') }}<br>
                        <span style="color:var(--md-outline)">{{ $productAd->created_at->format('H:i') }}</span>
                    </td>
                @endif
                <td>
                    <div class="d-flex justify-content-center align-items-center gap-0">
                        <a href="{{ route('product-ads.show', $productAd->id) }}"
                           class="btn-md-icon primary" title="Lihat Detail">
                            <i class="bi bi-eye"></i>
                        </a>
                        <a href="{{ route('product-ads.edit', $productAd->id) }}"
                           class="btn-md-icon warning" title="Edit">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <button type="button" class="btn-md-icon error" title="Hapus"
                                data-bs-toggle="modal"
                                data-bs-target="#deleteConfirmModal"
                                data-action="{{ route('product-ads.destroy', $productAd->id) }}">
                            <i class="bi bi-trash3"></i>
                        </button>
                        @if(request('testing_status') === 'perlu_dicek')
                            <button type="button" class="btn-md-icon" style="color:var(--md-primary)"
                                    title="Tandai Berhasil"
                                    data-action="{{ route('product-ads.mark-success', $productAd->id) }}"
                                    data-message="Tandai sebagai berhasil?"
                                    class="btn-status-action">
                                <i class="bi bi-check2-circle"></i>
                            </button>
                        @endif
                    </div>
                    @if(request('testing_status') === 'perlu_dicek')
                    <div class="d-flex justify-content-center align-items-center gap-0 mt-1">
                        <button type="button" class="btn-md-icon error btn-status-action"
                                title="Tandai Gagal"
                                data-action="{{ route('product-ads.mark-fail', $productAd->id) }}"
                                data-message="Tandai sebagai gagal?">
                            <i class="bi bi-x-circle"></i>
                        </button>
                        <button type="button" class="btn-md-icon tertiary"
                                title="Perpanjang"
                                data-bs-toggle="modal"
                                data-bs-target="#extendModal"
                                data-url="{{ route('product-ads.extend', $productAd->id) }}">
                            <i class="bi bi-arrow-repeat"></i>
                        </button>
                    </div>
                    @endif
                </td>
            </tr>

            {{-- ── Baris SKU expand ─────────────────────────── --}}
            <tr class="sku-row" style="{{ $rowBg }}">
                <td style="border-top:0;padding-top:0"></td>
                <td colspan="7" style="border-top:0;padding-top:0;padding-bottom:12px">
                    @if($skuCount > 0)
                        <button type="button"
                                class="sku-toggle-btn d-inline-flex align-items-center gap-1"
                                style="background:none;border:none;padding:0;cursor:pointer;font-size:12px;font-weight:500;color:var(--md-on-surface-variant)"
                                data-bs-toggle="collapse"
                                data-bs-target="#sku-{{ $productAd->id }}"
                                aria-expanded="false">
                            <i class="bi bi-chevron-right sku-chev" style="font-size:10px;transition:transform .2s"></i>
                            {{ $skuCount }} SKU
                        </button>
                        <div class="collapse mt-2" id="sku-{{ $productAd->id }}">
                            <div style="border-left:2px solid var(--md-outline-variant);padding-left:14px;max-width:420px">
                                <table style="width:100%;border-collapse:collapse">
                                    <thead>
                                        <tr>
                                            <th style="font-size:11px;font-weight:500;letter-spacing:.5px;text-transform:uppercase;color:var(--md-on-surface-variant);padding:4px 0;border:none">SKU</th>
                                            <th style="font-size:11px;font-weight:500;letter-spacing:.5px;text-transform:uppercase;color:var(--md-on-surface-variant);padding:4px 0;text-align:right;border:none">Stok</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($variants as $v)
                                        @php $vQty = (int) $v['qty']; @endphp
                                        <tr style="border-top:1px solid var(--md-outline-variant)">
                                            <td style="font-size:13px;padding:6px 0;color:var(--md-on-surface)">{{ $v['sku'] }}</td>
                                            <td style="padding:6px 0;text-align:right">
                                                <span class="md-chip {{ $stockChipClass($vQty) }}" style="font-size:12px">
                                                    {{ number_format($vQty) }}
                                                </span>
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @else
                        <span style="font-size:12px;color:var(--md-outline)">Tidak ada data SKU dari ERP</span>
                    @endif
                </td>
            </tr>

        @empty
            <tr>
                <td colspan="8" style="padding:48px 24px;text-align:center">
                    <i class="bi bi-inbox d-block mb-3" style="font-size:2.5rem;color:var(--md-outline)"></i>
                    <p class="mb-1" style="font-size:15px;font-weight:500;color:var(--md-on-surface)">Tidak Ada Data Iklan</p>
                    <p class="mb-0" style="font-size:13px;color:var(--md-on-surface-variant)">Sesuaikan filter atau buat iklan produk baru.</p>
                </td>
            </tr>
        @endforelse
    </tbody>
</table>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.sku-toggle-btn').forEach(function (btn) {
        var target = document.querySelector(btn.dataset.bsTarget);
        var chev   = btn.querySelector('.sku-chev');
        if (!target || !chev) return;
        target.addEventListener('show.bs.collapse', function () { chev.style.transform = 'rotate(90deg)'; });
        target.addEventListener('hide.bs.collapse', function () { chev.style.transform = 'rotate(0deg)'; });
    });
});
</script>
@endpush
