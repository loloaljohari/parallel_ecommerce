<?php

namespace App\Http\Middleware\Aspects;

use App\Models\Products;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class StockGuard
{
    public function handle(Request $request, Closure $next): Response
    {
        $items = $request->input('items', []);

        $this->writeToConsole(sprintf(
            '[AOP][STOCK][CHECK] %s %s items=%d',
            $request->method(),
            $request->path(),
            is_array($items) ? count($items) : 0
        ));

        foreach ($items as $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            $quantity = (int) ($item['quantity'] ?? 0);

            $product = Products::query()->whereKey($productId)->first();

            if (! $product) {
                $message = "Product not found: {$productId}";

                Log::error($message);
                $this->writeToConsole('[AOP][STOCK][FAIL] ' . $message);

                return response()->json([
                    'status' => 'error',
                    'message' => "Product {$productId} not found",
                ], 404);
            }

            if ($product->stock < $quantity) {
                $message = "Out of stock for product {$productId}";

                Log::warning($message, [
                    'requested' => $quantity,
                    'available' => $product->stock,
                ]);

                $this->writeToConsole(sprintf(
                    '[AOP][STOCK][FAIL] product=%d requested=%d available=%d',
                    $productId,
                    $quantity,
                    $product->stock
                ));

                return response()->json([
                    'status' => 'error',
                    'message' => "Out of stock for product {$productId}",
                ], 400);
            }

            $this->writeToConsole(sprintf(
                '[AOP][STOCK][PASS] product=%d requested=%d available=%d',
                $productId,
                $quantity,
                $product->stock
            ));
        }

        return $next($request);
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
