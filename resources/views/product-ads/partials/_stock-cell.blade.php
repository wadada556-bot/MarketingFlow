@php
    /** @var array $variants  list of ['sku' => string, 'qty' => int] */
    $variants  = $variants ?? [];
    $skuCount  = count($variants);
    $totalStock = array_sum(array_column($variants, 'qty'));
    $minQty    = $skuCount ? min(array_column($variants, 'qty')) : null;

    $stockChipClass = fn (int $qty) => $qty <= 100 ? 'error' : ($qty <= 300 ? 'warning' : 'primary');
@endphp
@if($skuCount > 0)
    <span class="md-chip {{ $stockChipClass($minQty) }}" style="font-size:13px;font-weight:500">
        {{ number_format($totalStock) }}
    </span>
@else
    <span style="color:var(--md-outline)">–</span>
@endif
