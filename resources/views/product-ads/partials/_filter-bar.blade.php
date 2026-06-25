<div class="md-filter-bar">
    <form id="filterForm" action="{{ route('product-ads.index') }}" method="GET">

        @if(request()->has('testing_status'))
            <input type="hidden" name="testing_status" value="{{ request('testing_status') }}">
        @endif

        <div class="row g-3 align-items-end">

            <div class="col-12 col-sm-6 col-md-3 col-lg-2">
                <label for="filter_product">Produk</label>
                <select class="form-select searchable-select" id="filter_product" name="product"
                        placeholder="Cari produk...">
                    <option value="">Semua Produk</option>
                    @foreach($products as $product)
                        <option value="{{ $product->id }}"
                                {{ request('product') == $product->id ? 'selected' : '' }}>
                            {{ $product->parent_sku }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-12 col-sm-6 col-md-3 col-lg-2">
                <label for="filter_category">Kategori</label>
                <select class="form-select" id="filter_category" name="category">
                    <option value="">Semua Kategori</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}"
                                {{ request('category') == $category->id ? 'selected' : '' }}>
                            {{ $category->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-12 col-sm-6 col-md-3 col-lg-3">
                <label for="filter_store">Toko</label>
                <select class="form-select" id="filter_store" name="store[]" multiple
                        placeholder="Semua Toko...">
                    @foreach($stores as $store)
                        <option value="{{ $store->id }}"
                                {{ in_array($store->id, request('store', [])) ? 'selected' : '' }}>
                            {{ $store->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-12 col-sm-6 col-md-3 col-lg-2">
                <label for="filter_status">Status</label>
                <select class="form-select" id="filter_status" name="status">
                    <option value="">Semua Status</option>
                    <option value="active"    {{ request('status') == 'active'    ? 'selected' : '' }}>Aktif</option>
                    <option value="stopped"   {{ request('status') == 'stopped'   ? 'selected' : '' }}>Dihentikan</option>
                    <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>Selesai</option>
                </select>
            </div>

            <div class="col-12 col-md-auto d-flex gap-2 ms-md-auto">
                <a href="{{ route('product-ads.index', request()->has('testing_status') ? ['testing_status' => request('testing_status')] : []) }}"
                   class="btn-md-outlined" style="padding:9px 20px">
                    Reset
                </a>
            </div>

        </div>
    </form>
</div>
