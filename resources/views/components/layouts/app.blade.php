<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
        <link rel="dns-prefetch" href="https://cdn.jsdelivr.net">

        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
        <link href="{{ asset('css/md3-product-ads.css') }}" rel="stylesheet">
        <link href="{{ asset('css/app-layout.css') }}" rel="stylesheet">

        @stack('styles')

        <title>@yield('title') - Marketing Flow</title>
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