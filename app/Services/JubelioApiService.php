<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class JubelioApiService
{
    private const LOGIN_URL = 'https://api2.jubelio.com/login';
    private const INV_URL   = 'https://open.jubelio.com/core-api/inventory/v2/';
    private const PAGE_SIZE = 100;

    private function login(): string
    {
        $response = Http::withoutVerifying()->timeout(30)->post(self::LOGIN_URL, [
            'email'    => config('services.jubelio.email'),
            'password' => config('services.jubelio.password'),
        ]);

        $response->throw();

        return $response->json('token');
    }

    /**
     * Fetch all HPP (last_cogs / average_cost) from Jubelio inventory.
     *
     * @return array<string, int>  [item_code => hpp_in_rupiah]
     * @throws \Exception
     */
    public function fetchAllHpp(): array
    {
        $token  = $this->login();
        $result = [];
        $page   = 1;
        $total  = null;

        while (true) {
            $body  = $this->fetchPage($token, $page);
            $items = $body['data'] ?? [];
            $total ??= (int) ($body['totalCount'] ?? 0);

            foreach ($items as $item) {
                $code = trim((string) ($item['item_code'] ?? ''));
                $hpp  = (int) round((float) ($item['last_cogs'] ?? $item['average_cost'] ?? 0));
                if ($code !== '') {
                    $result[$code] = $hpp;
                }
            }

            Log::info('[Jubelio] HPP sync progress', ['page' => $page, 'fetched' => count($result), 'total' => $total]);

            if (count($result) >= $total || empty($items)) {
                break;
            }

            $page++;
            sleep(1); // hindari rate-limit
        }

        Log::info('[Jubelio] HPP sync selesai', ['total_sku' => count($result)]);

        return $result;
    }

    /**
     * Fetch satu halaman inventory dengan retry otomatis (3x, backoff 5 detik).
     */
    private function fetchPage(string $token, int $page): array
    {
        $attempts = 3;
        $lastException = null;

        for ($i = 0; $i < $attempts; $i++) {
            if ($i > 0) {
                sleep(5 * $i); // backoff: 5s, 10s
            }
            try {
                $response = Http::withoutVerifying()->timeout(90)
                    ->withHeaders(['Authorization' => $token])
                    ->get(self::INV_URL, [
                        'page'           => $page,
                        'page_size'      => self::PAGE_SIZE,
                        'sort_direction' => 'NONE',
                    ]);

                $response->throw();
                return $response->json();
            } catch (\Throwable $e) {
                $lastException = $e;
                Log::warning("[Jubelio] Retry {$i}/{$attempts} page {$page}: {$e->getMessage()}");
            }
        }

        throw $lastException;
    }
}
