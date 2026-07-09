@php
    // $stockVariants: [['sku' => '...', 'qty' => int], ...]
    // $hppMap:        ['variantSku' => int] — lookup HPP dari sku_hpp table
    $stockVariants = collect($stockVariants ?? []);
    $hppMap        = $hppMap ?? [];
    $totalStock    = $stockVariants->sum('qty');

    $stockChipClass = function (int $qty): string {
        if ($qty <= 100) return 'error';
        if ($qty <= 300) return 'warning';
        return 'primary';
    };

    $worstChipClass = $stockVariants->isEmpty() ? 'primary'
        : $stockChipClass($stockVariants->min('qty'));

    // $variantSales: ['today' => [...], 'yesterday' => [...], '7d' => [...], '30d' => [...]]
    $variantSales  = $variantSales ?? ['today' => [], 'yesterday' => [], '7d' => [], '30d' => [], '90d' => []];
    $salesToday    = $variantSales['today']     ?? [];
    $salesYest     = $variantSales['yesterday'] ?? [];
    $sales7d       = $variantSales['7d']        ?? [];
    $sales30d      = $variantSales['30d']       ?? [];
    $sales90d      = $variantSales['90d']       ?? [];
    $totalToday    = array_sum($salesToday);
    $totalYest     = array_sum($salesYest);
    $total7d       = array_sum($sales7d);
    $total30d      = array_sum($sales30d);
    $total90d      = array_sum($sales90d);

    // $variantPo: [variantSku => qty_ordered] from purchase.po-inbound_current
    $variantPo  = $variantPo ?? [];
    $totalPo    = array_sum($variantPo);

    // Merge all SKUs from stock + sales + PO into one list
    $allSkus = $stockVariants->pluck('sku')
        ->merge(array_keys($salesToday))
        ->merge(array_keys($salesYest))
        ->merge(array_keys($sales7d))
        ->merge(array_keys($sales30d))
        ->merge(array_keys($sales90d))
        ->merge(array_keys($variantPo))
        ->unique()->values();

    // Top 2 SKUs by 30-day sales (only SKUs with qty > 0)
    $sorted90d   = collect($sales90d)->filter(fn($v) => $v > 0)->sortDesc();
    $bestSeller1 = $sorted90d->keys()->get(0);
    $bestSeller2 = $sorted90d->keys()->get(1);
    $bestSeller3 = $sorted90d->keys()->get(2);
@endphp
<div class="d-flex align-items-center justify-content-between mb-3">
    <p class="mb-0" style="font-size:11px;font-weight:500;letter-spacing:.8px;text-transform:uppercase;color:var(--md-on-surface-variant)">
        <i class="bi bi-layers me-1"></i> Stok & Penjualan Varian
    </p>
    <div class="d-flex gap-2 flex-wrap">
        <span class="md-chip {{ $worstChipClass }}" style="font-size:11px">Stok: {{ number_format($totalStock) }}</span>
        <span class="md-chip surface" style="font-size:11px">Hr: {{ number_format($totalToday) }}</span>
        <span class="md-chip surface" style="font-size:11px">Km: {{ number_format($totalYest) }}</span>
        <span class="md-chip surface" style="font-size:11px">7H: {{ number_format($total7d) }}</span>
        <span class="md-chip surface" style="font-size:11px">30H: {{ number_format($total30d) }}</span>
        <span class="md-chip surface" style="font-size:11px">90H: {{ number_format($total90d) }}</span>
        @if($totalPo > 0)
        <span class="md-chip primary" style="font-size:11px">PO: {{ number_format($totalPo) }}</span>
        @endif
    </div>
</div>

@if($allSkus->isNotEmpty())
    <div style="border-radius:var(--md-shape-md);overflow:hidden;border:1px solid var(--md-outline-variant)">
        <table style="width:100%;border-collapse:collapse">
            <thead>
                <tr style="background:var(--md-surface-container-low)">
                    @php $thStyle = 'font-size:11px;font-weight:500;letter-spacing:.5px;text-transform:uppercase;color:var(--md-on-surface-variant);padding:8px 12px;border-bottom:1px solid var(--md-outline-variant)'; @endphp
                    <th style="{{ $thStyle }}">SKU</th>
                    <th style="{{ $thStyle }};text-align:right">Stok</th>
                    <th style="{{ $thStyle }};text-align:right">PO</th>
                    <th style="{{ $thStyle }};text-align:right">HPP</th>
                    <th style="{{ $thStyle }};text-align:right">Hari Ini</th>
                    <th style="{{ $thStyle }};text-align:right">Kemarin</th>
                    <th style="{{ $thStyle }};text-align:right">7 Hari</th>
                    <th style="{{ $thStyle }};text-align:right">30 Hari</th>
                    <th style="{{ $thStyle }};text-align:right">90 Hari</th>
                </tr>
            </thead>
            <tbody>
                @foreach($allSkus as $sku)
                @php
                    $stockQty    = (int) ($stockVariants->firstWhere('sku', $sku)['qty'] ?? 0);
                    $hppVal      = (int) ($hppMap[$sku] ?? 0);
                    $hasToday    = array_key_exists($sku, $salesToday);
                    $hasYest     = array_key_exists($sku, $salesYest);
                    $has7d       = array_key_exists($sku, $sales7d);
                    $has30d      = array_key_exists($sku, $sales30d);
                    $qtyToday    = $hasToday ? (int) $salesToday[$sku] : null;
                    $qtyYest     = $hasYest  ? (int) $salesYest[$sku]  : null;
                    $qty7d       = $has7d    ? (int) $sales7d[$sku]    : null;
                    $qty30d      = $has30d   ? (int) $sales30d[$sku]   : null;
                    $has90d      = array_key_exists($sku, $sales90d);
                    $qty90d      = $has90d   ? (int) $sales90d[$sku]   : null;
                    $rowActive   = $qtyToday > 0 && $qtyYest > 0 && $qty7d > 0 && $qty30d > 0;
                    $poQty       = array_key_exists($sku, $variantPo) ? (int) $variantPo[$sku] : null;
                @endphp
                <tr style="border-top:1px solid var(--md-outline-variant);{{ $rowActive ? 'background:rgba(29,158,117,.09)' : '' }}">
                    <td style="font-size:13px;font-family:monospace;padding:8px 12px;color:var(--md-on-surface)">
                        {{ $sku }}
                        @if($sku === $bestSeller1)
                            <span style="font-family:sans-serif;font-size:10px;font-weight:600;letter-spacing:.4px;background:#FFD700;color:#5a4000;padding:2px 6px;border-radius:20px;vertical-align:middle;margin-left:4px">🥇 #1</span>
                        @elseif($sku === $bestSeller2)
                            <span style="font-family:sans-serif;font-size:10px;font-weight:600;letter-spacing:.4px;background:#C0C0C0;color:#3a3a3a;padding:2px 6px;border-radius:20px;vertical-align:middle;margin-left:4px">🥈 #2</span>
                        @elseif($sku === $bestSeller3)
                            <span style="font-family:sans-serif;font-size:10px;font-weight:600;letter-spacing:.4px;background:#CD7F32;color:#fff;padding:2px 6px;border-radius:20px;vertical-align:middle;margin-left:4px">🥉 #3</span>
                        @endif
                    </td>
                    <td style="padding:8px 12px;text-align:right">
                        @if($stockQty > 0)
                            <span class="md-chip {{ $stockChipClass($stockQty) }}" style="font-size:12px">{{ number_format($stockQty) }}</span>
                        @else
                            <span style="color:var(--md-outline);font-size:13px">–</span>
                        @endif
                    </td>
                    <td style="padding:8px 12px;text-align:right;font-size:13px;
                        font-weight:{{ $poQty > 0 ? '500' : '400' }};
                        color:{{ $poQty === null ? 'var(--md-outline)' : ($poQty === 0 ? 'var(--md-on-surface-variant)' : 'var(--md-primary)') }}">
                        {{ $poQty === null ? '–' : number_format($poQty) }}
                    </td>
                    <td style="padding:8px 12px;text-align:right;font-size:12px;
                        color:{{ $hppVal > 0 ? 'var(--md-on-surface-variant)' : 'var(--md-outline)' }}">
                        {{ $hppVal > 0 ? 'Rp '.number_format($hppVal, 0, ',', '.') : '–' }}
                    </td>
                    @foreach([[$qtyToday, 'qtyToday'], [$qtyYest, 'qtyYest'], [$qty7d, 'qty7d'], [$qty30d, 'qty30d'], [$qty90d, 'qty90d']] as [$val, $_])
                    <td style="padding:8px 12px;text-align:right;font-size:13px;
                        font-weight:{{ $val > 0 ? '500' : '400' }};
                        color:{{ $val === null ? 'var(--md-outline)' : ($val === 0 ? 'var(--md-error)' : 'var(--md-on-surface)') }}">
                        {{ $val === null ? '–' : number_format($val) }}
                    </td>
                    @endforeach
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr style="background:var(--md-surface-container-low);border-top:2px solid var(--md-outline-variant)">
                    <td style="font-size:11px;font-weight:600;letter-spacing:.6px;text-transform:uppercase;padding:8px 12px;color:var(--md-on-surface-variant)">Total</td>
                    <td style="padding:8px 12px;text-align:right">
                        <span class="md-chip {{ $worstChipClass }}" style="font-size:12px;font-weight:600">{{ number_format($totalStock) }}</span>
                    </td>
                    <td style="padding:8px 12px;text-align:right;font-size:13px;font-weight:600;
                        color:{{ $totalPo === 0 ? 'var(--md-on-surface-variant)' : 'var(--md-primary)' }}">
                        {{ number_format($totalPo) }}
                    </td>
                    <td style="padding:8px 12px;text-align:right;font-size:12px;color:var(--md-outline)">–</td>
                    @foreach([
                        [$totalToday, false],
                        [$totalYest,  false],
                        [$total7d,    false],
                        [$total30d,   false],
                        [$total90d,   false],
                    ] as [$tot, $_])
                    <td style="padding:8px 12px;text-align:right;font-size:13px;font-weight:600;
                        color:{{ $tot === 0 ? 'var(--md-error)' : 'var(--md-on-surface)' }}">
                        {{ number_format($tot) }}
                    </td>
                    @endforeach
                </tr>
            </tfoot>
        </table>
    </div>
@else
    <div style="text-align:center;padding:24px;background:var(--md-surface-container-low);border-radius:var(--md-shape-md)">
        <i class="bi bi-box d-block mb-2" style="font-size:1.6rem;color:var(--md-outline)"></i>
        <p class="mb-0" style="font-size:13px;color:var(--md-on-surface-variant)">Tidak ada data stok atau penjualan</p>
    </div>
@endif
