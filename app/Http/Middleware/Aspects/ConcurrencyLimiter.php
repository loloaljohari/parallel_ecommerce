<?php
class ConcurrencyLimiter
{

 public function handle($request, $next) {
    $max = 10;
    $current = \Cache::increment('active_orders');

    if ($current > $max) {
        Cache::decrement('active_orders');
        Log::channel('aop')->warning("Capacity Control: REJECTED (Overload)");
        return response()->json(['message' => 'Server busy, try again later'], 503);
    }

    try {
        return $next($request);
    } finally {
        Cache::decrement('active_orders');
    }
}
}
