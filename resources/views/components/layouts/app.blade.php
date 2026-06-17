<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
        <link href="{{ asset('css/md3-product-ads.css') }}" rel="stylesheet">

        @stack('styles')

        <title>@yield('title') - Marketing Flow</title>

        <style>
            /* ── Global Layout ─────────────────────────────── */
            body {
                background-color: var(--md-surface-container-low);
                overflow-x: hidden;
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                color: var(--md-on-surface);
            }
            #wrapper {
                display: flex;
                width: 100vw;
                min-height: 100vh;
                align-items: stretch;
            }

            /* ── Navigation Drawer (MD3) ───────────────────── */
            #sidebar-wrapper {
                min-width: 256px;
                max-width: 256px;
                background-color: var(--md-surface-container-lowest);
                border-right: 1px solid var(--md-outline-variant);
                transition: margin 0.25s cubic-bezier(.4,0,.2,1);
                z-index: 1040;
                display: flex;
                flex-direction: column;
            }
            #sidebar-wrapper.toggled {
                margin-left: -256px;
            }
            #page-content-wrapper {
                min-width: 0;
                width: 100%;
                transition: width 0.25s cubic-bezier(.4,0,.2,1);
            }

            /* ── Top App Bar (mobile only) ─────────────────── */
            .top-app-bar {
                display: none;
            }

            /* ── Page Content ──────────────────────────────── */
            .page-content {
                padding: 24px;
                max-width: 1280px;
                margin: 0 auto;
                width: 100%;
            }

            /* ── MD3 Page Header ───────────────────────────── */
            .md-page-header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 12px;
                margin-bottom: 24px;
                flex-wrap: wrap;
            }
            .md-page-header h1 {
                font-size: 28px;
                font-weight: 400;
                color: var(--md-on-surface);
                margin: 0;
                line-height: 1.2;
            }
            .md-page-header .subtitle {
                font-size: 14px;
                color: var(--md-on-surface-variant);
                margin: 4px 0 0;
            }

            /* ── MD3 Alert (Snackbar-style) ────────────────── */
            .md-alert {
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 14px 20px;
                border-radius: var(--md-shape-sm);
                font-size: 14px;
                font-weight: 500;
                margin-bottom: 16px;
                line-height: 20px;
            }
            .md-alert.success {
                background: var(--md-primary-container);
                color: var(--md-on-primary-container);
            }
            .md-alert.error {
                background: var(--md-error-container);
                color: var(--md-on-error-container);
            }
            .md-alert .md-alert-close {
                margin-left: auto;
                background: none;
                border: none;
                padding: 0;
                cursor: pointer;
                font-size: 16px;
                color: inherit;
                opacity: .7;
                line-height: 1;
            }
            .md-alert .md-alert-close:hover { opacity: 1; }

            /* ── Pagination MD3 ────────────────────────────── */
            .pagination .page-link {
                color: var(--md-primary);
                border-radius: var(--md-shape-xs);
                margin: 0 2px;
                font-size: 13px;
                border-color: var(--md-outline-variant);
            }
            .pagination .page-item.active .page-link {
                background: var(--md-primary);
                border-color: var(--md-primary);
            }

            @media (max-width: 992px) {
                #sidebar-wrapper {
                    margin-left: -256px;
                    position: fixed;
                    height: 100vh;
                    box-shadow: var(--md-elev-3);
                }
                #sidebar-wrapper.toggled {
                    margin-left: 0;
                }
                .top-app-bar {
                    display: flex;
                    height: 56px;
                    background: var(--md-surface-container-lowest);
                    border-bottom: 1px solid var(--md-outline-variant);
                    align-items: center;
                    padding: 0 8px;
                    gap: 4px;
                    position: sticky;
                    top: 0;
                    z-index: 100;
                }
            }
        </style>
    </head>
    <body>

        <div id="wrapper">
            @include('components.layouts.partials.sidebar')

            <div id="page-content-wrapper" class="d-flex flex-column">

                {{-- Top App Bar (mobile toggle) --}}
                <div class="top-app-bar">
                    <button class="btn-md-icon top-app-bar-toggle" id="sidebarToggle" title="Menu">
                        <i class="bi bi-list" style="font-size:20px"></i>
                    </button>
                    <span class="top-app-bar-title" id="topAppBarTitle"></span>
                </div>

                <main class="page-content flex-grow-1">
                    @yield('content')
                </main>

            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const sidebarToggle = document.getElementById('sidebarToggle');
                const sidebarWrapper = document.getElementById('sidebar-wrapper');

                if (sidebarToggle) {
                    sidebarToggle.addEventListener('click', function (e) {
                        e.preventDefault();
                        sidebarWrapper.classList.toggle('toggled');
                    });
                }

                // Close sidebar when clicking outside on mobile
                document.addEventListener('click', function (e) {
                    if (window.innerWidth <= 992 &&
                        sidebarWrapper && sidebarWrapper.classList.contains('toggled') &&
                        !sidebarWrapper.contains(e.target) &&
                        e.target !== sidebarToggle) {
                        sidebarWrapper.classList.remove('toggled');
                    }
                });
            });
        </script>
        @stack('scripts')
    </body>
</html>