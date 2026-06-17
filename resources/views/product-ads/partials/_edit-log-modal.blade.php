<div class="modal fade md-dialog" id="editLogModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:420px">
        <div class="modal-content">

            <div class="modal-header">
                <div style="width:48px;height:48px;border-radius:50%;background:var(--md-secondary-container);display:flex;align-items:center;justify-content:center;margin-bottom:4px">
                    <i class="bi bi-pencil-square" style="font-size:20px;color:var(--md-secondary)"></i>
                </div>
            </div>

            <form id="editLogForm" method="POST" action="">
                @csrf
                @method('PUT')

                <div class="modal-body">
                    <p class="modal-title mb-4" style="font-size:18px;font-weight:500;color:var(--md-on-surface)">
                        Edit Log Aktivitas
                    </p>

                    <div class="md-field">
                        <label for="edit_action_date">Tanggal & Waktu <span style="color:var(--md-error)">*</span></label>
                        <input type="datetime-local" class="form-control"
                               id="edit_action_date" name="action_date" required>
                    </div>

                    <div class="md-field mb-0">
                        <label for="edit_description">Deskripsi <span style="color:var(--md-error)">*</span></label>
                        <textarea class="form-control"
                                  id="edit_description" name="description"
                                  rows="4" required
                                  placeholder="Tuliskan aktivitas yang terjadi..."></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-md-text" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn-md-filled">Simpan Perubahan</button>
                </div>
            </form>

        </div>
    </div>
</div>
