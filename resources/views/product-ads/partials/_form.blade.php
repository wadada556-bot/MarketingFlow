{{-- ── Product ─────────────────────────────────────────────────── --}}
<div class="md-field">
    <label for="product_id">Produk <span style="color:var(--md-error)">*</span></label>
    <select class="form-select searchable-select @error('product_id') is-invalid @enderror"
            id="product_id" name="product_id" required>
        <option value="" selected disabled>— Pilih produk —</option>
        @forelse($products as $product)
            <option value="{{ $product->id }}"
                    {{ (old('product_id') ?? $productAd->product_id ?? '') == $product->id ? 'selected' : '' }}>
                {{ $product->parent_sku }}
            </option>
        @empty
            <option value="" disabled>Tidak ada produk tersedia.</option>
        @endforelse
    </select>
    @error('product_id')
        <div class="invalid-feedback">{{ $message }}</div>
    @enderror
</div>

{{-- ── Status + Testing Status ─────────────────────────────────── --}}
<div class="row g-3">
    <div class="col-6">
        <div class="md-field mb-0">
            <label for="status">Status Iklan <span style="color:var(--md-error)">*</span></label>
            @php $curStatus = old('status') ?? $productAd->status ?? ''; @endphp
            <select class="form-select @error('status') is-invalid @enderror"
                    id="status" name="status" required>
                <option value="" selected disabled>— Pilih —</option>
                <option value="active"    {{ $curStatus == 'active'    ? 'selected' : '' }}>Aktif</option>
                <option value="stopped"   {{ $curStatus == 'stopped'   ? 'selected' : '' }}>Dihentikan</option>
                <option value="completed" {{ $curStatus == 'completed' ? 'selected' : '' }}>Selesai</option>
            </select>
            @error('status') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
    </div>
    <div class="col-6">
        <div class="md-field mb-0">
            <label for="testing_status">Status Testing</label>
            @php $curTesting = old('testing_status') ?? $productAd->testing_status ?? ''; @endphp
            <select class="form-select @error('testing_status') is-invalid @enderror"
                    id="testing_status" name="testing_status">
                <option value="" selected disabled>— Pilih —</option>
                <option value="testing" {{ $curTesting == 'testing' ? 'selected' : '' }}>Testing</option>
                <option value="success" {{ $curTesting == 'success' ? 'selected' : '' }}>Berhasil</option>
                <option value="fail"    {{ $curTesting == 'fail'    ? 'selected' : '' }}>Gagal</option>
            </select>
            @error('testing_status') <div class="invalid-feedback">{{ $message }}</div> @enderror
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
    </style>
@endpush

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        /* ── Flatpickr date range ── */
        flatpickr('#testing_dates', {
            mode: 'range',
            enableTime: true,
            time_24hr: true,
            dateFormat: 'Y-m-d H:i',
            allowInput: false,
            disableMobile: true,
            onValueUpdate: function(selectedDates, dateStr, instance) {
                if (selectedDates.length === 2) {
                    const h = parseInt(instance.hourElement.value, 10);
                    const m = parseInt(instance.minuteElement.value, 10);
                    selectedDates[0].setHours(h, m, 0, 0);
                    selectedDates[1].setHours(h, m, 0, 0);
                    instance.setDate(selectedDates, false);
                }
            }
        });

        /* ── Choice chips toggle ── */
        document.querySelectorAll('.md-choice-chip').forEach(function (label) {
            var input = label.querySelector('input[type="checkbox"]');
            if (!input) return;
            label.addEventListener('click', function () {
                /* let default label↔input binding run first */
                setTimeout(function () {
                    label.classList.toggle('checked', input.checked);
                }, 0);
            });
        });
    });
    </script>
@endpush
