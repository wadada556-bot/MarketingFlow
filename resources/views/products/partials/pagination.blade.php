@php
    $last    = $catalog->lastPage();
    $current = $catalog->currentPage();

    // Daftar nomor halaman yg ditampilkan: 1, sekitar current, dan last —
    // celah di antaranya diisi tanda "…".
    $pages = collect([1]);
    for ($p = max(2, $current - 1); $p <= min($last - 1, $current + 1); $p++) {
        $pages->push($p);
    }
    if ($last > 1) {
        $pages->push($last);
    }
    $pages = $pages->unique()->sort()->values();
@endphp

<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mt-4 products-pagination-bar">
    <p class="mb-0" style="font-size:13px;color:var(--md-on-surface-variant)">
        Total baris:
        <strong style="color:var(--md-on-surface)">{{ number_format($catalog->total(), 0, ',', '.') }}</strong>
    </p>

    <div class="d-flex align-items-center gap-3">
        <nav class="products-pager" aria-label="Navigasi halaman">
            @if ($catalog->onFirstPage())
                <span class="products-pager-btn disabled"><i class="bi bi-chevron-left"></i></span>
            @else
                <a href="{{ $catalog->previousPageUrl() }}" class="products-pager-btn"><i class="bi bi-chevron-left"></i></a>
            @endif

            @php $prevPage = null; @endphp
            @foreach ($pages as $p)
                @if ($prevPage !== null && $p - $prevPage > 1)
                    <span class="products-pager-ellipsis">&hellip;</span>
                @endif
                <a href="{{ $catalog->url($p) }}" class="products-pager-btn {{ $p == $current ? 'active' : '' }}">{{ $p }}</a>
                @php $prevPage = $p; @endphp
            @endforeach

            @if ($catalog->hasMorePages())
                <a href="{{ $catalog->nextPageUrl() }}" class="products-pager-btn"><i class="bi bi-chevron-right"></i></a>
            @else
                <span class="products-pager-btn disabled"><i class="bi bi-chevron-right"></i></span>
            @endif
        </nav>

        <div class="products-perpage" id="products-perpage">
            <button type="button" class="products-perpage-btn" id="products-perpage-toggle">
                {{ $perPage }}/Halaman <i class="bi bi-chevron-down"></i>
            </button>
            <div class="products-perpage-menu" id="products-perpage-menu">
                @foreach ([10, 20, 50, 100] as $opt)
                    <label class="products-perpage-option">
                        <input type="radio" name="per_page_opt" value="{{ $opt }}" class="js-perpage-radio" @checked($perPage == $opt)>
                        {{ $opt }}/Halaman
                    </label>
                @endforeach
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const wrap   = document.getElementById('products-perpage');
    const toggle = document.getElementById('products-perpage-toggle');
    const menu   = document.getElementById('products-perpage-menu');
    if (!wrap || wrap.dataset.bound) return;
    wrap.dataset.bound = '1';

    toggle.addEventListener('click', function (e) {
        e.stopPropagation();
        menu.classList.toggle('open');
    });
    document.addEventListener('click', function (e) {
        if (!wrap.contains(e.target)) menu.classList.remove('open');
    });

    wrap.querySelectorAll('.js-perpage-radio').forEach(function (radio) {
        radio.addEventListener('change', function () {
            const url = new URL(window.location.href);
            url.searchParams.set('per_page', radio.value);
            url.searchParams.delete('page');
            window.location.href = url.toString();
        });
    });
})();
</script>
