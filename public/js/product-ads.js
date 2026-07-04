document.addEventListener('DOMContentLoaded', function () {
    var filterForm = document.getElementById('filterForm');

    // Debounce 350ms — cegah multiple concurrent request saat user ganti filter cepat
    var _filterTimer = null;
    function submitFilter() {
        clearTimeout(_filterTimer);
        _filterTimer = setTimeout(function () { filterForm.submit(); }, 350);
    }

    var searchableSelects = document.querySelectorAll('.searchable-select');
    searchableSelects.forEach(function(selectElement) {
        var insideFilterForm = filterForm && filterForm.contains(selectElement);
        new TomSelect(selectElement, {
            create: false,
            sortField: { field: "text", direction: "asc" },
            onChange: function() {
                if (insideFilterForm) submitFilter();
            },
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
            onChange: function() {
                if (filterForm) submitFilter();
            },
        });
    }

    // Handle inline onchange yang dipindah ke JS (status select)
    var statusEl = document.getElementById('filter_status');
    if (statusEl) statusEl.addEventListener('change', submitFilter);

    var editProductModal = document.getElementById('editProductModal');
    if (editProductModal) {
        editProductModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var actionUrl = button.getAttribute('data-action');
            var skuData = button.getAttribute('data-sku');
            var form = editProductModal.querySelector('#editProductForm');

            form.setAttribute('action', actionUrl);
            form.querySelector('#edit_parent_sku').value = skuData;
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