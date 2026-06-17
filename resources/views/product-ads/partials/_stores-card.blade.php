<div class="md-card" style="overflow:hidden">

    {{-- Card header --}}
    <div style="background:var(--md-surface-container-low);padding:18px 24px;border-bottom:1px solid var(--md-outline-variant)">
        <div class="d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
                <span style="width:32px;height:32px;border-radius:50%;background:var(--md-secondary-container);display:inline-flex;align-items:center;justify-content:center;color:var(--md-on-secondary-container);font-size:15px">
                    <i class="bi bi-shop"></i>
                </span>
                <p class="mb-0" style="font-size:14px;font-weight:500;color:var(--md-on-surface)">Target Toko</p>
            </div>
            <span class="md-chip surface">
                {{ $stores->count() }} {{ $stores->count() === 1 ? 'Toko' : 'Toko' }}
            </span>
        </div>
    </div>

    <div style="padding:24px">
        @if($stores->isNotEmpty())
            <div class="d-flex flex-wrap gap-2">
                @foreach($stores as $store)
                    <span class="md-store-chip">
                        <i class="bi bi-storefront" style="font-size:14px;color:var(--md-on-surface-variant)"></i>
                        {{ $store->name }}
                    </span>
                @endforeach
            </div>
        @else
            <div style="text-align:center;padding:24px;background:var(--md-surface-container-low);border-radius:var(--md-shape-md)">
                <i class="bi bi-shop-window d-block mb-2" style="font-size:1.8rem;color:var(--md-outline)"></i>
                <p class="mb-0" style="font-size:13px;color:var(--md-on-surface-variant)">Belum ada target toko.</p>
            </div>
        @endif
    </div>

</div>
