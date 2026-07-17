@php
    /**
     * Reusable stock-alert group.
     * Expects: $alert (array), $uid (unique string used for toggle ids)
     */
    $isDanger      = $alert['status'] === 'danger';
    $alertCnt      = $alert['alert_count'];
    $dangerCnt     = $alert['danger_count'];
    $hasMissingPo  = !empty(array_filter($alert['variants'], fn ($v) => ($v['rank_90d'] ?? null) !== null && ($v['po_qty'] ?? null) === null));
@endphp
<div class="stock-group {{ $alert['has_urgent_variant'] ? 'urgent-group' : '' }}"
     data-urgent="{{ $alert['has_urgent_variant'] ? '1' : '0' }}"
     data-status="{{ $alert['status'] }}"
     data-missing-po="{{ $hasMissingPo ? '1' : '0' }}">

    <div class="stock-group-header {{ $isDanger ? 'danger-header' : 'warning-header' }}"
         onclick="toggleStock('{{ $uid }}')">
        <i class="bi {{ $isDanger ? 'bi-x-circle-fill' : 'bi-exclamation-triangle-fill' }}"
           style="font-size:15px;flex-shrink:0;margin-top:1px;color:{{ $isDanger ? 'var(--md-error)' : 'var(--md-warning)' }}"></i>
        <div style="min-width:0;flex:1;overflow:hidden">
            <span style="font-size:13px;font-weight:600;color:var(--md-on-surface);
                         display:flex;align-items:center;gap:6px;overflow:hidden;">
                <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $alert['parent_sku'] }}</span>
                @if($alert['has_urgent_variant'])
                    <span title="Best seller stok menipis, prioritas restok"
                          style="flex-shrink:0;display:inline-flex;align-items:center;gap:3px;font-size:9px;font-weight:800;letter-spacing:.4px;
                                 background:var(--md-error);color:var(--md-on-error);padding:2px 6px;border-radius:var(--md-shape-full);text-transform:uppercase;">
                        ⚡ Urgent
                    </span>
                @endif
            </span>
            <div style="display:flex;align-items:center;gap:4px;flex-wrap:wrap;margin-top:3px">
                @php
                    $storeShow  = array_slice($alert['stores'], 0, 2);
                    $storeExtra = count($alert['stores']) - 2;
                @endphp
                <span style="font-size:11px;color:var(--md-on-surface-variant);min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                    {{ implode(' · ', $storeShow) }}@if($storeExtra > 0) <span style="color:var(--md-outline)">+{{ $storeExtra }}</span>@endif
                    · {{ $alertCnt }} varian
                    @if($dangerCnt > 0)
                        · <span style="color:var(--md-error);font-weight:600">{{ $dangerCnt }} kritis</span>
                    @endif
                </span>
            </div>
        </div>
        <div style="display:flex;align-items:center;gap:6px;flex-shrink:0">
            <span class="md-chip {{ $isDanger ? 'error' : 'warning' }}">
                {{ $isDanger ? 'Kritis' : 'Menipis' }}
            </span>
            <i id="stock-chevron-{{ $uid }}" class="bi bi-chevron-down"
               style="font-size:12px;transition:transform .2s;color:var(--md-on-surface-variant)"></i>
        </div>
    </div>
    <div id="stock-body-{{ $uid }}" class="stock-group-body">
        <div style="display:grid;grid-template-columns:max-content max-content max-content;row-gap:4px;width:fit-content;max-width:100%">
            @foreach($alert['variants'] as $variant)
                @php
                    $vDanger      = $variant['status'] === 'danger';
                    $vWarn        = $variant['status'] === 'warning';
                    $vRank        = $variant['rank_90d'] ?? null;
                    $vPoQty       = $variant['po_qty'] ?? null;
                    $isBestSeller = $vRank !== null;
                    $missingPo    = $isBestSeller && $vPoQty === null;
                    $rowBg        = $missingPo ? 'rgba(255,222,170,.35)' : 'var(--md-surface-container-low)';
                    $rowBorder    = $missingPo ? '1px solid rgba(122,88,0,.25)' : '1px solid transparent';
                @endphp
                {{-- Kolom 1: SKU + badges --}}
                <div style="
                    display:flex;align-items:center;gap:5px;
                    padding:6px 8px 6px 10px;
                    background:{{ $rowBg }};
                    border-top:{{ $rowBorder }};border-bottom:{{ $rowBorder }};border-left:{{ $rowBorder }};
                    border-radius:var(--md-shape-xs) 0 0 var(--md-shape-xs);
                ">
                    <span style="font-size:12px;font-family:monospace;color:var(--md-on-surface);white-space:nowrap;">{{ $variant['sku'] }}</span>
                    @if($vRank === 1)
                        <span style="flex-shrink:0;font-size:10px;font-weight:700;background:#FFD700;color:#5a4000;padding:1px 5px;border-radius:20px;white-space:nowrap">🥇 #1</span>
                    @elseif($vRank === 2)
                        <span style="flex-shrink:0;font-size:10px;font-weight:700;background:#C0C0C0;color:#3a3a3a;padding:1px 5px;border-radius:20px;white-space:nowrap">🥈 #2</span>
                    @elseif($vRank === 3)
                        <span style="flex-shrink:0;font-size:10px;font-weight:700;background:#CD7F32;color:#fff;padding:1px 5px;border-radius:20px;white-space:nowrap">🥉 #3</span>
                    @endif
                    @if($missingPo)
                        <span style="flex-shrink:0;font-size:10px;font-weight:700;letter-spacing:.3px;
                                     background:var(--md-warning-container);color:var(--md-on-warning-container);
                                     padding:1px 6px;border-radius:20px;white-space:nowrap;text-transform:uppercase">
                            ⚠ Belum PO
                        </span>
                    @endif
                </div>
                {{-- Kolom 2: PO --}}
                <div style="
                    display:flex;align-items:center;justify-content:flex-end;
                    padding:6px 8px;
                    background:{{ $rowBg }};
                    border-top:{{ $rowBorder }};border-bottom:{{ $rowBorder }};
                ">
                    @if($vPoQty !== null)
                        <span style="font-size:11px;color:var(--md-primary);font-weight:600;white-space:nowrap">
                            PO {{ number_format($vPoQty) }}
                        </span>
                    @endif
                </div>
                {{-- Kolom 3: qty --}}
                <div style="
                    display:flex;align-items:center;
                    padding:6px 10px 6px 8px;
                    background:{{ $rowBg }};
                    border-top:{{ $rowBorder }};border-bottom:{{ $rowBorder }};border-right:{{ $rowBorder }};
                    border-radius:0 var(--md-shape-xs) var(--md-shape-xs) 0;
                ">
                    <span class="md-chip {{ $vDanger ? 'error' : ($vWarn ? 'warning' : 'primary') }}" style="font-size:11px;min-width:52px;text-align:center">
                        {{ number_format($variant['qty']) }} pcs
                    </span>
                </div>
            @endforeach
        </div>
    </div>
</div>
