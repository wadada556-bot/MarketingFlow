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

    @if($products->isEmpty())
        <div class="md-card text-center py-5 px-4" style="border-style:dashed">
            <i class="bi bi-inbox d-block mb-3" style="font-size:2.8rem;color:var(--md-outline)"></i>
            <p class="mb-1" style="font-size:16px;font-weight:500;color:var(--md-on-surface)">Tidak Ada Data</p>
            <p class="mb-0" style="font-size:14px;color:var(--md-on-surface-variant)">Coba sesuaikan filter atau tambahkan produk baru.</p>
        </div>
    @else
        <form id="formBulkDelete" action="{{ route('products.bulk-destroy') }}" method="POST">
            @csrf
            @method('DELETE')
            @include('products.partials.table', ['products' => $products])
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
