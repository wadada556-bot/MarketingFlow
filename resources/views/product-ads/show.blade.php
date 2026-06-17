@extends('components.layouts.app')

@section('title', 'Detail Iklan Produk')

@push('styles')
    <link href="{{ asset('css/timeline.css') }}" rel="stylesheet">
@endpush

@push('scripts')
    <script src="{{ asset('js/product-ads.js') }}?v={{ filemtime(public_path('js/product-ads.js')) }}"></script>
@endpush

@section('content')

    {{-- ── Header ──────────────────────────────────────────────── --}}
    <div class="d-flex align-items-center gap-3 mb-5">
        <a href="{{ route('product-ads.index') }}" class="btn-md-icon" title="Kembali"
           style="color:var(--md-on-surface-variant)">
            <i class="bi bi-arrow-left"></i>
        </a>
        <div>
            <p class="mb-0" style="font-size:12px;font-weight:500;letter-spacing:.8px;text-transform:uppercase;color:var(--md-on-surface-variant)">
                Product Ads
            </p>
            <h4 class="mb-0" style="font-size:22px;font-weight:400;color:var(--md-on-surface)">
                Detail Iklan Produk
            </h4>
        </div>
        <div class="ms-auto">
            <a href="{{ route('product-ads.edit', $productAd->id) }}" class="btn-md-tonal">
                <i class="bi bi-pencil"></i> Edit
            </a>
        </div>
    </div>

    {{-- ── Content Grid ────────────────────────────────────────── --}}
    <div class="row g-4">
        <div class="col-12 col-lg-6">
            @include('product-ads.partials._product-info-card', [
                'product'               => $productAd->product,
                'status_badge'          => $productAd->status_badge,
                'status_testing'        => $productAd->status_testing,
                'testing_started_at'    => $productAd->testing_started_at,
                'testing_completed_at'  => $productAd->testing_completed_at,
                'stockVariants'         => $stockVariants,
            ])
            @include('product-ads.partials._stores-card', ['stores' => $productAd->stores])
        </div>
        <div class="col-12 col-lg-6">
            @include('product-ads.partials._timeline-card', ['productAdLogs' => $productAd->productAdLogs])
        </div>
    </div>

    @include('product-ads.partials._create-log-modal')
    @include('product-ads.partials._edit-log-modal')
    @include('product-ads.partials.delete-modal')

@endsection
