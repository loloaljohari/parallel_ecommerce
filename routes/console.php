<?php

use App\Jobs\DailySalesAudit;
use App\Models\Products;
use App\Services\LoadDistributionService;
use GuzzleHttp\TransferStats;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Requirement #5 - Multiple Instances Load Distribution
|--------------------------------------------------------------------------
*/
Artisan::command('project:load-distribution {--tasks=5}', function () {
    $tasks = max(1, (int) $this->option('tasks'));
    $service = app(LoadDistributionService::class);

    foreach ($service->simulate($tasks) as $line) {
        $this->line($line);
    }
})->purpose('Simulate distribution across multiple app instances');

/*
|--------------------------------------------------------------------------
| Requirement #9 - Concurrent Stress Test
|--------------------------------------------------------------------------
| This version is designed to reduce stock in the database by calling
| POST /api/orders, not GET /api/products.
*/
Artisan::command(
    'project:stress-test {--requests=100} {--endpoint=/api/orders} {--token=} {--product-id=} {--quantity=1}',
    function () {
        $requests = max(1, (int) $this->option('requests'));
        $endpoint = '/' . ltrim((string) $this->option('endpoint'), '/');
        $token = trim((string) $this->option('token'));
        $quantity = max(1, (int) $this->option('quantity'));
        $explicitProductId = $this->option('product-id');

        $servers = config('project.servers', [rtrim((string) config('app.url', 'http://127.0.0.1:8000'), '/')]);
        $servers = array_values(array_filter($servers, static fn ($server) => is_string($server) && trim($server) !== ''));

        if ($endpoint === '/api/orders' && $token === '') {
            $this->error('A Bearer token is required for /api/orders.');
            return 1;
        }

        $availableProductIds = Products::query()
            ->where('stock', '>', 0)
            ->pluck('id')
            ->all();

        if ($endpoint === '/api/orders' && empty($availableProductIds)) {
            $this->error('No products with stock > 0 were found in the database.');
            return 1;
        }

        $startedAt = microtime(true);
        $durations = [];
        $responses = [];

        $makeRequest = function (int $index) use (
            $servers,
            $endpoint,
            $token,
            $quantity,
            $explicitProductId,
            $availableProductIds,
            &$durations
        ) {
            $server = $servers[$index % count($servers)];
            $productId = $explicitProductId !== null
                ? (int) $explicitProductId
                : (int) $availableProductIds[$index % count($availableProductIds)];

            $payload = [
                'items' => [
                    [
                        'product_id' => $productId,
                        'quantity' => $quantity,
                    ],
                ],
            ];

            $request = Http::timeout(15)
                ->acceptJson()
                ->withToken($token)
                ->withOptions([
                    'on_stats' => function (TransferStats $stats) use (&$durations, $index): void {
                        $durations[$index] = round($stats->getTransferTime() * 1000, 2);
                    },
                ]);

            return match (true) {
                str_starts_with($endpoint, '/api/orders') => $request->post(rtrim($server, '/') . $endpoint, $payload),
                default => $request->get(rtrim($server, '/') . $endpoint),
            };
        };

        if (count($servers) > 1) {
            $responses = Http::pool(function (Pool $pool) use (
                $requests,
                $servers,
                $endpoint,
                $token,
                $quantity,
                $explicitProductId,
                $availableProductIds,
                &$durations
            ) {
                $calls = [];

                for ($i = 0; $i < $requests; $i++) {
                    $server = $servers[$i % count($servers)];
                    $productId = $explicitProductId !== null
                        ? (int) $explicitProductId
                        : (int) $availableProductIds[$i % count($availableProductIds)];

                    $payload = [
                        'items' => [
                            [
                                'product_id' => $productId,
                                'quantity' => $quantity,
                            ],
                        ],
                    ];

                    $pending = $pool->timeout(15)->acceptJson()->withToken($token)->withOptions([
                        'on_stats' => function (TransferStats $stats) use (&$durations, $i): void {
                            $durations[$i] = round($stats->getTransferTime() * 1000, 2);
                        },
                    ]);

                    $calls[] = match (true) {
                        str_starts_with($endpoint, '/api/orders') => $pending->post(rtrim($server, '/') . $endpoint, $payload),
                        default => $pending->get(rtrim($server, '/') . $endpoint),
                    };
                }

                return $calls;
            });
        } else {
            for ($i = 0; $i < $requests; $i++) {
                $responses[] = $makeRequest($i);
            }
        }

        $elapsedMs = round((microtime(true) - $startedAt) * 1000, 2);

        $successful = 0;
        $failed = 0;

        foreach ($responses as $response) {
            if ($response instanceof Response && $response->successful()) {
                $successful++;
            } else {
                $failed++;
            }
        }

        $durations = array_values(array_filter($durations, static fn ($value) => is_numeric($value)));
        $averageResponseTime = count($durations) > 0
            ? round(array_sum($durations) / count($durations), 2)
            : round($elapsedMs / $requests, 2);

        $systemCrashed = $successful === 0;

        $this->newLine();
        $this->info('===== Stress Test Report =====');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Requests', $requests],
                ['Successful Requests', $successful],
                ['Failed Requests', $failed],
                ['Average Response Time (ms)', $averageResponseTime],
                ['System Crashed?', $systemCrashed ? 'YES' : 'NO'],
            ]
        );

        $this->newLine();
        $this->line('Important: /api/products is read-only. Use /api/orders to reduce stock in the database.');
    }
)->purpose('Run concurrent stress test');

/*
|--------------------------------------------------------------------------
| Requirement #10 - Benchmark Before / After
|--------------------------------------------------------------------------
*/
Artisan::command('project:benchmark {--runs=10} {--endpoint=/api/products}', function () {
    $runs = max(1, (int) $this->option('runs'));
    $endpoint = '/' . ltrim((string) $this->option('endpoint'), '/');
    $url = rtrim(config('app.url'), '/') . $endpoint;

    $measure = function (bool $clearCache) use ($url): float {
        if ($clearCache) {
            Cache::forget('products.available');
        }

        $start = microtime(true);
        Http::timeout(10)->acceptJson()->get($url);

        return round((microtime(true) - $start) * 1000, 2);
    };

    $before = [];
    for ($i = 0; $i < $runs; $i++) {
        $before[] = $measure(true);
    }

    $after = [];
    for ($i = 0; $i < $runs; $i++) {
        $after[] = $measure(false);
    }

    $beforeAvg = round(array_sum($before) / count($before), 2);
    $afterAvg = round(array_sum($after) / count($after), 2);
    $improvement = $beforeAvg > 0
        ? round((($beforeAvg - $afterAvg) / $beforeAvg) * 100, 2)
        : 0;

    $this->newLine();
    $this->info('===== Benchmark Report =====');
    $this->table(
        ['Phase', 'Average Response Time (ms)'],
        [
            ['Before Optimization', $beforeAvg],
            ['After Optimization', $afterAvg],
            ['Improvement %', $improvement . '%'],
        ]
    );

    $this->line('Likely bottleneck before optimization: direct database access when cache was cold.');
    $this->newLine();
})->purpose('Benchmark before and after optimization');

/*
|--------------------------------------------------------------------------
| Requirement #4 - Daily Batch Processing
|--------------------------------------------------------------------------
*/
Schedule::job(new DailySalesAudit())
    ->dailyAt('23:55')
    ->withoutOverlapping();
