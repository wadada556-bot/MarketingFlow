{{-- ── Perpanjang Testing ────────────────────────────────────── --}}
<div class="modal fade" id="extendModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <form class="modal-content" method="POST" id="extendForm">
            @csrf @method('PATCH')
            <div class="modal-header">
                <h5 class="modal-title" style="font-size:18px;font-weight:500">Perpanjang Testing</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body">
                <label for="extend_days" class="form-label" style="font-size:13px">Tambah berapa hari?</label>
                <select class="form-select" id="extend_days" name="days">
                    <option value="3">3 hari</option>
                    <option value="7" selected>7 hari</option>
                    <option value="14">14 hari</option>
                    <option value="custom">Lainnya…</option>
                </select>
                <div class="mt-2 d-none" id="extend_custom_wrap">
                    <input type="number" class="form-control" name="custom_days" min="1" max="365" placeholder="Jumlah hari">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-md-text" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn-md-filled">Perpanjang</button>
            </div>
        </form>
    </div>
</div>

{{-- ── Catatan ───────────────────────────────────────────────── --}}
<div class="modal fade" id="logsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" style="font-size:18px;font-weight:500">Catatan Iklan</h5>
                    <p class="mb-0" style="font-size:12px;color:var(--md-on-surface-variant)" id="logs_product"></p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
            </div>
            <div class="modal-body" id="logs_body"></div>
        </div>
    </div>
</div>
