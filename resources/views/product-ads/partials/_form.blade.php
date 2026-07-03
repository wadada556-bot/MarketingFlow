{{-- Hidden: current ad id (for duplicate check exclusion on edit) --}}
<input type="hidden" id="current_ad_id" value="{{ $productAd->id ?? '' }}">

{{-- ── Product (remote search dari katalog jubelio_inventory) ────── --}}
@php
    $selectedSku = old('parent_sku')
        ?? ((isset($productAd) && $productAd->product) ? $productAd->product->parent_sku : null)
        ?? request('create_sku');
@endphp
<div class="md-field">
    <label for="ad_product_select">Produk <span style="color:var(--md-error)">*</span></label>
    <select class="form-select @error('parent_sku') is-invalid @enderror"
            id="ad_product_select" name="parent_sku" required>
        <option value="" disabled {{ $selectedSku ? '' : 'selected' }}>— Cari SKU produk —</option>
        @if($selectedSku)
            <option value="{{ $selectedSku }}" selected>{{ $selectedSku }}</option>
        @endif
    </select>
    <p style="font-size:11.5px;color:var(--md-on-surface-variant);margin:6px 0 0">
        Ketik untuk cari SKU dari katalog Jubelio.
    </p>
    @error('parent_sku')
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
</div>

{{-- ── Kategori Produk (dikembangkan / produk baru) ─────────────── --}}
@php
    $curCat = old('category_id')
        ?? ((isset($productAd) && $productAd->product) ? $productAd->product->category_id : null);
@endphp
<div class="md-field">
    <label for="category_id">Kategori Produk</label>
    <select class="form-select @error('category_id') is-invalid @enderror" id="category_id" name="category_id">
        <option value="">— Belum dikategorikan —</option>
        @foreach(($categories ?? []) as $cat)
            <option value="{{ $cat->id }}" {{ (string) $curCat === (string) $cat->id ? 'selected' : '' }}>
                {{ ucwords($cat->name) }}
            </option>
        @endforeach
    </select>
    @error('category_id')
        <div class="invalid-feedback d-block">{{ $message }}</div>
    @enderror
</div>

{{-- ── Status Iklan + Status Testing (chip radio terpisah) ─────── --}}
@php
    $statusOptions = [
        ['value' => 'active',    'label' => 'Aktif',      'cls' => 'si-active'],
        ['value' => 'stopped',   'label' => 'Dihentikan', 'cls' => 'si-stopped'],
        ['value' => 'completed', 'label' => 'Selesai',    'cls' => 'si-completed'],
    ];
    $testingOptions = [
        ['value' => 'testing', 'label' => 'Testing',  'cls' => 'ts-testing'],
        ['value' => 'success', 'label' => 'Berhasil', 'cls' => 'ts-success'],
        ['value' => 'fail',    'label' => 'Gagal',    'cls' => 'ts-fail'],
        ['value' => '',        'label' => 'Kosong',   'cls' => 'ts-none'],
    ];

    $isNew      = !($productAd->id ?? null);
    $curStatus  = old('status')         ?? ($productAd->status         ?? ($isNew ? 'active'   : ''));
    $curTesting = old('testing_status') ?? ($productAd->testing_status ?? ($isNew ? 'testing'  : ''));
@endphp

<div class="row g-3">
    <div class="col-12 col-sm-6">
        <div class="md-field mb-0">
            <p class="md-field-label" style="display:block;font-size:11px;font-weight:500;letter-spacing:.8px;text-transform:uppercase;color:var(--md-on-surface-variant);margin-bottom:10px">
                Status Iklan <span style="color:var(--md-error)">*</span>
            </p>
            <input type="hidden" name="status" id="hidden_status" value="{{ $curStatus }}">
            <div style="display:flex;flex-wrap:wrap;gap:6px">
                @foreach($statusOptions as $opt)
                <button type="button"
                        class="status-radio-chip {{ $opt['cls'] }} {{ $curStatus === $opt['value'] ? 'active' : '' }}"
                        data-group="status"
                        data-value="{{ $opt['value'] }}"
                        data-target="hidden_status"
                        onclick="selectRadioChip(this)">
                    {{ $opt['label'] }}
                </button>
                @endforeach
            </div>
            @error('status')
                <p style="font-size:12px;color:var(--md-error);margin-top:6px;margin-bottom:0">
                    <i class="bi bi-exclamation-circle me-1"></i>{{ $message }}
                </p>
            @enderror
        </div>
    </div>
    <div class="col-12 col-sm-6">
        <div class="md-field mb-0">
            <p class="md-field-label" style="display:block;font-size:11px;font-weight:500;letter-spacing:.8px;text-transform:uppercase;color:var(--md-on-surface-variant);margin-bottom:10px">
                Status Testing
            </p>
            <input type="hidden" name="testing_status" id="hidden_testing" value="{{ $curTesting }}">
            <div style="display:flex;flex-wrap:wrap;gap:6px">
                @foreach($testingOptions as $opt)
                <button type="button"
                        class="status-radio-chip {{ $opt['cls'] }} {{ $curTesting === $opt['value'] ? 'active' : '' }}"
                        data-group="testing"
                        data-value="{{ $opt['value'] }}"
                        data-target="hidden_testing"
                        onclick="selectRadioChip(this)">
                    {{ $opt['label'] }}
                </button>
                @endforeach
            </div>
            @error('testing_status')
                <p style="font-size:12px;color:var(--md-error);margin-top:6px;margin-bottom:0">
                    <i class="bi bi-exclamation-circle me-1"></i>{{ $message }}
                </p>
            @enderror
        </div>
    </div>
</div>

<hr class="md-divider" style="margin:20px 0">

{{-- ── Tanggal Testing ─────────────────────────────────────────── --}}
<div class="md-field">
    <label for="testing_dates">Rentang Tanggal Testing</label>
    <div class="input-group">
        <span class="input-group-text" style="background:var(--md-surface-container-low);border:1px solid var(--md-outline);border-right:none;border-radius:var(--md-shape-xs) 0 0 var(--md-shape-xs)">
            <i class="bi bi-calendar3" style="color:var(--md-on-surface-variant)"></i>
        </span>
        @php
            $oldDates = old('testing_dates');
            $dbDates  = (isset($productAd) && $productAd->testing_started_at && $productAd->testing_completed_at)
                ? $productAd->testing_started_at->format('Y-m-d H:i') . ' to ' . $productAd->testing_completed_at->format('Y-m-d H:i')
                : '';
        @endphp
        <input type="text"
               class="form-control bg-white @error('testing_dates') is-invalid @enderror"
               id="testing_dates" name="testing_dates"
               placeholder="Pilih rentang tanggal & waktu"
               value="{{ $oldDates ?: $dbDates }}"
               autocomplete="off"
               style="border-radius:0 var(--md-shape-xs) var(--md-shape-xs) 0 !important;border:1px solid var(--md-outline) !important;border-left:none !important">
        @error('testing_dates') <div class="invalid-feedback">{{ $message }}</div> @enderror
    </div>
    {{-- Date presets --}}
    <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap;margin-top:8px">
        <span style="font-size:11px;color:var(--md-on-surface-variant)">Cepat:</span>
        @foreach([3, 5, 7, 14, 30] as $days)
            <button type="button" class="date-preset-btn" onclick="setDatePreset({{ $days }})">{{ $days }} Hari</button>
        @endforeach
    </div>
</div>

<hr class="md-divider" style="margin:20px 0">

{{-- ── Target Toko ─────────────────────────────────────────────── --}}
<div>
    <p class="md-field-label" style="display:block;font-size:11px;font-weight:500;letter-spacing:.8px;text-transform:uppercase;color:var(--md-on-surface-variant);margin-bottom:10px">
        Target Toko <span style="color:var(--md-error)">*</span>
    </p>
    @if($stores->isNotEmpty())
        <div class="md-chip-choices @error('stores') is-invalid @enderror" id="storeChipsContainer">
            @foreach($stores as $store)
                @php
                    $isChecked = is_array(old('stores'))
                        ? in_array($store->id, old('stores'))
                        : (isset($productAd) && $productAd->stores->contains('id', $store->id));
                @endphp
                <label class="md-choice-chip {{ $isChecked ? 'checked' : '' }}"
                       for="store_{{ $store->id }}">
                    <i class="bi bi-check2 md-check-icon"></i>
                    <input class="visually-hidden" type="checkbox"
                           name="stores[]" value="{{ $store->id }}"
                           id="store_{{ $store->id }}"
                           {{ $isChecked ? 'checked' : '' }}>
                    {{ $store->name }}
                </label>
            @endforeach
        </div>
    @else
        <div style="text-align:center;padding:24px;background:var(--md-surface-container-low);border-radius:var(--md-shape-md)">
            <i class="bi bi-shop d-block mb-2" style="font-size:1.8rem;color:var(--md-outline)"></i>
            <span style="font-size:13px;color:var(--md-on-surface-variant)">Tidak ada toko tersedia.</span>
        </div>
    @endif
    @error('stores')
        <p style="font-size:12px;color:var(--md-error);margin-top:6px;margin-bottom:0">
            <i class="bi bi-exclamation-circle me-1"></i>{{ $message }}
        </p>
    @enderror
</div>

{{-- ── Duplicate Warning ────────────────────────────────────────── --}}
<div id="duplicate-warning"
     style="display:none;margin-top:16px;padding:10px 14px;
            background:var(--md-warning-container);
            border-radius:var(--md-shape-xs);
            border-left:3px solid var(--md-warning)">
    <p style="margin:0;font-size:13px;color:var(--md-on-warning-container)">
        <i class="bi bi-exclamation-triangle-fill me-2" style="color:var(--md-warning)"></i>
        <span id="duplicate-warning-text"></span>
    </p>
</div>

@push('styles')
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        .flatpickr-calendar { transform: scale(.92); transform-origin: top left; }
        .flatpickr-calendar.rightMost { transform-origin: top right; }
        .flatpickr-day.selected,
        .flatpickr-day.selected:hover {
            background: var(--md-primary) !important;
            border-color: var(--md-primary) !important;
        }
        .flatpickr-day.inRange {
            background: var(--md-primary-container) !important;
            box-shadow: none !important;
        }
        /* ── Status radio chips ── */
        .status-radio-chip {
            display: inline-flex;
            align-items: center;
            padding: 6px 14px;
            border-radius: 999px;
            border: 1.5px solid var(--md-outline-variant);
            background: var(--md-surface-container-low);
            color: var(--md-on-surface-variant);
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: background .15s, border-color .15s, color .15s;
        }
        .status-radio-chip:hover:not(.active) {
            background: var(--md-surface-container);
            border-color: var(--md-outline);
        }
        /* Status Iklan */
        .status-radio-chip.si-active.active    { background: var(--md-primary-container); color: var(--md-on-primary-container); border-color: var(--md-primary); }
        .status-radio-chip.si-stopped.active   { background: var(--md-surface-container-high); color: var(--md-on-surface); border-color: var(--md-outline); }
        .status-radio-chip.si-completed.active { background: var(--md-secondary-container, #e8def8); color: var(--md-on-secondary-container, #1d192b); border-color: var(--md-secondary, #6750a4); }
        /* Status Testing */
        .status-radio-chip.ts-testing.active { background: var(--md-primary-container); color: var(--md-on-primary-container); border-color: var(--md-primary); }
        .status-radio-chip.ts-success.active { background: rgba(29,158,117,.15); color: #156e52; border-color: #1D9E75; }
        .status-radio-chip.ts-fail.active    { background: var(--md-error-container); color: var(--md-on-error-container); border-color: var(--md-error); }
        .status-radio-chip.ts-none.active    { background: var(--md-surface-container-high); color: var(--md-on-surface-variant); border-color: var(--md-outline); }
        /* ── Date preset buttons ── */
        .date-preset-btn {
            font-size: 12px;
            padding: 3px 10px;
            border-radius: 999px;
            border: 1px solid var(--md-outline-variant);
            background: var(--md-surface-container-low);
            color: var(--md-on-surface-variant);
            cursor: pointer;
            transition: background .15s, border-color .15s, color .15s;
        }
        .date-preset-btn:hover {
            background: var(--md-primary-container);
            color: var(--md-on-primary-container);
            border-color: var(--md-primary);
        }
    </style>
@endpush

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
    (function () {
        var fp;

        document.addEventListener('DOMContentLoaded', function () {
            /* ── Flatpickr date range ── */
            fp = flatpickr('#testing_dates', {
                mode: 'range',
                enableTime: true,
                time_24hr: true,
                dateFormat: 'Y-m-d H:i',
                allowInput: false,
                disableMobile: true,
                onValueUpdate: function (selectedDates, dateStr, instance) {
                    if (selectedDates.length === 2) {
                        var h = parseInt(instance.hourElement.value, 10);
                        var m = parseInt(instance.minuteElement.value, 10);
                        selectedDates[0].setHours(h, m, 0, 0);
                        selectedDates[1].setHours(h, m, 0, 0);
                        instance.setDate(selectedDates, false);
                    }
                }
            });

            /* ── Store chips toggle + trigger duplicate check ── */
            document.querySelectorAll('.md-choice-chip').forEach(function (label) {
                var input = label.querySelector('input[type="checkbox"]');
                if (!input) return;
                label.addEventListener('click', function () {
                    setTimeout(function () {
                        label.classList.toggle('checked', input.checked);
                        scheduleDuplicateCheck();
                    }, 0);
                });
            });

            /* ── Product picker: remote search dari katalog jubelio ── */
            var catalogUrl = @json(route('product-ads.catalog-search'));
            var pselEl = document.getElementById('ad_product_select');
            if (pselEl && window.TomSelect) {
                new TomSelect(pselEl, {
                    valueField: 'sku',
                    labelField: 'label',
                    searchField: ['label', 'sku'],
                    create: false,
                    maxOptions: 30,
                    loadThrottle: 300,
                    load: function (query, callback) {
                        if (!query.length) return callback();
                        fetch(catalogUrl + '?q=' + encodeURIComponent(query))
                            .then(function (r) { return r.json(); })
                            .then(callback)
                            .catch(function () { callback(); });
                    },
                    onChange: function () { scheduleDuplicateCheck(); },
                });
            }
        });

        /* ── Status radio chips: select one per group, update hidden input ── */
        window.selectRadioChip = function (btn) {
            var group = btn.dataset.group;
            document.querySelectorAll('.status-radio-chip[data-group="' + group + '"]').forEach(function (c) {
                c.classList.remove('active');
            });
            btn.classList.add('active');
            document.getElementById(btn.dataset.target).value = btn.dataset.value;
        };

        /* ── Date presets ── */
        window.setDatePreset = function (days) {
            if (!fp) return;
            var today = new Date();
            today.setHours(0, 0, 0, 0);
            var end = new Date(today);
            end.setDate(today.getDate() + days - 1);
            fp.setDate([today, end]);
        };

        /* ── Duplicate check (debounced 300ms) ── */
        var dupTimer = null;
        function scheduleDuplicateCheck() {
            clearTimeout(dupTimer);
            dupTimer = setTimeout(doDuplicateCheck, 300);
        }

        function doDuplicateCheck() {
            var productSelect = document.getElementById('ad_product_select');
            var sku = productSelect ? productSelect.value : '';
            var storeIds = [];
            document.querySelectorAll('#storeChipsContainer input[type=checkbox]:checked').forEach(function (cb) {
                storeIds.push(cb.value);
            });
            var excludeIdEl = document.getElementById('current_ad_id');
            var excludeId = excludeIdEl ? excludeIdEl.value : '';
            var warn = document.getElementById('duplicate-warning');

            if (!sku || storeIds.length === 0) {
                warn.style.display = 'none';
                return;
            }

            var params = new URLSearchParams();
            params.append('parent_sku', sku);
            storeIds.forEach(function (id) { params.append('store_ids[]', id); });
            if (excludeId) params.append('exclude_id', excludeId);

            fetch('/product-ads/check-duplicate?' + params.toString(), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.duplicates && data.duplicates.length > 0) {
                    document.getElementById('duplicate-warning-text').textContent =
                        'Produk ini sudah punya iklan aktif di: ' + data.duplicates.join(', ');
                    warn.style.display = 'block';
                } else {
                    warn.style.display = 'none';
                }
            })
            .catch(function () { warn.style.display = 'none'; });
        }
    })();
    </script>
@endpush
