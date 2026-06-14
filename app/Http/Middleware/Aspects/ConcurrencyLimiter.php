<?php

namespace App\Http\Middleware\Aspects;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ConcurrencyLimiter
{
    public function handle(Request $request, Closure $next): Response
    {
        $max = (int) config('project.max_concurrent_orders', 10);
        $key = 'aop:active_orders';

        if (! Cache::has($key)) {
            Cache::put($key, 0, now()->addMinutes(5));
        }

        $current = (int) Cache::increment($key);
        Cache::put($key, $current, now()->addMinutes(5));

        $this->writeToConsole(sprintf(
            '[AOP][CONCURRENCY][IN] path=%s active=%d limit=%d',
            $request->path(),
            $current,
            $max
        ));

        if ($current > $max) {
            Cache::decrement($key);

            $message = 'Capacity Control: request rejected because the concurrent limit was exceeded.';

            Log::warning($message, [
                'active' => $current,
                'limit' => $max,
                'path' => $request->path(),
            ]);

            $this->writeToConsole(sprintf(
                '[AOP][CONCURRENCY][REJECT] path=%s active=%d limit=%d',
                $request->path(),
                $current,
                $max
            ));

            return response()->json([
                'message' => 'Server busy, try again later',
            ], 503);
        }

        try {
            $response = $next($request);

            $this->writeToConsole(sprintf(
                '[AOP][CONCURRENCY][OUT] path=%s status=%d active=%d',
                $request->path(),
                $response->getStatusCode(),
                $current
            ));

            return $response;
        } finally {
            Cache::decrement($key);
        }
    }

    private function writeToConsole(string $message): void
    {
        if (defined('STDOUT')) {
            fwrite(STDOUT, $message . PHP_EOL);
        } else {
            error_log($message);
        }
    }
}
