@if($categories->isEmpty())
    <div class="md-card text-center py-5 px-4" style="border-style:dashed">
        <i class="bi bi-tags d-block mb-3" style="font-size:2.8rem;color:var(--md-outline)"></i>
        <p class="mb-1" style="font-size:16px;font-weight:500;color:var(--md-on-surface)">Tidak Ada Kategori</p>
        <p class="mb-0" style="font-size:14px;color:var(--md-on-surface-variant)">Tambahkan kategori produk baru.</p>
    </div>
@else
    <div class="md-table-wrap">
        <table class="md-table table-hover align-middle">
            <thead>
                <tr>
                    <th>Nama Kategori</th>
                    <th class="text-center" style="width:110px">Tindakan</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($categories as $category)
                    <tr>
                        <td>
                            <p class="mb-0 fw-medium" style="font-size:14px;color:var(--md-on-surface)">
                                {{ $category->name ?? 'N/A' }}
                            </p>
                        </td>
                        <td>
                            <div class="d-flex justify-content-center align-items-center gap-0">
                                <button type="button"
                                        class="btn-md-icon warning"
                                        title="Edit Kategori"
                                        data-bs-toggle="modal"
                                        data-bs-target="#editCategoryModal"
                                        data-action="{{ route('categories.update', $category->id) }}"
                                        data-category="{{ $category->name }}">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button type="button"
                                        class="btn-md-icon error"
                                        title="Hapus Kategori"
                                        data-bs-toggle="modal"
                                        data-bs-target="#deleteConfirmModal"
                                        data-action="{{ route('categories.destroy', $category->id) }}">
                                    <i class="bi bi-trash3"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
