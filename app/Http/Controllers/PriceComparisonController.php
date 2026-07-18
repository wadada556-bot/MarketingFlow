<?php

namespace App\Http\Controllers;

use App\Exports\PriceComparisonExport;
use App\Services\PriceComparisonService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Maatwebsite\Excel\Facades\Excel;

class PriceComparisonController extends Controller
{
    public function index(Request $request, PriceComparisonService $service)
    {
        $search   = trim((string) $request->input('search'));
        $diffOnly = $request->boolean('diff_only');
        $sortKey  = (string) $request->input('sort', 'selisih');
        $sortDir  = $request->input('dir') === 'asc' ? 'asc' : 'desc';
        $perPage  = 50;
        $page     = (int) $request->input('page', 1);

        $rows   = $service->buildRows($search, $diffOnly, $sortKey, $sortDir);
        $stores = $service->stores();

        $paginated = new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('price-comparison.index', [
            'rows' => $paginated,
            'stores' => $stores,
            'search' => $search,
            'diffOnly' => $diffOnly,
            'sortKey' => $sortKey,
            'sortDir' => $sortDir,
            'totalAll' => $service->totalSkuCount(),
        ]);
    }

    public function export(Request $request, PriceComparisonService $service)
    {
        $search   = trim((string) $request->input('search'));
        $diffOnly = $request->boolean('diff_only');

        $rows   = $service->buildRows($search, $diffOnly);
        $stores = $service->stores();

        $filename = 'perbandingan_harga_promo'
            . ($diffOnly ? '_selisih' : '')
            . '_' . now()->format('Y-m-d_His') . '.xlsx';

        return Excel::download(new PriceComparisonExport($rows, $stores), $filename);
    }
}
