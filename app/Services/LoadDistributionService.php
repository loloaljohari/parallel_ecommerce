<?php

namespace App\Services;

use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class LoadDistributionService
{
    public function servers(): array
    {
        $servers = config('project.servers', []);

        if (is_array($servers) && count($servers) > 0) {
            return array_values(array_filter($servers, static fn ($server) => is_string($server) && trim($server) !== ''));
        }

        return [
            'http://127.0.0.1:8000',
            'http://127.0.0.1:8001',
            'http://127.0.0.1:8002',
        ];
    }

    public function pickServer(int $taskNumber): string
    {
        $servers = $this->servers();

        return $servers[($taskNumber - 1) % count($servers)];
    }

    public function simulate(int $tasks = 5): array
    {
        $servers = $this->servers();

        if ($servers === []) {
            return ['No servers configured for load distribution.'];
        }

        $startedAt = microtime(true);

        $responses = Http::pool(function (Pool $pool) use ($tasks, $servers) {
            $calls = [];

            for ($task = 1; $task <= $tasks; $task++) {
                $server = $this->pickServer($task);

                $calls[] = $pool
                    ->timeout(5)
                    ->acceptJson()
                    ->get(rtrim($server, '/') . '/process', [
                        'task' => $task,
                    ]);
            }

            return $calls;
        });

        $elapsedMs = round((microtime(true) - $startedAt) * 1000, 2);
        $results = [];

        foreach ($responses as $index => $response) {
            $task = $index + 1;
            $server = $this->pickServer($task);

            if ($response instanceof Response && $response->successful()) {
                $nodePort = $response->json('node_port')
                    ?? (string) parse_url($server, PHP_URL_PORT)
                    ?? 'unknown';

                $results[] = "Task {$task} -> Requested {$server} -> Handled by node on port {$nodePort}";
            } else {
                $status = $response instanceof Response ? $response->status() : 'error';
                $results[] = "Task {$task} -> Failed on {$server} (HTTP {$status})";
            }
        }

        $results[] = "Round completed in {$elapsedMs} ms";

        return $results;
    }
}
