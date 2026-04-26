<?php
namespace App\Http\Middleware\Aspects;
use Closure;
use Illuminate\Support\Facades\Log;

class BeforeAfterLogger
{
    public function handle($request, Closure $next)
    {
        $start = microtime(true);
        Log::channel('aop_console')->info(">>> [BEFORE] Processing Order...");

        $response = $next($request);

        $duration = round((microtime(true) - $start) * 1000, 2);
        Log::channel('aop_console')->info("<<< [AFTER] Completed in: {$duration}ms");

        return $response;
    }
}
