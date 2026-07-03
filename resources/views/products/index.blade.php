@extends('components.layouts.app')

@section('title', 'Products')

@push('styles')
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
    <style>
        /* Override TomSelect to match MD3 */
        .ts-wrapper .ts-control {
            border-radius: var(--md-shape-xs) !important;
            border: 1px solid var(--md-outline) !important;
            background: var(--md-surface) !important;
            box-shadow: none !important;
            font-size: 13.5px !important;
            padding: 7px 10px !important;
        }
        .ts-wrapper.focus .ts-control {
            border-color: var(--md-primary) !important;
            border-width: 2px !important;
            box-shadow: none !important;
        }
        .ts-dropdown {
            border-radius: var(--md-shape-sm) !important;
            border: 1px solid var(--md-outline-variant) !important;
            box-shadow: var(--md-elev-2) !important;
        }
        .pagination-wrapper nav > div:first-child { display: none !important; }
        .pagination-wrapper nav { margin-bottom: 0 !important; }
    </style>
@endpush

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
    <script src="{{ asset('js/product-ads.js') }}?v={{ filemtime(public_path('js/product-ads.js')) }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            @if($errors->any())
                var myOffcanvas = document.getElementById('offcanvasCreateProduct');
                if(myOffcanvas) { new bootstrap.Offcanvas(myOffcanvas).show(); }
            @endif

            const selectAll      = document.getElementById('select_all');
            const checkboxes     = document.querySelectorAll('.sub_chk');
            const bulkDeleteBtn  = document.getElementById('btn_bulk_delete');
            const selectCount    = document.getElementById('select_count');

            if (selectAll) {
                selectAll.addEventListener('change', function () {
                    checkboxes.forEach(cb => cb.checked = this.checked);
                    toggleBulkDeleteButton();
                });
            }
            checkboxes.forEach(cb => {
                cb.addEventListener('change', function () {
                    if (!this.checked) selectAll.checked = false;
                    else if (document.querySelectorAll('.sub_chk:checked').length === checkboxes.length)
                        selectAll.checked = true;
                    toggleBulkDeleteButton();
                });
            });
            function toggleBulkDeleteButton() {
                const n = document.querySelectorAll('.sub_chk:checked').length;
                bulkDeleteBtn.classList.toggle('d-none', n === 0);
                selectCount.textContent = n;
            }

            const confirmBulkDeleteBtn = document.getElementById('executeBulkDelete');
            if (confirmBulkDeleteBtn) {
                confirmBulkDeleteBtn.addEventListener('click', function () {
                    document.getElementById('formBulkDelete').submit();
                });
            }

            // ── Autocomplete ──────────────────────────────────────────────
            const searchInput  = document.getElementById('productSearch');
            const suggestionEl = document.getElementById('searchSuggestions');
            const suggestUrl   = '{{ route('products.suggest') }}';
            let debounceTimer  = null;
            let activeIndex    = -1;

            function renderSuggestions(items) {
                suggestionEl.innerHTML = '';
                activeIndex = -1;

                if (!items.length) { suggestionEl.style.display = 'none'; return; }

                items.forEach((item, i) => {
                    const li = document.createElement('li');
                    li.dataset.index = i;
                    li.style.cssText = 'padding:8px 14px;cursor:pointer;display:flex;align-items:center;justify-content:space-between;gap:8px;font-size:13.5px;color:var(--md-on-surface)';
                    li.innerHTML = `<span>${item.sku}</span>
                        <span style="font-size:12px;color:var(--md-on-surface-variant);flex-shrink:0">${item.category}</span>`;

                    li.addEventListener('mouseenter', () => setActive(i));
                    li.addEventListener('mouseleave', () => clearActive());
                    li.addEventListener('mousedown', (e) => {
                        e.preventDefault();
                        searchInput.value = item.sku;
                        document.getElementById('searchForm').submit();
                    });

                    suggestionEl.appendChild(li);
                });

                suggestionEl.style.display = 'block';
            }

            function setActive(index) {
                const items = suggestionEl.querySelectorAll('li');
                items.forEach(el => el.style.background = '');
                activeIndex = index;
                if (items[index]) items[index].style.background = 'var(--md-surface-container-low)';
            }

            function clearActive() {
                const items = suggestionEl.querySelectorAll('li');
                items.forEach(el => el.style.background = '');
                activeIndex = -1;
            }

            if (searchInput) {
                searchInput.addEventListener('input', function () {
                    clearTimeout(debounceTimer);
                    const q = this.value.trim();
                    if (!q) { suggestionEl.style.display = 'none'; return; }

                    debounceTimer = setTimeout(() => {
                        fetch(`${suggestUrl}?q=${encodeURIComponent(q)}`)
                            .then(r => r.json())
                            .then(renderSuggestions);
                    }, 250);
                });

                searchInput.addEventListener('keydown', function (e) {
                    const items = suggestionEl.querySelectorAll('li');
                    if (!items.length || suggestionEl.style.display === 'none') return;

                    if (e.key === 'ArrowDown') {
                        e.preventDefault();
                        setActive(Math.min(activeIndex + 1, items.length - 1));
                    } else if (e.key === 'ArrowUp') {
                        e.preventDefault();
                        setActive(Math.max(activeIndex - 1, 0));
                    } else if (e.key === 'Enter' && activeIndex >= 0) {
                        e.preventDefault();
                        searchInput.value = items[activeIndex].querySelector('span').textContent;
                        document.getElementById('searchForm').submit();
                    } else if (e.key === 'Escape') {
                        suggestionEl.style.display = 'none';
                    }
                });

                document.addEventListener('click', function (e) {
                    if (!searchInput.contains(e.target) && !suggestionEl.contains(e.target)) {
                        suggestionEl.style.display = 'none';
                    }
                });
            }
        });
    </script>
@endpush

@section('content')

    <div class="md-page-header">
        <div>
            <h1>Products</h1>
            <p class="subtitle">Kelola data produk dan SKU</p>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <button type="button" id="btn_bulk_delete"
                    class="btn-md-error-tonal d-none"
                    data-bs-toggle="modal" data-bs-target="#bulkDeleteConfirmModal">
                <i class="bi bi-trash3"></i>
                Hapus Terpilih (<span id="select_count">0</span>)
            </button>
            <button class="btn-md-filled" type="button"
                    data-bs-toggle="offcanvas"
                    data-bs-target="#offcanvasCreateProduct"
                    aria-controls="offcanvasCreateProduct">
                <i class="bi bi-plus-lg"></i> Tambah Produk
            </button>
        </div>
    </div>

    @include('components.alert')

    <form method="GET" action="{{ route('products.index') }}" class="mb-3 d-flex flex-wrap align-items-center gap-2" id="searchForm" autocomplete="off">
        <div style="position:relative;max-width:360px;flex:1 1 260px">
            <div class="input-group">
                <span class="input-group-text"
                      style="background:var(--md-surface);border-color:var(--md-outline);border-radius:var(--md-shape-xs) 0 0 var(--md-shape-xs)">
                    <i class="bi bi-search" style="color:var(--md-on-surface-variant);font-size:14px"></i>
                </span>
                <input type="text" name="search" id="productSearch"
                       value="{{ $search ?? '' }}"
                       class="form-control"
                       placeholder="Cari SKU atau kategori…"
                       style="border-color:var(--md-outline);font-size:13.5px;background:var(--md-surface);color:var(--md-on-surface)">
                @if(!empty($search))
                    <a href="{{ route('products.index', ['store_id' => $storeId]) }}"
                       class="input-group-text"
                       style="background:var(--md-surface);border-color:var(--md-outline);border-radius:0 var(--md-shape-xs) var(--md-shape-xs) 0;color:var(--md-on-surface-variant);text-decoration:none"
                       title="Hapus pencarian">
                        <i class="bi bi-x-lg" style="font-size:12px"></i>
                    </a>
                @endif
            </div>

            {{-- Autocomplete dropdown --}}
            <ul id="searchSuggestions"
                style="display:none;position:absolute;top:100%;left:0;right:0;z-index:1055;
                       list-style:none;margin:4px 0 0;padding:4px 0;
                       background:var(--md-surface);
                       border:1px solid var(--md-outline-variant);
                       border-radius:var(--md-shape-sm);
                       box-shadow:var(--md-elev-2);
                       max-height:260px;overflow-y:auto">
            </ul>
        </div>

        {{-- Pemilih toko: harga jual ditampilkan per toko --}}
        @if($stores->isNotEmpty())
            <div style="min-width:200px">
                <select name="store_id" class="form-select" onchange="this.form.submit()"
                        title="Pilih toko untuk melihat harga"
                        style="border-color:var(--md-outline);font-size:13.5px;background:var(--md-surface);color:var(--md-on-surface)">
                    @foreach($stores as $store)
                        <option value="{{ $store->id }}" @selected($storeId == $store->id)>
                            {{ ucwords($store->name) }}
                        </option>
                    @endforeach
                </select>
            </div>
        @endif
    </form>

    @if($products->isEmpty())
        <div class="md-card text-center py-5 px-4" style="border-style:dashed">
            <i class="bi bi-inbox d-block mb-3" style="font-size:2.8rem;color:var(--md-outline)"></i>
            <p class="mb-1" style="font-size:16px;font-weight:500;color:var(--md-on-surface)">Tidak Ada Data</p>
            <p class="mb-0" style="font-size:14px;color:var(--md-on-surface-variant)">
                @if(!empty($search))
                    Tidak ada produk yang cocok dengan "<strong>{{ $search }}</strong>".
                @else
                    Coba sesuaikan filter atau tambahkan produk baru.
                @endif
            </p>
        </div>
    @else
        <form id="formBulkDelete" action="{{ route('products.bulk-destroy') }}" method="POST">
            @csrf
            @method('DELETE')
            @include('products.partials.table', ['products' => $products, 'prices' => $prices])
        </form>

        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-center mt-4 gap-2">
            <p class="mb-0" style="font-size:13px;color:var(--md-on-surface-variant)">
                Menampilkan
                <strong style="color:var(--md-on-surface)">{{ $products->firstItem() ?? 0 }}</strong>
                –
                <strong style="color:var(--md-on-surface)">{{ $products->lastItem() ?? 0 }}</strong>
                dari
                <strong style="color:var(--md-on-surface)">{{ $products->total() }}</strong>
                data
            </p>
            <div class="pagination-wrapper">
                {{ $products->withQueryString()->links() }}
            </div>
        </div>
    @endif

    @include('products.partials.create-offcanvas')
    @include('products.partials._edit-product-modal')
    @include('products.partials.delete-modal')

@endsection
