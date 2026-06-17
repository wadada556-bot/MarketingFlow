<div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasCreateCategory" aria-labelledby="offcanvasCreateCategoryLabel" style="width: 400px;">
    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title" id="offcanvasCreateCategoryLabel">Create Category</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body d-flex flex-column">
        <form action="{{ route('categories.store') }}" method="POST" class="d-flex flex-column h-100">
            @csrf
            <div class="flex-grow-1">
                @include('categories.partials._form')
            </div>
            <div class="d-grid gap-2 mt-auto pt-3 border-top bg-white">
                <button type="submit" class="btn btn-primary">Save Category</button>
                <button type="button" class="btn btn-light" data-bs-dismiss="offcanvas">Cancel</button>
            </div>
        </form>
    </div>
</div>