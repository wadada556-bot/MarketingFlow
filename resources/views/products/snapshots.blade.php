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
                        <i class="bi bi-info-circle"></i>
                        Dibuat otomatis tiap hari 00:05 WIB (data ditutup 23:58 malam sebelumnya), disimpan 7 hari terakhir
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
            @php
                $closed   = \Carbon\Carbon::parse($snap['date']);
                $archived = $closed->copy()->addDay();
            @endphp
            <div style="padding:16px 0;border-bottom:1px solid var(--md-outline-variant)">
                <div class="d-flex align-items-start justify-content-between gap-2 flex-wrap mb-2">
                    <div style="min-width:0">
                        <p class="mb-1 fw-medium" style="color:var(--md-on-surface);font-size:14px">
                            <i class="bi bi-calendar3"></i> {{ $closed->translatedFormat('l, d F Y') }}
                        </p>
                        <span class="d-inline-flex align-items-center gap-1"
                              style="font-size:11.5px;color:var(--md-on-surface-variant);
                                     background:var(--md-surface-variant);padding:3px 9px;border-radius:999px">
                            <i class="bi bi-clock-history"></i>
                            Data ditutup {{ $closed->translatedFormat('d M') }} 23:58 · diarsipkan {{ $archived->translatedFormat('d M') }}
                        </span>
                    </div>

                    @if($snap['files']->isNotEmpty())
                        <a href="{{ route('products.snapshots.download-all', ['date' => $snap['date']]) }}"
                           class="products-export-btn d-inline-flex align-items-center gap-2"
                           style="width:auto;padding:6px 12px;font-size:13px;flex-shrink:0"
                           title="Unduh semua toko tanggal ini (.zip)">
                            <i class="bi bi-file-earmark-zip"></i> Unduh semua
                        </a>
                    @endif
                </div>

                <div class="d-flex flex-wrap gap-2">
                    @foreach($snap['files'] as $file)
                        <a href="{{ route('products.snapshots.download', ['date' => $snap['date'], 'file' => $file['name']]) }}"
                           class="products-export-btn d-inline-flex align-items-center gap-2"
                           style="width:auto;padding:6px 12px;font-size:13px"
                           title="Download {{ $file['name'] }}">
                            <i class="bi bi-file-earmark-excel"></i>
                            <span>{{ $file['name'] }}</span>
                            <span style="opacity:.7;font-size:11.5px">
                                {{ $file['size'] }} · {{ $file['rows'] !== null ? number_format($file['rows']) . ' baris' : '—' }}
                            </span>
                        </a>
                    @endforeach
                </div>
            </div>
        @empty
            <p class="mb-0" style="color:var(--md-on-surface-variant);font-size:13.5px">
                Belum ada arsip. Snapshot pertama akan tersimpan pada jadwal berikutnya (00:05 WIB).
            </p>
        @endforelse
    </div>

@endsection
