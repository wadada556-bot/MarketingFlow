@extends('components.layouts.app')

@section('title', 'Arsip Snapshot Produk')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/products.css') }}?v={{ filemtime(public_path('css/products.css')) }}">
@endpush

@section('content')

    <div class="products-sticky-header">
        <div class="products-title-block">
            <div style="min-width:0">
                <h1>Arsip Snapshot Produk</h1>
                <div class="products-freshness-row d-flex align-items-center gap-2">
                    <span class="d-inline-flex align-items-center gap-1">
                        <i class="bi bi-info-circle"></i> Dibuat otomatis tiap hari jam 01:00 WIB, disimpan 7 hari terakhir
                    </span>
                </div>
            </div>

            <div class="d-flex align-items-center gap-2" style="flex-shrink:0">
                <a href="{{ route('products.index') }}" class="products-export-btn" title="Kembali ke Products">
                    <i class="bi bi-arrow-left"></i>
                </a>
            </div>
        </div>
    </div>

    @include('components.alert')

    <div class="md-card" style="padding:20px;margin-top:16px">
        @forelse($snapshots as $snap)
            <div style="padding:14px 0;border-bottom:1px solid var(--md-outline-variant)">
                <p class="mb-2 fw-medium" style="color:var(--md-on-surface);font-size:14px">
                    <i class="bi bi-calendar3"></i> {{ \Carbon\Carbon::parse($snap['date'])->translatedFormat('l, d F Y') }}
                </p>
                <div class="d-flex flex-wrap gap-2">
                    @foreach($snap['files'] as $file)
                        <a href="{{ route('products.snapshots.download', ['date' => $snap['date'], 'file' => $file]) }}"
                           class="products-export-btn d-inline-flex align-items-center gap-2"
                           style="width:auto;padding:6px 12px;font-size:13px"
                           title="Download {{ $file }}">
                            <i class="bi bi-file-earmark-excel"></i> {{ $file }}
                        </a>
                    @endforeach
                </div>
            </div>
        @empty
            <p class="mb-0" style="color:var(--md-on-surface-variant);font-size:13.5px">
                Belum ada arsip. Snapshot pertama akan tersimpan pada jadwal berikutnya (01:00 WIB).
            </p>
        @endforelse
    </div>

@endsection
