@extends('components.layouts.app')

@section('title', 'Product Ads')

@push('styles')
    <link href="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/css/tom-select.bootstrap5.min.css" rel="stylesheet">
    <link href="{{ asset('css/tomselect-md3.css') }}" rel="stylesheet">
@endpush

@section('content')
    {{-- ── Page Header ─────────────────────────────────────────── --}}
    <div class="d-flex justify-content-between align-items-center mb-4" style="gap:12px">
        <div>
            <h4 class="mb-0" style="font-size:22px;font-weight:400;color:var(--md-on-surface)">Product Ads</h4>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <button type="button" id="btn_bulk_delete"
                    class="btn-md-error-tonal d-none"
                    data-bs-toggle="modal" data-bs-target="#bulkDeleteConfirmModal">
                <i class="bi bi-trash3"></i>
                Hapus Terpilih (<span id="select_count">0</span>)
            </button>
            <button class="btn-md-filled"
                    type="button"
                    data-bs-toggle="offcanvas"
                    data-bs-target="#offcanvasCreateAd"
                    aria-controls="offcanvasCreateAd">
                <i class="bi bi-plus-lg"></i> Tambah Baru
            </button>
        </div>
    </div>

    @include('components.alert')

    {{-- ── Tab Navigation ──────────────────────────────────────── --}}
    @php $currentStatus = request('testing_status') ?: 'semua'; @endphp

    <nav class="md-tabs mb-4 overflow-auto" style="padding-bottom:2px">
        <a href="{{ request()->fullUrlWithQuery(['testing_status' => null, 'page' => null]) }}"
           class="md-tab {{ $currentStatus === 'semua' ? 'active' : '' }}">
            Semua
            @if($tabCounts['semua'] > 0)
                <span class="tab-count">{{ $tabCounts['semua'] }}</span>
            @endif
        </a>
        <a href="{{ request()->fullUrlWithQuery(['testing_status' => 'perlu_dicek', 'page' => null]) }}"
           class="md-tab {{ $currentStatus === 'perlu_dicek' ? 'active' : '' }}">
            Perlu Dicek
            @if($tabCounts['perlu_dicek'] > 0)
                <span class="tab-count">{{ $tabCounts['perlu_dicek'] }}</span>
                <span class="tab-dot"></span>
            @endif
        </a>
        <a href="{{ request()->fullUrlWithQuery(['testing_status' => 'berhasil', 'page' => null]) }}"
           class="md-tab {{ $currentStatus === 'berhasil' ? 'active' : '' }}">
            Berhasil
            @if($tabCounts['berhasil'] > 0)
                <span class="tab-count">{{ $tabCounts['berhasil'] }}</span>
            @endif
        </a>
        <a href="{{ request()->fullUrlWithQuery(['testing_status' => 'gagal', 'page' => null]) }}"
           class="md-tab {{ $currentStatus === 'gagal' ? 'active' : '' }}">
            Gagal
            @if($tabCounts['gagal'] > 0)
                <span class="tab-count">{{ $tabCounts['gagal'] }}</span>
            @endif
        </a>
    </nav>

    @include('product-ads.partials._filter-bar')

    @if($productAds->isEmpty())
        {{-- Empty State --}}
        <div class="md-card text-center py-5 px-4" style="border-style:dashed">
            <i class="bi bi-inbox d-block mb-3" style="font-size:2.8rem;color:var(--md-outline)"></i>
            <p class="mb-1" style="font-size:16px;font-weight:500;color:var(--md-on-surface)">Tidak Ada Data</p>
            <p class="mb-0" style="font-size:14px;color:var(--md-on-surface-variant)">Coba sesuaikan filter atau tambahkan Product Ad baru.</p>
        </div>
    @else
        <form id="formBulkDelete" action="{{ route('product-ads.bulk-destroy') }}" method="POST">
            @csrf
            @method('DELETE')
            @include('product-ads.partials.table', ['productAds' => $productAds])
        </form>

        {{-- Pagination --}}
        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-center mt-4 gap-2">
            <p class="mb-0" style="font-size:13px;color:var(--md-on-surface-variant)">
                Menampilkan
                <strong style="color:var(--md-on-surface)">{{ $productAds->firstItem() ?? 0 }}</strong>
                –
                <strong style="color:var(--md-on-surface)">{{ $productAds->lastItem() ?? 0 }}</strong>
                dari
                <strong style="color:var(--md-on-surface)">{{ $productAds->total() }}</strong>
                data
            </p>
            <div>{{ $productAds->withQueryString()->links() }}</div>
        </div>
    @endif

    {{-- Hidden status form --}}
    <form id="dynamicStatusForm" method="POST" style="display:none">
        @csrf
        @method('PATCH')
    </form>

    @include('product-ads.partials.create-offcanvas')
    @include('product-ads.partials.delete-modal')
    @include('product-ads.partials.bulk-delete-modal')
@endsection

{{-- ── Extend Modal ────────────────────────────────────────────── --}}
<div class="modal fade md-dialog" id="extendModal" tabindex="-1" aria-labelledby="extendModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:400px">
        <form id="extendForm" method="POST">
            @csrf
            @method('PATCH')
            <div class="modal-content">
                <div class="modal-header">
                    <span style="font-size:24px;color:var(--md-primary);margin-bottom:4px;display:block">
                        <i class="bi bi-arrow-repeat"></i>
                    </span>
                </div>
                <div class="modal-body" style="padding-top:8px !important">
                    <p class="modal-title mb-3">Perpanjang Masa Testing</p>
                    <div class="md-field">
                        <label for="extendDuration">Durasi Perpanjangan</label>
                        <select class="form-select" id="extendDuration" name="days" onchange="toggleCustomDays()">
                            <option value="3">3 Hari</option>
                            <option value="7">7 Hari</option>
                            <option value="custom">Masukkan jumlah hari...</option>
                        </select>
                    </div>
                    <div class="md-field d-none" id="customDaysWrapper">
                        <label for="customDaysInput">Jumlah Hari</label>
                        <input type="number" class="form-control" id="customDaysInput" name="custom_days" min="1" placeholder="Mis. 14">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-md-text" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn-md-filled">Simpan</button>
                </div>
            </div>
        </form>
    </div>
</div>

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.2.2/dist/js/tom-select.complete.min.js"></script>
    <script src="{{ asset('js/product-ads.js') }}?v={{ filemtime(public_path('js/product-ads.js')) }}"></script>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        @if($errors->any())
            var myOffcanvas = document.getElementById('offcanvasCreateAd');
            if (myOffcanvas) { new bootstrap.Offcanvas(myOffcanvas).show(); }
        @endif

        /* ── Bulk selection ── */
        const selectAll   = document.getElementById('select_all');
        const checkboxes  = document.querySelectorAll('.sub_chk');
        const bulkBtn     = document.getElementById('btn_bulk_delete');
        const selectCount = document.getElementById('select_count');

        function syncBulkBtn() {
            const n = document.querySelectorAll('.sub_chk:checked').length;
            bulkBtn.classList.toggle('d-none', n === 0);
            if (selectCount) selectCount.textContent = n;
        }

        if (selectAll) {
            selectAll.addEventListener('change', function () {
                checkboxes.forEach(cb => cb.checked = this.checked);
                syncBulkBtn();
            });
        }
        checkboxes.forEach(cb => cb.addEventListener('change', function () {
            if (!this.checked && selectAll) selectAll.checked = false;
            else if (selectAll && document.querySelectorAll('.sub_chk:checked').length === checkboxes.length)
                selectAll.checked = true;
            syncBulkBtn();
        }));

        /* ── Bulk delete confirm ── */
        const execBulk = document.getElementById('executeBulkDelete');
        if (execBulk) execBulk.addEventListener('click', () => document.getElementById('formBulkDelete').submit());

        /* ── Status action buttons ── */
        document.querySelectorAll('.btn-status-action').forEach(btn => {
            btn.addEventListener('click', function () {
                if (confirm(this.dataset.message)) {
                    const form = document.getElementById('dynamicStatusForm');
                    form.action = this.dataset.action;
                    form.submit();
                }
            });
        });

        /* ── Extend modal ── */
        const extendModal = document.getElementById('extendModal');
        if (extendModal) {
            extendModal.addEventListener('show.bs.modal', function (e) {
                const btn = e.relatedTarget;
                document.getElementById('extendForm').action = btn.dataset.url;
                document.getElementById('extendDuration').value = '3';
                document.getElementById('customDaysWrapper').classList.add('d-none');
                const ci = document.getElementById('customDaysInput');
                ci.value = ''; ci.required = false;
            });
        }
    });

    function toggleCustomDays() {
        const sel = document.getElementById('extendDuration');
        const wrap = document.getElementById('customDaysWrapper');
        const inp  = document.getElementById('customDaysInput');
        const show = sel.value === 'custom';
        wrap.classList.toggle('d-none', !show);
        inp.required = show;
        if (!show) inp.value = '';
    }
    </script>
@endpush
