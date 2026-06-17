@extends('components.layouts.app')

@section('title', 'Categories')

@push('scripts')
    <script src="{{ asset('js/product-ads.js') }}?v={{ filemtime(public_path('js/product-ads.js')) }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            @if($errors->any())
                var myOffcanvas = document.getElementById('offcanvasCreateCategory');
                if(myOffcanvas) { new bootstrap.Offcanvas(myOffcanvas).show(); }
            @endif
        });
    </script>
@endpush

@section('content')

    <div class="md-page-header">
        <div>
            <h1>Categories</h1>
            <p class="subtitle">Kelola kategori produk</p>
        </div>
        <button class="btn-md-filled" type="button"
                data-bs-toggle="offcanvas"
                data-bs-target="#offcanvasCreateCategory"
                aria-controls="offcanvasCreateCategory">
            <i class="bi bi-plus-lg"></i> Tambah Kategori
        </button>
    </div>

    @include('components.alert')

    @include('categories.partials.table', ['categories' => $categories])

    <div class="d-flex justify-content-end mt-4">
        {{ $categories->withQueryString()->links() }}
    </div>

    @include('categories.partials.create-offcanvas')
    @include('categories.partials._edit-category-modal')
    @include('categories.partials.delete-modal')

@endsection
