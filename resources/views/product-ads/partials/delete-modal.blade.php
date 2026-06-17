<div class="modal fade md-dialog" id="deleteConfirmModal" tabindex="-1"
     aria-labelledby="deleteConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:360px">
        <div class="modal-content">

            <div class="modal-header">
                <div style="width:48px;height:48px;border-radius:50%;background:var(--md-error-container);display:flex;align-items:center;justify-content:center;margin-bottom:4px">
                    <i class="bi bi-trash3" style="font-size:20px;color:var(--md-error)"></i>
                </div>
            </div>

            <div class="modal-body">
                <p class="modal-title mb-2" style="font-size:18px;font-weight:500;color:var(--md-on-surface)">
                    Hapus Iklan?
                </p>
                <p class="mb-0" style="font-size:14px;color:var(--md-on-surface-variant);line-height:20px">
                    Data iklan produk ini akan dihapus secara permanen dan tidak bisa dikembalikan.
                </p>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn-md-text" data-bs-dismiss="modal">Batal</button>
                <form id="deleteForm" method="POST" action="">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn-md-error-filled">Hapus</button>
                </form>
            </div>

        </div>
    </div>
</div>
