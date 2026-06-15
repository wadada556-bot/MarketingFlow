<x-layouts.app title="Daftar Kampanye Pemasaran">

    <form action="{{ route('campaigns.index') }}" method="GET" class="filter-bar">
        <select name="status">
            <option value="">Semua Status</option>
            <option value="active" {{ request('status') == 'active' ? 'selected' : '' }}>Aktif</option>
            <option value="stopped" {{ request('status') == 'stopped' ? 'selected' : '' }}>Dihentikan / Selesai</option>
        </select>

        <select name="type">
            <option value="">Semua Tipe</option>
            <option value="ads marketing" {{ request('type') == 'ads marketing' ? 'selected' : '' }}>Ads Marketing</option>
            <option value="bs strategy" {{ request('type') == 'bs strategy' ? 'selected' : '' }}>BS Strategy</option>
        </select>

        <button type="submit">Filter Data</button>
        <a href="{{ route('campaigns.index') }}" class="btn-reset">Reset</a>
    </form>

    <table>
        <thead>
            <tr>
                <th>Produk</th>
                <th>Kategori</th>
                <th>Tipe</th>
                <th>Toko</th>
                <th>Tgl Mulai</th>
                <th>Status</th>
                <th style="width: 35%;">Step Terakhir</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($campaigns as $campaign)
                <tr>
                    <td>
                        <strong>{{ $campaign->product->parent_sku }}</strong><br>
                    </td>
                    <td>{{ $campaign->product->category->name ?? '-' }}</td>
                    
                    <td>
                        <x-badge color="blue">
                            {{ ucwords($campaign->type) }}
                        </x-badge>
                    </td>

                    <td>
                        @foreach ($campaign->stores as $store)
                            <x-badge color="gray">{{ $store->name }}</x-badge>
                        @endforeach
                    </td>

                    <td>{{ \Carbon\Carbon::parse($campaign->start_date)->format('d M Y') }}</td>
                    
                    <td>
                        <x-badge :color="$campaign->status == 'active' ? 'green' : 'red'">
                            {{ ucfirst($campaign->status) }}
                        </x-badge>
                    </td>

                    <td>
                        @if ($campaign->latestHistory)
                            <strong>Step {{ $campaign->latestHistory->step_number }}:</strong> 
                            {{ \Illuminate\Support\Str::limit($campaign->latestHistory->description, 80) }}
                        @else
                            <em style="color: #9ca3af;">Belum ada riwayat</em>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" style="text-align: center; padding: 2rem;">Tidak ada data ditemukan.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div style="margin-top: 1.5rem;">
        {{ $campaigns->links() }}
    </div>

</x-layouts.app>