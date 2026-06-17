<div class="d-flex flex-column" style="gap: 16px; margin-bottom: 1.5rem;">
    
    <div>
        <label for="parent_sku" class="form-label fw-semibold">Parent SKU <span class="text-danger">*</span></label>
        <input type="text" 
               class="form-control @error('parent_sku') is-invalid @enderror" 
               id="parent_sku" 
               name="parent_sku" 
               value="{{ old('parent_sku', $product->parent_sku ?? '') }}" 
               maxlength="50" 
               placeholder="e.g. SKU-12345" 
               required>
        
        <div class="form-text small text-muted mt-1">
            <i class="bi bi-info-circle me-1"></i> Max 50 characters
        </div>
        
        @error('parent_sku') 
            <div class="invalid-feedback">{{ $message }}</div> 
        @enderror
    </div>

    <div>
        <label for="category_id" class="form-label fw-semibold">Category <span class="text-danger">*</span></label>
        <select class="form-select searchable-select @error('category_id') is-invalid @enderror" 
                id="category_id" 
                name="category_id" 
                required>
            <option value="" selected disabled>-- Select Category --</option>
            
            @forelse($categories as $category)
                <option value="{{ $category->id }}" {{ old('category_id', $product->category_id ?? '') == $category->id ? 'selected' : '' }}>
                    {{ $category->name }}
                </option>
            @empty
                <option value="" disabled>No categories available.</option>
            @endforelse
        </select>
        
        @error('category_id') 
            <div class="invalid-feedback">{{ $message }}</div> 
        @enderror
    </div>
    
</div>