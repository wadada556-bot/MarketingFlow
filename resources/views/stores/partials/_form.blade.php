<div class="row g-4 mb-3">
    
    <div class="col-md-12">
        <label for="name" class="form-label fw-semibold">Name <span class="text-danger">*</span></label>
        <input type="text" 
               class="form-control @error('name', $errorBag) is-invalid @enderror" 
               id="name" 
               name="name" 
               value="{{ old('name', $store->name ?? '') }}" 
               maxlength="100" 
               placeholder="e.g. Store Baru" 
               required>
        
        <div class="form-text small text-muted">
            <i class="bi bi-info-circle me-1"></i> Max 100 characters
        </div>
        
        @error('name', $errorBag)
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>
    
</div>