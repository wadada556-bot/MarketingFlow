<div class="md-card" style="overflow:hidden;height:100%">

    {{-- Card header --}}
    <div style="background:var(--md-surface-container-low);padding:18px 24px;border-bottom:1px solid var(--md-outline-variant)">
        <div class="d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
                <span style="width:32px;height:32px;border-radius:50%;background:var(--md-tertiary-container);display:inline-flex;align-items:center;justify-content:center;color:var(--md-tertiary);font-size:15px">
                    <i class="bi bi-clock-history"></i>
                </span>
                <p class="mb-0" style="font-size:14px;font-weight:500;color:var(--md-on-surface)">Log Aktivitas</p>
            </div>
            <button type="button"
                    class="btn-md-tonal"
                    style="padding:7px 14px;font-size:13px"
                    data-bs-toggle="modal" data-bs-target="#createLogModal">
                <i class="bi bi-plus-lg" style="font-size:12px"></i> Tambah Log
            </button>
        </div>
    </div>

    {{-- Timeline body --}}
    <div style="padding:24px;max-height:480px;overflow-y:auto">
        @if($productAdLogs->isNotEmpty())
            <ul class="md-timeline">
                @foreach($productAdLogs as $log)
                    <li class="md-tl-item">
                        <div class="md-tl-dot"></div>
                        <div class="md-tl-body">
                            <div class="d-flex justify-content-between align-items-start gap-2 mb-1">
                                <span class="md-chip surface">
                                    {{ \Carbon\Carbon::parse($log->action_date ?? $log->created_at)->format('d M Y, H:i') }}
                                </span>
                                <div class="d-flex gap-0 flex-shrink-0">
                                    <button type="button"
                                            class="btn-md-icon"
                                            style="width:32px;height:32px;font-size:14px;color:var(--md-on-surface-variant)"
                                            title="Edit Log"
                                            data-bs-toggle="modal"
                                            data-bs-target="#editLogModal"
                                            data-action="{{ route('product-ad-logs.update', $log->id) }}"
                                            data-date="{{ \Carbon\Carbon::parse($log->action_date ?? $log->created_at)->format('Y-m-d\TH:i') }}"
                                            data-desc="{{ $log->description }}">
                                        <i class="bi bi-pencil"></i>
                                    </button>
                                    <button type="button"
                                            class="btn-md-icon error"
                                            style="width:32px;height:32px;font-size:14px"
                                            title="Hapus Log"
                                            data-bs-toggle="modal"
                                            data-bs-target="#deleteConfirmModal"
                                            data-action="{{ route('product-ad-logs.destroy', $log->id) }}">
                                        <i class="bi bi-trash3"></i>
                                    </button>
                                </div>
                            </div>
                            <p class="mb-0" style="font-size:14px;color:var(--md-on-surface);line-height:20px">
                                {{ $log->description }}
                            </p>
                        </div>
                    </li>
                @endforeach
            </ul>
        @else
            <div style="text-align:center;padding:32px 16px">
                <i class="bi bi-clock d-block mb-3" style="font-size:2rem;color:var(--md-outline)"></i>
                <p class="mb-1" style="font-size:15px;font-weight:500;color:var(--md-on-surface)">Belum Ada Riwayat</p>
                <p class="mb-0" style="font-size:13px;color:var(--md-on-surface-variant)">Log aktivitas akan muncul di sini.</p>
            </div>
        @endif
    </div>

</div>
