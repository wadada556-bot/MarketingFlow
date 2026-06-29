@extends('components.layouts.app')

@section('title', 'Notifikasi')

@section('content')
<div class="container-fluid py-4 px-4">

    <div class="d-flex align-items-center justify-content-between mb-4">
        <h5 class="mb-0" style="font-weight:600;color:var(--md-on-surface)">
            <i class="bi bi-bell me-2"></i>Notifikasi
            @if($unreadCount > 0)
                <span class="badge ms-1" style="background:var(--md-error);color:var(--md-on-error);font-size:12px">
                    {{ $unreadCount }}
                </span>
            @endif
        </h5>
        @if($unreadCount > 0)
            <form method="POST" action="{{ route('notifications.read-all') }}">
                @csrf
                <button type="submit" class="btn btn-sm"
                    style="background:var(--md-secondary-container);color:var(--md-on-secondary-container);border:none;border-radius:var(--md-shape-full);font-size:13px;padding:6px 16px">
                    Tandai semua sudah dibaca
                </button>
            </form>
        @endif
    </div>

    @forelse($notifications as $notif)
        @php $data = $notif->data; $isUnread = is_null($notif->read_at); @endphp
        <div class="mb-2 p-3 rounded-3 d-flex align-items-start gap-3"
            style="background:{{ $isUnread ? 'var(--md-primary-container)' : 'var(--md-surface-container)' }};border:1px solid {{ $isUnread ? 'var(--md-primary)' : 'var(--md-outline-variant)' }}">

            <div style="margin-top:2px;flex-shrink:0">
                <i class="bi {{ str_contains($data['title'] ?? '', '⚠️') ? 'bi-exclamation-triangle-fill' : 'bi-check-circle-fill' }}"
                   style="font-size:18px;color:{{ str_contains($data['title'] ?? '', '⚠️') ? 'var(--md-error)' : 'var(--md-primary)' }}"></i>
            </div>

            <div class="flex-grow-1">
                <div class="d-flex align-items-center justify-content-between">
                    <span style="font-weight:{{ $isUnread ? '600' : '500' }};font-size:14px;color:var(--md-on-surface)">
                        {{ $data['title'] ?? 'Notifikasi' }}
                    </span>
                    <span style="font-size:11px;color:var(--md-on-surface-variant)">
                        {{ $notif->created_at->timezone('Asia/Jakarta')->format('d M Y, H:i') }} WIB
                    </span>
                </div>
                <p class="mb-0 mt-1" style="font-size:13px;color:var(--md-on-surface-variant)">
                    {{ $data['message'] ?? '' }}
                </p>
                @if(!empty($data['changes']))
                    @php $changeId = 'changes-' . $notif->id; @endphp
                    <div class="mt-2" style="font-size:12px;color:var(--md-on-surface-variant)">
                        @foreach(array_slice($data['changes'], 0, 5) as $c)
                            @php
                                $old    = 'Rp ' . number_format($c['old'], 0, ',', '.');
                                $new    = 'Rp ' . number_format($c['new'], 0, ',', '.');
                                $pct    = $c['old'] > 0 ? round(($c['new'] - $c['old']) / $c['old'] * 100, 1) : 0;
                                $pctStr = ($pct >= 0 ? '+' : '') . number_format($pct, 1, ',', '.') . '%';
                            @endphp
                            <div>• {{ $c['sku'] }}: {{ $old }} → {{ $new }} ({{ $pctStr }})</div>
                        @endforeach
                        @if(count($data['changes']) > 5)
                            <div id="{{ $changeId }}" style="display:none">
                                @foreach(array_slice($data['changes'], 5) as $c)
                                    @php
                                        $old    = 'Rp ' . number_format($c['old'], 0, ',', '.');
                                        $new    = 'Rp ' . number_format($c['new'], 0, ',', '.');
                                        $pct    = $c['old'] > 0 ? round(($c['new'] - $c['old']) / $c['old'] * 100, 1) : 0;
                                        $pctStr = ($pct >= 0 ? '+' : '') . number_format($pct, 1, ',', '.') . '%';
                                    @endphp
                                    <div>• {{ $c['sku'] }}: {{ $old }} → {{ $new }} ({{ $pctStr }})</div>
                                @endforeach
                            </div>
                            <button type="button"
                                onclick="toggleChanges('{{ $changeId }}', this)"
                                class="mt-1 btn btn-sm p-0"
                                style="font-size:12px;color:var(--md-primary);background:none;border:none;cursor:pointer;text-decoration:underline">
                                Lihat {{ count($data['changes']) - 5 }} SKU lainnya
                            </button>
                        @endif
                    </div>
                @endif
            </div>

            @if($isUnread)
                <form method="POST" action="{{ route('notifications.read', $notif->id) }}" style="flex-shrink:0">
                    @csrf
                    <button type="submit" class="btn btn-sm"
                        style="background:transparent;border:1px solid var(--md-outline-variant);border-radius:var(--md-shape-full);font-size:12px;padding:4px 12px;color:var(--md-on-surface-variant)">
                        Baca
                    </button>
                </form>
            @endif

        </div>
    @empty
        <div class="text-center py-5" style="color:var(--md-on-surface-variant)">
            <i class="bi bi-bell-slash" style="font-size:40px"></i>
            <p class="mt-3 mb-0" style="font-size:14px">Belum ada notifikasi</p>
        </div>
    @endforelse

    <div class="mt-3">
        {{ $notifications->links() }}
    </div>

</div>

@push('scripts')
<script>
function toggleChanges(id, btn) {
    const el = document.getElementById(id);
    const isHidden = el.style.display === 'none';
    el.style.display = isHidden ? 'block' : 'none';
    const count = el.querySelectorAll('div').length;
    btn.textContent = isHidden ? 'Sembunyikan' : 'Lihat ' + count + ' SKU lainnya';
}
</script>
@endpush
@endsection
