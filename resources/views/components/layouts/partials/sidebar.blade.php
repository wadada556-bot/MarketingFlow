<div id="sidebar-wrapper">

    {{-- ── Drawer Header ──────────────────────────────────── --}}
    <div style="
        height: 64px;
        display: flex;
        align-items: center;
        padding: 0 20px;
        gap: 10px;
        border-bottom: 1px solid var(--md-outline-variant);
        flex-shrink: 0;
    ">
        <div style="
            width: 36px; height: 36px;
            background: var(--md-primary-container);
            border-radius: var(--md-shape-sm);
            display: flex; align-items: center; justify-content: center;
            flex-shrink: 0;
        ">
            <i class="bi bi-graph-up-arrow" style="font-size:16px;color:var(--md-on-primary-container)"></i>
        </div>
        <div>
            <p style="font-size:15px;font-weight:600;color:var(--md-on-surface);margin:0;letter-spacing:.1px">Marketing Flow</p>
            <p style="font-size:11px;color:var(--md-on-surface-variant);margin:0;letter-spacing:.3px">Ad Management</p>
        </div>
    </div>

    {{-- ── Navigation Items ─────────────────────────────────── --}}
    <nav style="padding: 12px 12px; flex: 1; overflow-y: auto;">

        @php
            $unreadNotifCount = \App\Models\User::first()?->unreadNotifications()->count() ?? 0;
            $navItems = [
                [
                    'label'  => 'Peringatan Stok',
                    'icon'   => 'bi-grid-1x2',
                    'active' => request()->is('dashboard*'),
                    'href'   => route('dashboard'),
                    'badge'  => 0,
                ],
                [
                    'label'  => 'Products',
                    'icon'   => 'bi-box-seam',
                    'active' => request()->is('products*'),
                    'href'   => '/products',
                    'badge'  => 0,
                ],
                [
                    'label'  => 'Stores',
                    'icon'   => 'bi-shop',
                    'active' => request()->is('stores*'),
                    'href'   => '/stores',
                    'badge'  => 0,
                ],
                [
                    'label'  => 'Perubahan HPP',
                    'icon'   => 'bi-bell',
                    'active' => request()->is('notifications*'),
                    'href'   => route('notifications.index'),
                    'badge'  => $unreadNotifCount,
                ],
            ];
        @endphp

        @foreach ($navItems as $item)
            <a href="{{ $item['href'] }}"
               style="
                   display: flex;
                   align-items: center;
                   gap: 14px;
                   padding: 10px 16px;
                   border-radius: var(--md-shape-full);
                   text-decoration: none;
                   margin-bottom: 2px;
                   transition: background .15s;
                   font-size: 14px;
                   font-weight: {{ $item['active'] ? '600' : '500' }};
                   color: {{ $item['active'] ? 'var(--md-on-secondary-container)' : 'var(--md-on-surface-variant)' }};
                   background: {{ $item['active'] ? 'var(--md-secondary-container)' : 'transparent' }};
                   letter-spacing: .1px;
               "
               onmouseenter="if(!this.classList.contains('nav-active')) this.style.background='var(--md-surface-container-high)'"
               onmouseleave="if(!this.classList.contains('nav-active')) this.style.background='transparent'"
               {{ $item['active'] ? 'class=nav-active' : '' }}
            >
                <i class="bi {{ $item['icon'] }}"
                   style="
                       font-size: 18px;
                       color: {{ $item['active'] ? 'var(--md-on-secondary-container)' : 'var(--md-on-surface-variant)' }};
                       width: 20px;
                       text-align: center;
                       flex-shrink: 0;
                   "></i>
                {{ $item['label'] }}
                @if(!empty($item['badge']) && $item['badge'] > 0)
                    <span style="
                        margin-left: auto;
                        background: var(--md-error);
                        color: var(--md-on-error);
                        font-size: 11px;
                        font-weight: 600;
                        min-width: 20px;
                        height: 20px;
                        border-radius: 10px;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        padding: 0 5px;
                    ">{{ $item['badge'] > 99 ? '99+' : $item['badge'] }}</span>
                @endif
            </a>
        @endforeach

    </nav>

    {{-- ── Drawer Footer ────────────────────────────────────── --}}
    <div style="
        padding: 12px 16px;
        border-top: 1px solid var(--md-outline-variant);
        flex-shrink: 0;
    ">
        <p style="font-size:11px;color:var(--md-on-surface-variant);margin:0;letter-spacing:.3px;text-align:center">
            Marketing Flow v1.0
        </p>
    </div>

</div>
