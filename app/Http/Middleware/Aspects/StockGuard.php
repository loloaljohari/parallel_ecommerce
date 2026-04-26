<?php
namespace App\Http\Middleware\Aspects;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StockGuard
{
    public function handle($request, Closure $next)
    {
        $items = $request->input('items', []);

        foreach ($items as $item) {
            $productId = $item['product_id'];
            $quantity = $item['quantity'];

            $affected = DB::table('products')
                ->where('id', $productId)
                ->where('stock', '>=', $quantity)
                ->decrement('stock', $quantity);

            if ($affected === 0) {
                Log::channel('aop_console')->error("!!! [REJECTED] Race Condition Blocked - No Stock for Product: " . $productId);

                return response()->json([
                    'status' => 'error',
                    'message' => 'Out of stock for product ' . $productId
                ], 400);
            }
        }

        return $next($request);
    }
}
