@push('styles')
<style>
    @keyframes erp-spin { to { transform: rotate(360deg); } }
    .erp-spinner {
        display:inline-block; width:13px; height:13px;
        border:2px solid var(--md-outline-variant);
        border-top-color:var(--md-primary);
        border-radius:50%;
        animation:erp-spin .7s linear infinite;
        vertical-align:middle;
    }
</style>
@endpush

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
                $parentSku = $productAd->product->parent_sku ?? 'N/A';

                $rowBg       = '';
                $urgencyChip = '';

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
                {{-- Stok: di-load lazy via AJAX (lihat skrip di bawah) --}}
                <td class="text-center erp-stock" data-ad-id="{{ $productAd->id }}">
                    <span class="erp-spinner" title="Memuat stok…"></span>
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
                            <button type="button" class="btn-md-icon btn-status-action" style="color:var(--md-primary)"
                                    title="Tandai Berhasil"
                                    data-action="{{ route('product-ads.mark-success', $productAd->id) }}"
                                    data-message="Tandai sebagai berhasil?">
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

            {{-- ── Baris SKU expand (di-load lazy via AJAX) ──── --}}
            <tr class="sku-row" style="{{ $rowBg }}">
                <td style="border-top:0;padding-top:0"></td>
                <td colspan="7" style="border-top:0;padding-top:0;padding-bottom:12px">
                    <div class="erp-detail" data-ad-id="{{ $productAd->id }}">
                        <span style="font-size:12px;color:var(--md-outline)">
                            <span class="erp-spinner"></span> Memuat stok &amp; penjualan…
                        </span>
                    </div>
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
    var pending = document.querySelector('.erp-stock[data-ad-id], .erp-detail[data-ad-id]');
    if (!pending) return;

    function chipClass(qty) {
        return qty <= 100 ? 'error' : qty <= 300 ? 'warning' : 'primary';
    }

    function fmtNum(n) {
        return n === null || n === undefined ? '–' : Number(n).toLocaleString('id');
    }

    function fmtCompact(n) {
        if (n === null || n === undefined) return '–';
        if (n >= 1000000) return (n / 1000000).toFixed(1).replace('.', ',') + ' jt';
        if (n >= 10000)   return (n / 1000).toFixed(1).replace('.', ',') + ' rb';
        return Number(n).toLocaleString('id');
    }

    function rankBadge(sku, rank1, rank2, rank3) {
        var s = 'font-size:10px;font-weight:700;padding:1px 6px;border-radius:20px;vertical-align:middle;margin-left:4px;';
        if (sku === rank1) return '<span style="' + s + 'background:#FFC107;color:#5D4037">#1</span>';
        if (sku === rank2) return '<span style="' + s + 'background:#B0BEC5;color:#263238">#2</span>';
        if (sku === rank3) return '<span style="' + s + 'background:#CD7F32;color:#fff">#3</span>';
        return '';
    }

    function renderStockCell(variants) {
        if (!variants || variants.length === 0) {
            return '<span style="color:var(--md-outline)">–</span>';
        }
        var total = variants.reduce(function (s, v) { return s + (parseInt(v.qty) || 0); }, 0);
        var minQty = Math.min.apply(null, variants.map(function (v) { return parseInt(v.qty) || 0; }));
        return '<span class="md-chip ' + chipClass(minQty) + '" style="font-size:13px;font-weight:500">' + total.toLocaleString('id') + '</span>';
    }

    var sepLeft = ';border-left:1px solid var(--md-outline-variant)';
    var thBase  = 'font-size:11px;font-weight:500;letter-spacing:.5px;text-transform:uppercase;color:var(--md-on-surface-variant);padding:4px 0;border:none';

    function salesCellHtml(val, hasSep) {
        var color  = val === null ? 'var(--md-outline)' : (val === 0 ? 'var(--md-error)' : 'var(--md-on-surface)');
        var weight = val > 0 ? '500' : '400';
        var style  = 'padding:9px 0 9px 12px;text-align:right;font-size:13px;font-weight:' + weight + ';color:' + color;
        if (hasSep) style += sepLeft + ';padding-left:16px';
        return '<td style="' + style + '">' + fmtNum(val) + '</td>';
    }

    function renderTotalTable(variants, sales, po, rank1, rank2, rank3) {
        var headers = ['SKU', 'Stok', 'HPP', 'Hari Ini', 'Kemarin', '7 Hari', '30 Hari', '90 Hari', 'PO'].map(function (h, i) {
            var extra = (i >= 1 ? ';text-align:right' : '');
            if (i === 3) extra += sepLeft + ';padding-left:16px';       // separator sebelum HARI INI
            else if (i === 8) extra += sepLeft + ';padding-left:16px';  // separator sebelum PO
            else if (i > 1) extra += ';padding-left:12px';
            return '<th style="' + thBase + extra + '">' + h + '</th>';
        }).join('');

        var colgroup = '<colgroup>'
            + '<col style="min-width:130px">'
            + '<col style="min-width:64px">'
            + '<col style="min-width:80px">'
            + '<col style="min-width:56px">'
            + '<col style="min-width:56px">'
            + '<col style="min-width:52px">'
            + '<col style="min-width:52px">'
            + '<col style="min-width:60px">'
            + '<col style="min-width:56px">'
            + '</colgroup>';

        var totStock = 0, totToday = 0, totYest = 0, tot7d = 0, tot30d = 0, tot90d = 0, totPo = 0;
        var rows = variants.map(function (v) {
            var vSku = v.sku;
            var vQty = parseInt(v.qty) || 0;
            var getVal = function (period) {
                var p = sales[period] || {};
                return Object.prototype.hasOwnProperty.call(p, vSku) ? parseInt(p[vSku]) : null;
            };
            var vToday = getVal('today');
            var vYest  = getVal('yesterday');
            var v7d    = getVal('7d');
            var v30d   = getVal('30d');
            var v90d   = getVal('90d');
            var vPoQty = Object.prototype.hasOwnProperty.call(po || {}, vSku) ? parseInt(po[vSku]) : null;
            var vHpp   = v.hpp || 0;
            var vActive        = vToday > 0 && vYest > 0 && v7d > 0 && v30d > 0;
            var hasNoSalesData = vToday === null && vYest === null && v7d === null && v30d === null && v90d === null;

            var rowStyle = 'border-top:1px solid var(--md-outline-variant);';
            if (vActive)        rowStyle += 'background:rgba(29,158,117,.12);box-shadow:inset 3px 0 0 #1D9E75;';
            if (hasNoSalesData) rowStyle += 'opacity:0.55;';

            totStock += vQty;
            totToday += vToday || 0;
            totYest  += vYest  || 0;
            tot7d    += v7d    || 0;
            tot30d   += v30d   || 0;
            tot90d   += v90d   || 0;
            totPo    += vPoQty || 0;

            var poColor  = vPoQty === null ? 'var(--md-outline)' : (vPoQty === 0 ? 'var(--md-on-surface-variant)' : 'var(--md-primary)');
            var poWeight = vPoQty > 0 ? '500' : '400';
            var poStyle  = 'padding:9px 0 9px 16px;text-align:right;font-size:13px;font-weight:' + poWeight + ';color:' + poColor + sepLeft;

            var hppStyle = 'padding:9px 0 9px 12px;text-align:right;font-size:12px;color:' + (vHpp > 0 ? 'var(--md-on-surface-variant)' : 'var(--md-outline)');
            var hppText  = vHpp > 0 ? 'Rp ' + vHpp.toLocaleString('id') : '–';

            return '<tr style="' + rowStyle + '">'
                + '<td style="font-size:13px;padding:9px 0;color:var(--md-on-surface)">' + vSku + rankBadge(vSku, rank1, rank2, rank3) + '</td>'
                + '<td style="padding:9px 0;text-align:right"><span class="md-chip ' + chipClass(vQty) + '" style="font-size:12px">' + vQty.toLocaleString('id') + '</span></td>'
                + '<td style="' + hppStyle + '">' + hppText + '</td>'
                + salesCellHtml(vToday, true) + salesCellHtml(vYest, false) + salesCellHtml(v7d, false) + salesCellHtml(v30d, false)
                + salesCellHtml(v90d, false).replace(fmtNum(v90d), fmtCompact(v90d))
                + '<td style="' + poStyle + '">' + fmtCompact(vPoQty) + '</td>'
                + '</tr>';
        }).join('');

        return '<table style="width:100%;border-collapse:collapse">'
            + colgroup
            + '<thead><tr>' + headers + '</tr></thead>'
            + '<tbody>' + rows + '</tbody>'
            + '<tfoot>'
            + '<tr style="background:var(--md-surface-container-low);border-top:2px solid var(--md-outline-variant)">'
            + '<td style="font-size:11px;font-weight:600;letter-spacing:.6px;text-transform:uppercase;padding:9px 0;color:var(--md-on-surface-variant)">Total</td>'
            + '<td style="padding:9px 0;text-align:right"><span class="md-chip ' + chipClass(Math.min.apply(null, variants.map(function(v){return parseInt(v.qty)||0;}))) + '" style="font-size:12px;font-weight:600">' + totStock.toLocaleString('id') + '</span></td>'
            + '<td style="padding:9px 0 9px 12px;text-align:right;font-size:12px;color:var(--md-outline)">–</td>'
            + (function() {
                var totSalesCells = '';
                var totals = [totToday, totYest, tot7d, tot30d, tot90d];
                totals.forEach(function(t, i) {
                    var color  = t === 0 ? 'var(--md-error)' : 'var(--md-on-surface)';
                    var sep    = i === 0 ? sepLeft + ';padding-left:16px' : '';
                    totSalesCells += '<td style="padding:9px 0 9px 12px;text-align:right;font-size:13px;font-weight:600;color:' + color + sep + '">' + t.toLocaleString('id') + '</td>';
                });
                return totSalesCells;
            })()
            + '<td style="padding:9px 0 9px 16px;text-align:right;font-size:13px;font-weight:600;color:' + (totPo === 0 ? 'var(--md-on-surface-variant)' : 'var(--md-primary)') + sepLeft + '">' + totPo.toLocaleString('id') + '</td>'
            + '</tr>'
            + '</tfoot>'
            + '</table>';
    }

    /**
     * storeSales shape: { [store_id]: { name: '...', sales: { today: {sku:qty}, ... } } }
     * Stok/HPP/PO tidak dipecah per toko (levelnya gudang, bukan channel penjualan) —
     * hanya kolom penjualan yang di-breakdown per toko. Lihat DailySalesQueryService.
     */
    function renderPerTokoTable(variants, storeSales, rank1, rank2, rank3) {
        var storeIds = Object.keys(storeSales || {});
        if (storeIds.length === 0) {
            return '<p style="font-size:12px;color:var(--md-outline);padding:12px 0">Tidak ada data penjualan per toko pada rentang ini.</p>';
        }
        storeIds.sort(function (a, b) { return storeSales[a].name.localeCompare(storeSales[b].name); });

        var headers = ['SKU', 'Toko', 'Hari Ini', 'Kemarin', '7 Hari', '30 Hari', '90 Hari'].map(function (h, i) {
            var extra = (i >= 2 ? ';text-align:right' : '');
            if (i === 2) extra += sepLeft + ';padding-left:16px';
            else if (i > 2) extra += ';padding-left:12px';
            return '<th style="' + thBase + extra + '">' + h + '</th>';
        }).join('');

        var colgroup = '<colgroup>'
            + '<col style="min-width:130px"><col style="min-width:120px">'
            + '<col style="min-width:56px"><col style="min-width:56px"><col style="min-width:52px">'
            + '<col style="min-width:52px"><col style="min-width:60px">'
            + '</colgroup>';

        var totals = { today: 0, yesterday: 0, '7d': 0, '30d': 0, '90d': 0 };
        var rows = '';
        variants.forEach(function (v, vIdx) {
            var vSku = v.sku;
            storeIds.forEach(function (storeId, sIdx) {
                var store = storeSales[storeId];
                var getVal = function (period) {
                    var p = (store.sales && store.sales[period]) || {};
                    var val = Object.prototype.hasOwnProperty.call(p, vSku) ? parseInt(p[vSku]) : 0;
                    totals[period] += val;
                    return val;
                };
                var vToday = getVal('today'), vYest = getVal('yesterday'), v7d = getVal('7d'), v30d = getVal('30d'), v90d = getVal('90d');
                var rowStyle = (sIdx > 0 || vIdx > 0) ? 'border-top:1px solid var(--md-outline-variant);' : '';
                if (vIdx % 2 === 1) rowStyle += 'background:rgba(0,0,0,.015);';

                rows += '<tr style="' + rowStyle + '">'
                    + (sIdx === 0
                        ? '<td rowspan="' + storeIds.length + '" style="vertical-align:top;font-size:13px;padding:9px 0;color:var(--md-on-surface);font-weight:500">' + vSku + rankBadge(vSku, rank1, rank2, rank3) + '</td>'
                        : '')
                    + '<td style="padding:9px 0;font-size:12px;color:var(--md-on-surface-variant)">' + store.name + '</td>'
                    + salesCellHtml(vToday, true) + salesCellHtml(vYest, false) + salesCellHtml(v7d, false)
                    + salesCellHtml(v30d, false) + salesCellHtml(v90d, false).replace(fmtNum(v90d), fmtCompact(v90d))
                    + '</tr>';
            });
        });

        var totRow = '<tr style="background:var(--md-surface-container-low);border-top:2px solid var(--md-outline-variant)">'
            + '<td colspan="2" style="font-size:11px;font-weight:600;letter-spacing:.6px;text-transform:uppercase;padding:9px 0;color:var(--md-on-surface-variant)">Total</td>';
        ['today', 'yesterday', '7d', '30d', '90d'].forEach(function (period, i) {
            var t = totals[period];
            var color = t === 0 ? 'var(--md-error)' : 'var(--md-on-surface)';
            var sep = i === 0 ? sepLeft + ';padding-left:16px' : '';
            totRow += '<td style="padding:9px 0 9px 12px;text-align:right;font-size:13px;font-weight:600;color:' + color + sep + '">' + t.toLocaleString('id') + '</td>';
        });
        totRow += '</tr>';

        return '<table style="width:100%;border-collapse:collapse">'
            + colgroup
            + '<thead><tr>' + headers + '</tr></thead>'
            + '<tbody>' + rows + '</tbody>'
            + '<tfoot>' + totRow + '</tfoot>'
            + '</table>';
    }

    function renderSkuDetail(variants, sales, po, adId, storeSales) {
        if (!variants || variants.length === 0) {
            return '<span style="font-size:12px;color:var(--md-outline)">Tidak ada data SKU dari ERP</span>';
        }
        var sales90d = sales['90d'] || {};
        var sorted90d = Object.entries(sales90d).filter(function (e) { return e[1] > 0; }).sort(function (a, b) { return b[1] - a[1]; });
        var rank1 = sorted90d[0] ? sorted90d[0][0] : null;
        var rank2 = sorted90d[1] ? sorted90d[1][0] : null;
        var rank3 = sorted90d[2] ? sorted90d[2][0] : null;

        var totalHtml   = renderTotalTable(variants, sales, po, rank1, rank2, rank3);
        var perTokoHtml = renderPerTokoTable(variants, storeSales, rank1, rank2, rank3);

        return '<button type="button" class="sku-toggle-btn d-inline-flex align-items-center gap-1"'
            + ' style="background:none;border:none;padding:0;cursor:pointer;font-size:12px;font-weight:500;color:var(--md-on-surface-variant)"'
            + ' data-bs-toggle="collapse" data-bs-target="#sku-' + adId + '" aria-expanded="false">'
            + '<i class="bi bi-chevron-right sku-chev" style="font-size:10px;transition:transform .2s"></i>'
            + ' ' + variants.length + ' SKU</button>'
            + '<div class="collapse mt-2" id="sku-' + adId + '">'
            + '<div style="border-left:2px solid var(--md-outline-variant);padding-left:14px">'
            + '<div class="d-inline-flex gap-1 mb-2 sales-mode-toggle" data-ad-id="' + adId + '">'
            + '<button type="button" class="md-choice-chip checked" data-mode="total">Total</button>'
            + '<button type="button" class="md-choice-chip" data-mode="toko">Per Toko</button>'
            + '</div>'
            + '<div class="sales-view-total" data-ad-id="' + adId + '" style="overflow-x:auto;-webkit-overflow-scrolling:touch">' + totalHtml + '</div>'
            + '<div class="sales-view-toko" data-ad-id="' + adId + '" style="display:none;overflow-x:auto;-webkit-overflow-scrolling:touch">' + perTokoHtml + '</div>'
            + '</div></div>';
    }

    function bindSalesModeToggles() {
        document.querySelectorAll('.sales-mode-toggle').forEach(function (group) {
            if (group.dataset.bound) return;
            group.dataset.bound = '1';
            var adId = group.dataset.adId;
            group.querySelectorAll('.md-choice-chip').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    group.querySelectorAll('.md-choice-chip').forEach(function (b) { b.classList.toggle('checked', b === btn); });
                    var mode = btn.dataset.mode;
                    var totalView = document.querySelector('.sales-view-total[data-ad-id="' + adId + '"]');
                    var tokoView  = document.querySelector('.sales-view-toko[data-ad-id="' + adId + '"]');
                    if (totalView) totalView.style.display = mode === 'total' ? '' : 'none';
                    if (tokoView)  tokoView.style.display  = mode === 'toko'  ? '' : 'none';
                });
            });
        });
    }

    function bindSkuToggles() {
        document.querySelectorAll('.sku-toggle-btn').forEach(function (btn) {
            if (btn.dataset.bound) return;
            btn.dataset.bound = '1';
            var target = document.querySelector(btn.dataset.bsTarget);
            var chev   = btn.querySelector('.sku-chev');
            if (!target || !chev) return;
            target.addEventListener('show.bs.collapse', function () { chev.style.transform = 'rotate(90deg)'; });
            target.addEventListener('hide.bs.collapse', function () { chev.style.transform = 'rotate(0deg)'; });
        });
    }

    var url = "{{ route('product-ads.erp-data') }}" + window.location.search;

    var erpAbortCtrl = new AbortController();
    var erpTimeout   = setTimeout(function () { erpAbortCtrl.abort(); }, 10000);

    fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }, signal: erpAbortCtrl.signal })
        .then(function (r) { return r.ok ? r.json() : Promise.reject(r.status); })
        .then(function (data) {
            clearTimeout(erpTimeout);
            Object.keys(data).forEach(function (adId) {
                var d        = data[adId];
                var stockCell = document.querySelector('.erp-stock[data-ad-id="' + adId + '"]');
                if (stockCell) stockCell.innerHTML = renderStockCell(d.variants);
                var detailCell = document.querySelector('.erp-detail[data-ad-id="' + adId + '"]');
                if (detailCell) detailCell.innerHTML = renderSkuDetail(d.variants, d.sales || {}, d.po || {}, adId, d.storeSales || {});
            });
            bindSkuToggles();
            bindSalesModeToggles();
        })
        .catch(function () {
            clearTimeout(erpTimeout);
            document.querySelectorAll('.erp-stock[data-ad-id]').forEach(function (c) {
                c.innerHTML = '<span style="color:var(--md-outline)">–</span>';
            });
            document.querySelectorAll('.erp-detail[data-ad-id]').forEach(function (c) {
                c.innerHTML = '<span style="font-size:12px;color:var(--md-error)">Data ERP tidak tersedia</span>';
            });
        });
});
</script>
@endpush
