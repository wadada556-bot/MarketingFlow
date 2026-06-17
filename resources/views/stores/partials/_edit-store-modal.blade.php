<div class="modal fade" id="editStoreModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg"> 
        <div class="modal-content">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold">Edit Store</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <form id="editStoreForm" method="POST" action="">
                @csrf
                @method('PUT') 
                
                <div class="modal-body">
                    <div class="row g-4 mb-2">
                        <div class="col-md-12">
                            <label for="edit_name" class="form-label fw-semibold">
                                Name<span class="text-danger">*</span>
                            </label>
                            <input type="text" class="form-control" id="edit_name" name="name" maxlength="100" required>
                            <div class="form-text small text-muted">
                                <i class="bi bi-info-circle me-1"></i>Max 100 characters
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer bg-light border-top-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>