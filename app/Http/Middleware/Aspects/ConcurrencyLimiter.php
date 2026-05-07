<?php
namespace App\Http\Middleware\Aspects;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
class ConcurrencyLimiter
{

 public function handle($request, $next) {
    $max = 10;
    $current = \Cache::increment('active_orders');

    if ($current > $max) {
        Cache::decrement('active_orders');
        Log::channel('aop_console')->warning("Capacity Control: REJECTED (Overload)");
        return response()->json(['message' => 'Server busy, try again later'], 503);
    }

    try {
        return $next($request);
    } finally {
        Cache::decrement('active_orders');
    }
}
}
