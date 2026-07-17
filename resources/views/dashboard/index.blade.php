@extends('components.layouts.app')

@section('title', 'Peringatan Stok')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/dashboard.css') }}?v={{ filemtime(public_path('css/dashboard.css')) }}">

@endpush

@section('content')

{{-- Stock Alerts (di-load lazy via AJAX → dashboard.stock-alerts) --}}
<div class="dash-section-card">
    <div id="stock-card-lazy">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
            <div>
                <p class="dash-section-title">Peringatan Stok</p>
                <p class="dash-section-subtitle">Produk butuh perhatian</p>
            </div>
            <span class="dash-spinner"></span>
        </div>
        <div style="padding:28px 16px;text-align:center;background:var(--md-surface-container-low);border-radius:var(--md-shape-sm)">
            <span class="dash-spinner" style="width:22px;height:22px;border-width:3px"></span>
            <p style="font-size:13px;color:var(--md-on-surface-variant);margin:12px 0 0">Memuat data stok…</p>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
window.DASH = {
    routes: {
        stockAlerts: "{{ route('dashboard.stock-alerts') }}",
    },
};
</script>
<script src="{{ asset('js/dashboard.js') }}?v={{ filemtime(public_path('js/dashboard.js')) }}"></script>
@endpush
