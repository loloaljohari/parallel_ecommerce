<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessOrderDetails;
use App\Models\Products;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class OrderController extends Controller
{
    public function store(Request $request, PaymentService $paymentService)
    {
        $validated = $request->validate([
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'simulate_payment_failure' => 'sometimes|boolean',
        ]);

        try {
            $result = Cache::lock('checkout:orders', 15)->block(5, function () use ($request, $validated, $paymentService) {
                return DB::transaction(function () use ($request, $validated, $paymentService) {
                    $user = $request->user();

                    if (! $user) {
                        throw new \RuntimeException('Unauthenticated user.');
                    }

                    $order = $user->orders()->create([
                        'total_price' => 0,
                        'status' => 'pending',
                        'payment_status' => 'pending',
                        'payment_reference' => null,
                    ]);

                    $totalPrice = 0.0;
                    $touchedProductIds = [];

                    foreach ($validated['items'] as $item) {
                        $product = Products::query()
                            ->whereKey($item['product_id'])
                            ->lockForUpdate()
                            ->first();

                        if (! $product) {
                            throw new \RuntimeException("Product ID {$item['product_id']} not found.");
                        }

                        if ($product->stock < $item['quantity']) {
                            throw new \RuntimeException("Insufficient stock for product ID {$item['product_id']}.");
                        }

                        $subtotal = (float) $product->price * (int) $item['quantity'];
                        $totalPrice += $subtotal;

                        $product->decrement('stock', (int) $item['quantity']);
                        $touchedProductIds[] = $product->id;

                        $order->items()->create([
                            'product_id' => $product->id,
                            'quantity' => $item['quantity'],
                            'price' => $product->price,
                        ]);
                    }

                    $payment = $paymentService->charge($user, $totalPrice, [
                        'simulate_failure' => (bool) ($validated['simulate_payment_failure'] ?? false),
                        'order_id' => $order->id,
                    ]);

                    $order->update([
                        'total_price' => $totalPrice,
                        'status' => 'paid',
                        'payment_status' => $payment['status'],
                        'payment_reference' => $payment['reference'],
                    ]);

                    ProcessOrderDetails::dispatch($order->fresh())->afterCommit();

                    return [$order->load('items.product'), $touchedProductIds, $payment];
                });
            });

            [$order, $touchedProductIds, $payment] = $result;

            Cache::forget('products.available');

            foreach ($touchedProductIds as $productId) {
                Cache::forget("product.show.{$productId}");
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Order processed safely with distributed lock and transaction',
                'payment' => $payment,
                'order_id' => $order->id,
                'data' => $order,
            ], 201);
        } catch (Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function userOrders(Request $request)
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $orders = $user->orders()->with('items.product')->latest()->get();

        return response()->json([
            'status' => 'success',
            'data' => $orders,
        ], 200);
    }
}
