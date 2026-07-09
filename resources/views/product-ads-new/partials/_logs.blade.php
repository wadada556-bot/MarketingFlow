<form method="POST" action="{{ route('product-ads-new.logs.store', $ad) }}" class="mb-3">
    @csrf
    <div class="row g-2 align-items-end">
        <div class="col-12 col-sm-4">
            <label for="log_date" style="font-size:12px">Tanggal</label>
            <input type="date" class="form-control" id="log_date" name="action_date"
                   value="{{ now()->toDateString() }}" required>
        </div>
        <div class="col-12 col-sm-8">
            <label for="log_desc" style="font-size:12px">Catatan</label>
            <div class="d-flex gap-2">
                <input type="text" class="form-control" id="log_desc" name="description"
                       placeholder="mis. naikkan budget jadi 200rb" maxlength="2000" required>
                <button class="btn-md-filled" type="submit" style="white-space:nowrap">
                    <i class="bi bi-plus-lg"></i>
                </button>
            </div>
        </div>
    </div>
</form>

@if($logs->isEmpty())
    <p class="text-center py-3 mb-0" style="font-size:13px;color:var(--md-on-surface-variant)">
        Belum ada catatan untuk iklan ini.
    </p>
@else
    <ul class="list-unstyled mb-0">
        @foreach($logs as $log)
            <li class="d-flex gap-3 align-items-start py-2"
                style="border-top:1px solid var(--md-outline-variant)">
                <div style="flex-shrink:0;text-align:center">
                    <span class="badge rounded-pill bg-secondary bg-opacity-10 text-secondary border border-secondary"
                          style="font-size:11px" title="Tanggal catatan (bisa dipilih manual)">
                        {{ $log->action_date->translatedFormat('j M Y') }}
                    </span>
                    {{-- Jam:menit SEBENARNYA catatan dibuat (created_at), beda dari action_date
                         yang cuma date-picker tanpa jam & bisa diisi mundur/maju. --}}
                    <div style="font-size:10px;color:var(--md-on-surface-variant);margin-top:2px"
                         title="Dibuat pada {{ $log->created_at->translatedFormat('j M Y, H:i:s') }}">
                        {{ $log->created_at->format('H:i') }}
                    </div>
                </div>
                {{-- Mode lihat --}}
                <div class="js-log-display d-flex gap-2 align-items-start" style="flex:1">
                    <span style="font-size:13px;color:var(--md-on-surface);flex:1">{{ $log->description }}</span>
                    <button type="button" class="btn-md-icon" title="Edit catatan"
                            onclick="const li=this.closest('li');li.querySelector('.js-log-display').classList.add('d-none');li.querySelector('.js-log-edit').classList.remove('d-none');li.querySelector('.js-log-edit input[name=description]').focus();">
                        <i class="bi bi-pencil" style="font-size:12px"></i>
                    </button>
                    <form method="POST" action="{{ route('ad-logs.destroy', $log) }}"
                          onsubmit="return confirm('Hapus catatan ini?')">
                        @csrf @method('DELETE')
                        <button class="btn-md-icon" type="submit" title="Hapus catatan">
                            <i class="bi bi-x-lg" style="font-size:12px"></i>
                        </button>
                    </form>
                </div>

                {{-- Mode edit (tersembunyi sampai tombol pensil ditekan) --}}
                <form class="js-log-edit d-none" style="flex:1" method="POST"
                      action="{{ route('ad-logs.update', $log) }}">
                    @csrf @method('PUT')
                    <div class="d-flex gap-2 align-items-center">
                        <input type="date" class="form-control form-control-sm" name="action_date"
                               value="{{ $log->action_date->toDateString() }}" required style="max-width:150px">
                        <input type="text" class="form-control form-control-sm" name="description"
                               value="{{ $log->description }}" maxlength="2000" required>
                        <button class="btn-md-icon primary" type="submit" title="Simpan">
                            <i class="bi bi-check-lg" style="font-size:13px"></i>
                        </button>
                        <button type="button" class="btn-md-icon" title="Batal"
                                onclick="const li=this.closest('li');li.querySelector('.js-log-edit').classList.add('d-none');li.querySelector('.js-log-display').classList.remove('d-none');">
                            <i class="bi bi-x-lg" style="font-size:12px"></i>
                        </button>
                    </div>
                </form>
            </li>
        @endforeach
    </ul>
@endif
