<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Products;
use Illuminate\Support\Facades\DB;
use App\Jobs\ProcessOrderDetails;

class OrderController extends Controller
{
    public function store(Request $request)
    {
        return DB::transaction(function () use ($request) {

            $user = \App\Models\User::first();
            if (!$user) {
                return response()->json(['message' => 'User not found'], 500);
            }

            $totalPrice = 0;
            if ($request->items) {
                foreach ($request->items as $item) {
                    $product = Products::find($item['product_id']);
                    if ($product) {
                        $totalPrice += $product->price * $item['quantity'];
                    }
                }
            }

            $order = $user->orders()->create([
                'total_price' => $totalPrice,
                'status' => 'pending'
            ]);

            foreach ($request->items as $item) {
                $order->items()->create([
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'price' => Products::find($item['product_id'])->price ?? 0
                ]);
            }

            ProcessOrderDetails::dispatch($order)->afterCommit();

            return response()->json([
                'status' => 'success',
                'message' => 'Order processed and added to queue',
                'order_id' => $order->id
            ], 201);
        });
    }
    public function userOrders()
    {
        $orders = auth()->user()->orders()->with('items.product')->get();

        return response()->json([
            'status' => 'success',
            'data' => $orders
        ], 200);
    }
}
