@if($stores->isEmpty())
    <div class="md-card text-center py-5 px-4" style="border-style:dashed">
        <i class="bi bi-shop d-block mb-3" style="font-size:2.8rem;color:var(--md-outline)"></i>
        <p class="mb-1" style="font-size:16px;font-weight:500;color:var(--md-on-surface)">Tidak Ada Toko</p>
        <p class="mb-0" style="font-size:14px;color:var(--md-on-surface-variant)">Tambahkan toko baru untuk mulai.</p>
    </div>
@else
    <div class="md-table-wrap">
        <table class="md-table table-hover align-middle">
            <thead>
                <tr>
                    <th>Nama Toko</th>
                    <th class="text-center" style="width:110px">Tindakan</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($stores as $store)
                    <tr>
                        <td>
                            <div style="display:flex;align-items:center;gap:10px">
                                <div style="
                                    width:32px;height:32px;
                                    background:var(--md-primary-container);
                                    border-radius:var(--md-shape-sm);
                                    display:flex;align-items:center;justify-content:center;
                                    flex-shrink:0;
                                ">
                                    <i class="bi bi-shop" style="font-size:14px;color:var(--md-on-primary-container)"></i>
                                </div>
                                <p class="mb-0 fw-medium" style="font-size:14px;color:var(--md-on-surface)">
                                    {{ $store->name ?? 'N/A' }}
                                </p>
                            </div>
                        </td>
                        <td>
                            <div class="d-flex justify-content-center align-items-center gap-0">
                                <button type="button"
                                        class="btn-md-icon warning"
                                        title="Edit Toko"
                                        data-bs-toggle="modal"
                                        data-bs-target="#editStoreModal"
                                        data-action="{{ route('stores.update', $store->id) }}"
                                        data-store="{{ $store->name }}">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button type="button"
                                        class="btn-md-icon error"
                                        title="Hapus Toko"
                                        data-bs-toggle="modal"
                                        data-bs-target="#deleteConfirmModal"
                                        data-action="{{ route('stores.destroy', $store->id) }}">
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
