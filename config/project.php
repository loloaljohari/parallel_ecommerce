<?php

return [
    'servers' => array_values(array_filter(array_map(
        static fn (string $server): string => trim($server),
        explode(
            ',',
            env(
                'LOAD_SERVERS',
                env('APP_URL', 'http://127.0.0.1:8000')
            )
        )
    ))),

    'max_concurrent_orders' => (int) env('MAX_CONCURRENT_ORDERS', 1000),
    'stress_test_requests' => (int) env('STRESS_TEST_REQUESTS', 1000),
    'benchmark_runs' => (int) env('BENCHMARK_RUNS', 1000),
];
