@if(session('success'))
    <div class="md-alert success" role="alert" id="md-alert-success">
        <i class="bi bi-check-circle" style="font-size:18px;flex-shrink:0"></i>
        <span>{{ session('success') }}</span>
        <button class="md-alert-close" onclick="this.closest('.md-alert').remove()" aria-label="Tutup">
            <i class="bi bi-x"></i>
        </button>
    </div>
@endif

@if(session('error'))
    <div class="md-alert error" role="alert" id="md-alert-error">
        <i class="bi bi-exclamation-octagon" style="font-size:18px;flex-shrink:0"></i>
        <span>{{ session('error') }}</span>
        <button class="md-alert-close" onclick="this.closest('.md-alert').remove()" aria-label="Tutup">
            <i class="bi bi-x"></i>
        </button>
    </div>
@endif
