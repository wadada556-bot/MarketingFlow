<div class="modal fade" id="editProductModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered"> 
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold">Edit Product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <form id="editProductForm" method="POST" action="">
                @csrf
                @method('PUT') 
                
                <div class="modal-body pt-4">
                    <div class="d-flex flex-column" style="gap: 16px;">
                        <div>
                            <label for="edit_parent_sku" class="form-label fw-semibold">Parent SKU <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="edit_parent_sku" name="parent_sku" maxlength="50" required>
                            <div class="form-text small text-muted mt-1"><i class="bi bi-info-circle me-1"></i> Max 50 characters</div>
                        </div>

                        <div>
                            <label for="edit_category_id" class="form-label fw-semibold">Category <span class="text-danger">*</span></label>
                            <select class="form-select" id="edit_category_id" name="category_id" required>
                                <option value="" disabled>-- Select Category --</option>
                                @foreach($categories as $category)
                                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer bg-light border-top-0" style="display: flex; justify-content: flex-end; gap: 12px;">
                    <button type="button" class="btn btn-light border px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>