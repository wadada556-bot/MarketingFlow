@php
    /**
     * @var array $variants  list of ['sku' => string, 'qty' => int]
     * @var array $skuSales  ['today'=>[], 'yesterday'=>[], '7d'=>[], '30d'=>[]] keyed by variant sku
     * @var array $skuPo     [variantSku => qty_ordered] from purchase.po-inbound_current
     * @var mixed $adId
     */
    $variants = $variants ?? [];
    $skuSales = $skuSales ?? ['today' => [], 'yesterday' => [], '7d' => [], '30d' => [], '90d' => []];
    $skuPo    = $skuPo    ?? [];
    $skuCount = count($variants);

    $stockChipClass = fn (int $qty) => $qty <= 100 ? 'error' : ($qty <= 300 ? 'warning' : 'primary');

    $sorted90d   = collect($skuSales['90d'] ?? [])->filter(fn ($v) => $v > 0)->sortDesc();
    $bestSeller1 = $sorted90d->keys()->get(0);
    $bestSeller2 = $sorted90d->keys()->get(1);
    $bestSeller3 = $sorted90d->keys()->get(2);
@endphp
@if($skuCount > 0)
    <button type="button"
            class="sku-toggle-btn d-inline-flex align-items-center gap-1"
            style="background:none;border:none;padding:0;cursor:pointer;font-size:12px;font-weight:500;color:var(--md-on-surface-variant)"
            data-bs-toggle="collapse"
            data-bs-target="#sku-{{ $adId }}"
            aria-expanded="false">
        <i class="bi bi-chevron-right sku-chev" style="font-size:10px;transition:transform .2s"></i>
        {{ $skuCount }} SKU
    </button>
    <div class="collapse mt-2" id="sku-{{ $adId }}">
        <div style="border-left:2px solid var(--md-outline-variant);padding-left:14px;max-width:560px">
            <table style="width:100%;border-collapse:collapse">
                <thead>
                    <tr>
                        <th style="font-size:11px;font-weight:500;letter-spacing:.5px;text-transform:uppercase;color:var(--md-on-surface-variant);padding:4px 0;border:none">SKU</th>
                        <th style="font-size:11px;font-weight:500;letter-spacing:.5px;text-transform:uppercase;color:var(--md-on-surface-variant);padding:4px 0;text-align:right;border:none">Stok</th>
                        <th style="font-size:11px;font-weight:500;letter-spacing:.5px;text-transform:uppercase;color:var(--md-on-surface-variant);padding:4px 0;text-align:right;border:none;padding-left:12px">Hari Ini</th>
                        <th style="font-size:11px;font-weight:500;letter-spacing:.5px;text-transform:uppercase;color:var(--md-on-surface-variant);padding:4px 0;text-align:right;border:none;padding-left:12px">Kemarin</th>
                        <th style="font-size:11px;font-weight:500;letter-spacing:.5px;text-transform:uppercase;color:var(--md-on-surface-variant);padding:4px 0;text-align:right;border:none;padding-left:12px">7 Hari</th>
                        <th style="font-size:11px;font-weight:500;letter-spacing:.5px;text-transform:uppercase;color:var(--md-on-surface-variant);padding:4px 0;text-align:right;border:none;padding-left:12px">30 Hari</th>
                        <th style="font-size:11px;font-weight:500;letter-spacing:.5px;text-transform:uppercase;color:var(--md-on-surface-variant);padding:4px 0;text-align:right;border:none;padding-left:12px">90 Hari</th>
                        <th style="font-size:11px;font-weight:500;letter-spacing:.5px;text-transform:uppercase;color:var(--md-on-surface-variant);padding:4px 0;text-align:right;border:none;padding-left:12px">PO</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($variants as $v)
                    @php
                        $vSku     = $v['sku'];
                        $vQty     = (int) $v['qty'];
                        $hasToday = array_key_exists($vSku, $skuSales['today']     ?? []);
                        $hasYest  = array_key_exists($vSku, $skuSales['yesterday'] ?? []);
                        $has7d    = array_key_exists($vSku, $skuSales['7d']         ?? []);
                        $has30d   = array_key_exists($vSku, $skuSales['30d']        ?? []);
                        $has90d   = array_key_exists($vSku, $skuSales['90d']        ?? []);
                        $vToday   = $hasToday ? (int) $skuSales['today'][$vSku]     : null;
                        $vYest    = $hasYest  ? (int) $skuSales['yesterday'][$vSku] : null;
                        $v7d      = $has7d    ? (int) $skuSales['7d'][$vSku]        : null;
                        $v30d     = $has30d   ? (int) $skuSales['30d'][$vSku]       : null;
                        $v90d     = $has90d   ? (int) $skuSales['90d'][$vSku]       : null;
                        $vActive  = $vToday > 0 && $vYest > 0 && $v7d > 0 && $v30d > 0;
                        $vPoQty   = array_key_exists($vSku, $skuPo) ? (int) $skuPo[$vSku] : null;
                    @endphp
                    <tr style="border-top:1px solid var(--md-outline-variant);{{ $vActive ? 'background:rgba(29,158,117,.09)' : '' }}">
                        <td style="font-size:13px;padding:6px 0;color:var(--md-on-surface)">
                            {{ $vSku }}
                            @if($vSku === $bestSeller1)
                                <span style="font-size:10px;font-weight:600;background:#FFD700;color:#5a4000;padding:1px 5px;border-radius:20px;vertical-align:middle;margin-left:3px">🥇 #1</span>
                            @elseif($vSku === $bestSeller2)
                                <span style="font-size:10px;font-weight:600;background:#C0C0C0;color:#3a3a3a;padding:1px 5px;border-radius:20px;vertical-align:middle;margin-left:3px">🥈 #2</span>
                            @elseif($vSku === $bestSeller3)
                                <span style="font-size:10px;font-weight:600;background:#CD7F32;color:#fff;padding:1px 5px;border-radius:20px;vertical-align:middle;margin-left:3px">🥉 #3</span>
                            @endif
                        </td>
                        <td style="padding:6px 0;text-align:right">
                            <span class="md-chip {{ $stockChipClass($vQty) }}" style="font-size:12px">
                                {{ number_format($vQty) }}
                            </span>
                        </td>
                        @foreach([$vToday, $vYest, $v7d, $v30d, $v90d] as $val)
                        <td style="padding:6px 0 6px 12px;text-align:right;font-size:13px;
                            font-weight:{{ $val > 0 ? '500' : '400' }};
                            color:{{ $val === null ? 'var(--md-outline)' : ($val === 0 ? 'var(--md-error)' : 'var(--md-on-surface)') }}">
                            {{ $val === null ? '–' : number_format($val) }}
                        </td>
                        @endforeach
                        <td style="padding:6px 0 6px 12px;text-align:right;font-size:13px;
                            font-weight:{{ $vPoQty > 0 ? '500' : '400' }};
                            color:{{ $vPoQty === null ? 'var(--md-outline)' : ($vPoQty === 0 ? 'var(--md-on-surface-variant)' : 'var(--md-primary)') }}">
                            {{ $vPoQty === null ? '–' : number_format($vPoQty) }}
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
