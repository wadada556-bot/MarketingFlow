<div class="modal fade md-dialog" id="bulkDeleteConfirmModal" tabindex="-1"
     aria-labelledby="bulkDeleteConfirmModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:360px">
        <div class="modal-content">

            <div class="modal-header">
                <div style="width:48px;height:48px;border-radius:50%;background:var(--md-error-container);display:flex;align-items:center;justify-content:center;margin-bottom:4px">
                    <i class="bi bi-trash3-fill" style="font-size:20px;color:var(--md-error)"></i>
                </div>
            </div>

            <div class="modal-body">
                <p class="modal-title mb-2" style="font-size:18px;font-weight:500;color:var(--md-on-surface)">
                    Hapus Semua yang Dipilih?
                </p>
                <p class="mb-0" style="font-size:14px;color:var(--md-on-surface-variant);line-height:20px">
                    Semua iklan produk yang Anda pilih akan dihapus secara permanen. Tindakan ini tidak dapat dibatalkan.
                </p>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn-md-text" data-bs-dismiss="modal">Batal</button>
                <button type="button" id="executeBulkDelete" class="btn-md-error-filled">Ya, Hapus Semua</button>
            </div>

        </div>
    </div>
</div>
