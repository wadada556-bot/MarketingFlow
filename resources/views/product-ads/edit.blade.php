@extends('components.layouts.app')

@section('title', 'Edit Iklan Produk')

@push('styles')
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
    <style>
        .ts-wrapper .ts-control {
            border-radius: var(--md-shape-xs) !important;
            border: 1px solid var(--md-outline) !important;
            background: var(--md-surface) !important;
            box-shadow: none !important; font-size: 14px !important;
            padding: 8px 12px !important;
        }
        .ts-wrapper.focus .ts-control {
            border-color: var(--md-primary) !important;
            border-width: 2px !important; box-shadow: none !important;
        }
        .ts-dropdown {
            border-radius: var(--md-shape-sm) !important;
            border: 1px solid var(--md-outline-variant) !important;
            box-shadow: var(--md-elev-2) !important;
        }
        .ts-dropdown .option.active { background: var(--md-secondary-container) !important; color: var(--md-on-secondary-container) !important; }
    </style>
@endpush

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.searchable-select').forEach(function (el) {
            new TomSelect(el, { create: false, sortField: { field: 'text', direction: 'asc' } });
        });
    });
    </script>
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
                Edit Iklan Produk
            </h4>
        </div>
    </div>

    {{-- ── Form Card ───────────────────────────────────────────── --}}
    <div class="row justify-content-center">
        <div class="col-12 col-lg-8 col-xl-7">
            <div class="md-card" style="padding:32px">
                <form action="{{ route('product-ads.update', $productAd->id) }}" method="POST">
                    @csrf
                    @method('PUT')

                    @include('product-ads.partials._form')

                    <hr class="md-divider" style="margin:28px 0 24px">

                    <div class="d-flex justify-content-end gap-2">
                        <a href="{{ route('product-ads.index') }}" class="btn-md-text">Batal</a>
                        <button type="submit" class="btn-md-filled">
                            <i class="bi bi-check2"></i> Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

@endsection
