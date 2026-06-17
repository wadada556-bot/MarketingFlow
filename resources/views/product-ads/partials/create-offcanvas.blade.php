<div class="offcanvas offcanvas-end md-side-sheet" tabindex="-1"
     id="offcanvasCreateAd" aria-labelledby="offcanvasCreateAdLabel"
     style="width:460px">

    <div class="offcanvas-header">
        <div class="d-flex align-items-center gap-3">
            <span style="width:40px;height:40px;border-radius:50%;background:var(--md-primary-container);display:inline-flex;align-items:center;justify-content:center;color:var(--md-on-primary-container);font-size:18px">
                <i class="bi bi-megaphone"></i>
            </span>
            <h5 class="offcanvas-title mb-0" id="offcanvasCreateAdLabel">Buat Iklan Baru</h5>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>

    <div class="offcanvas-body d-flex flex-column p-0" style="background:var(--md-surface)">
        <form action="{{ route('product-ads.store') }}" method="POST" class="d-flex flex-column h-100">
            @csrf
            <div class="flex-grow-1 overflow-auto" style="padding:24px">
                @include('product-ads.partials._form')
            </div>
            <div class="sheet-footer">
                <button type="button" class="btn-md-text" data-bs-dismiss="offcanvas">Batal</button>
                <button type="submit" class="btn-md-filled">
                    <i class="bi bi-check2"></i> Simpan
                </button>
            </div>
        </form>
    </div>
</div>
