<div class="offcanvas offcanvas-end" tabindex="-1" id="offcanvasCreateStore" aria-labelledby="offcanvasCreateStoreLabel" style="width: 400px;">
    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title" id="offcanvasCreateStoreLabel">Create Store</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body d-flex flex-column">
        <form action="{{ route('stores.store') }}" method="POST" class="d-flex flex-column h-100">
            @csrf
            <div class="flex-grow-1">
                @include('stores.partials._form', ['errorBag' => 'createStore'])
            </div>
            <div class="d-grid gap-2 mt-auto pt-3 border-top bg-white">
                <button type="submit" class="btn btn-primary">Save Store</button>
                <button type="button" class="btn btn-light" data-bs-dismiss="offcanvas">Cancel</button>
            </div>
        </form>
    </div>
</div>