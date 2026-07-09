<div class="md-filter-bar">
    <form id="filterForm" action="{{ route('product-ads-new.index') }}" method="GET">

        {{-- Tab & sorting dipertahankan lintas filter --}}
        @if(request()->filled('testing_status'))
            <input type="hidden" name="testing_status" value="{{ request('testing_status') }}">
        @endif
        @if(request()->filled('sort'))
            <input type="hidden" name="sort" value="{{ request('sort') }}">
            <input type="hidden" name="dir" value="{{ request('dir') }}">
        @endif

        <div class="row g-3 align-items-end">

            <div class="col-12 col-sm-6 col-lg-3">
                <label for="filter_period">Periode</label>
                <select class="form-select" id="filter_period" name="period">
                    @forelse($periods as $p)
                        <option value="{{ $p->period_start }}" {{ $period == $p->period_start ? 'selected' : '' }}>
                            {{ \Carbon\Carbon::parse($p->period_start)->translatedFormat('j M') }} –
                            {{ \Carbon\Carbon::parse($p->period_end)->translatedFormat('j M Y') }}
                        </option>
                    @empty
                        <option value="">Belum ada data</option>
                    @endforelse
                </select>
            </div>

            <div class="col-12 col-sm-6 col-lg-3">
                <label for="filter_store">Toko</label>
                {{-- Disulap jadi TomSelect (chip + cari). Tanpa itu, <select multiple>
                     native jadi listbox tinggi yang merusak tinggi baris filter. --}}
                <select class="form-select" id="filter_store" name="store[]" multiple placeholder="Semua toko">
                    @foreach($stores as $store)
                        <option value="{{ $store->id }}" {{ in_array($store->id, (array) request('store', [])) ? 'selected' : '' }}>
                            {{ $store->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-12 col-sm-7 col-lg-3">
                <label for="filter_q">Cari</label>
                <input type="search" class="form-control" id="filter_q" name="q"
                       value="{{ request('q') }}" placeholder="Product ID, SKU induk/variasi, atau nama produk">
            </div>

            <div class="col-7 col-sm-5 col-lg-1">
                <label for="filter_roi" title="Baris dengan ROI di bawah nilai ini disorot merah">
                    Sorot ROI &lt;
                </label>
                <input type="number" step="0.1" min="0" class="form-control text-end" id="filter_roi"
                       name="roi_threshold" value="{{ $threshold + 0 }}" style="min-width:64px">
            </div>

            <div class="col-5 col-lg-2 d-flex gap-2 justify-content-end">
                <button type="submit" class="btn-md-tonal flex-shrink-0" style="padding:9px 18px">
                    Terapkan
                </button>
                @if(request()->hasAny(['store', 'q', 'roi_threshold']))
                    <a href="{{ route('product-ads-new.index', array_filter([
                            'period'         => request('period'),
                            'testing_status' => request('testing_status'),
                       ])) }}"
                       class="btn-md-outlined flex-shrink-0" style="padding:9px 18px">Reset</a>
                @endif
            </div>

        </div>
    </form>
</div>
