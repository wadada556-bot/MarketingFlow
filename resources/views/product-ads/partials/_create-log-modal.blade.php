<div class="modal fade md-dialog" id="createLogModal" tabindex="-1"
     aria-labelledby="createLogModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:420px">
        <div class="modal-content">

            <div class="modal-header">
                <div style="width:48px;height:48px;border-radius:50%;background:var(--md-tertiary-container);display:flex;align-items:center;justify-content:center;margin-bottom:4px">
                    <i class="bi bi-clock-history" style="font-size:20px;color:var(--md-tertiary)"></i>
                </div>
            </div>

            <form action="{{ route('product-ad-logs.store') }}" method="POST">
                @csrf
                <div class="modal-body">
                    <p class="modal-title mb-4" style="font-size:18px;font-weight:500;color:var(--md-on-surface)">
                        Tambah Log Aktivitas
                    </p>
                    <input type="hidden" name="product_ad_id" value="{{ $productAd->id }}">

                    <div class="md-field">
                        <label for="create_action_date">Tanggal & Waktu <span style="color:var(--md-error)">*</span></label>
                        <input type="datetime-local" class="form-control"
                               id="create_action_date" name="action_date"
                               value="{{ now()->format('Y-m-d\TH:i') }}" required>
                    </div>

                    <div class="md-field mb-0">
                        <label for="create_description">Deskripsi <span style="color:var(--md-error)">*</span></label>
                        <textarea class="form-control"
                                  id="create_description" name="description"
                                  rows="4" required
                                  placeholder="Tuliskan aktivitas yang terjadi..."></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn-md-text" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn-md-filled">Simpan</button>
                </div>
            </form>

        </div>
    </div>
</div>
