<?php

namespace App\Http\Middleware\Aspects;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class BeforeAfterLogger
{
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = microtime(true);

        $beforeMessage = sprintf(
            '[AOP][BEFORE] %s %s IP=%s',
            $request->method(),
            $request->path(),
            $request->ip()
        );

        Log::info($beforeMessage, [
            'method' => $request->method(),
            'path' => $request->path(),
            'ip' => $request->ip(),
        ]);

        $this->writeToConsole($beforeMessage);

        $response = $next($request);

        $afterMessage = sprintf(
            '[AOP][AFTER] %s %s STATUS=%s DURATION_MS=%s',
            $request->method(),
            $request->path(),
            $response->getStatusCode(),
            round((microtime(true) - $startedAt) * 1000, 2)
        );

        Log::info($afterMessage, [
            'method' => $request->method(),
            'path' => $request->path(),
            'status' => $response->getStatusCode(),
            'duration_ms' => round((microtime(true) - $startedAt) * 1000, 2),
        ]);

        $this->writeToConsole($afterMessage);

        return $response;
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
