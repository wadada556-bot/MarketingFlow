<div class="offcanvas offcanvas-end shadow" tabindex="-1" id="offcanvasCreateProduct" aria-labelledby="offcanvasCreateProductLabel" style="width: 400px;">
    <div class="offcanvas-header border-bottom bg-light">
        <h5 class="offcanvas-title fw-bold" id="offcanvasCreateProductLabel">Create Product</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body d-flex flex-column p-0">
        <form action="{{ route('products.store') }}" method="POST" class="d-flex flex-column h-100">
            @csrf
            
            <div class="flex-grow-1 p-4 overflow-auto">
                @include('products.partials._form', ['categories' => $categories])
            </div>
            
            <div class="mt-auto p-3 border-top bg-light" style="display: flex; justify-content: flex-end; gap: 12px;">
                <button type="button" class="btn btn-light border px-4" data-bs-dismiss="offcanvas">Cancel</button>
                <button type="submit" class="btn btn-primary px-4">Save Product</button>
            </div>
        </form>
    </div>
</div>