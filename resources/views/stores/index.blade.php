@extends('components.layouts.app')

@section('title', 'Stores')

@push('scripts')
    <script src="{{ asset('js/product-ads.js') }}?v={{ filemtime(public_path('js/product-ads.js')) }}"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            @if($errors->createStore->any())
                var myOffcanvas = document.getElementById('offcanvasCreateStore');
                if(myOffcanvas) { new bootstrap.Offcanvas(myOffcanvas).show(); }
            @endif
        });
    </script>
@endpush

@section('content')

    <div class="md-page-header">
        <div>
            <h1>Stores</h1>
            <p class="subtitle">Kelola data toko Anda</p>
        </div>
        <button class="btn-md-filled" type="button"
                data-bs-toggle="offcanvas"
                data-bs-target="#offcanvasCreateStore"
                aria-controls="offcanvasCreateStore">
            <i class="bi bi-plus-lg"></i> Tambah Toko
        </button>
    </div>

    @include('components.alert')

    @include('stores.partials.table')

    <div class="d-flex justify-content-end mt-4">
        {{ $stores->withQueryString()->links() }}
    </div>

    @include('stores.partials.create-offcanvas')
    @include('stores.partials._edit-store-modal')
    @include('stores.partials.delete-modal')

@endsection
