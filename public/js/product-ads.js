document.addEventListener('DOMContentLoaded', function () {
    var searchableSelects = document.querySelectorAll('.searchable-select');
    searchableSelects.forEach(function(selectElement) {
        new TomSelect(selectElement, {
            create: false,
            sortField: { field: "text", direction: "asc" },
        });
    });

    var deleteModal = document.getElementById('deleteConfirmModal');
    if (deleteModal) {
        deleteModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var actionUrl = button.getAttribute('data-action');
            var form = deleteModal.querySelector('#deleteForm');
            form.setAttribute('action', actionUrl);
        });
    }

    var editLogModal = document.getElementById('editLogModal');
    if (editLogModal) {
        editLogModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var actionUrl = button.getAttribute('data-action');
            var dateData = button.getAttribute('data-date');
            var descData = button.getAttribute('data-desc');
            var form = editLogModal.querySelector('#editLogForm');

            form.setAttribute('action', actionUrl);
            form.querySelector('#edit_action_date').value = dateData;
            form.querySelector('#edit_description').value = descData;
        });
    }

    var storeFilterElement = document.getElementById('filter_store');
    if (storeFilterElement) {
        new TomSelect(storeFilterElement, {
            plugins: ['remove_button'],
            maxItems: null,
            hideSelected: true,
            placeholder: "Select stores...",
        });
    }

    var editProductModal = document.getElementById('editProductModal');
    if (editProductModal) {
        editProductModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var actionUrl = button.getAttribute('data-action');
            var skuData = button.getAttribute('data-sku');
            var categoryData = button.getAttribute('data-category');
            var form = editProductModal.querySelector('#editProductForm');

            form.setAttribute('action', actionUrl);
            form.querySelector('#edit_parent_sku').value = skuData;
            form.querySelector('#edit_category_id').value = categoryData;
        });
    }

    var editCategoryModal = document.getElementById('editCategoryModal');
    if (editCategoryModal) {
        editCategoryModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var actionUrl = button.getAttribute('data-action');
            var categoryData = button.getAttribute('data-category');
            var form = editCategoryModal.querySelector('#editCategoryForm');

            form.setAttribute('action', actionUrl);
            form.querySelector('#edit_name').value = categoryData;
        });
    }

    var editStoreModal = document.getElementById('editStoreModal');
    if (editStoreModal) {
        editStoreModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var actionUrl = button.getAttribute('data-action');
            var storeData = button.getAttribute('data-store');
            var form = editStoreModal.querySelector('#editStoreForm');

            form.setAttribute('action', actionUrl);
            form.querySelector('#edit_name').value = storeData;
        });
    }
});