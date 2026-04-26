<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Products;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function store(Request $request)
    {
        // الـ Middleware (StockGuard) خصم ا
        return DB::transaction(function () use ($request) {

            $user = \App\Models\User::first();

            if (!$user) {
                return response()->json(['message' => 'No user found in DB'], 500);
            }

            $order = $user->orders()->create([
                'total_price' => $request->items ? array_sum(array_map(function ($item) {
                    $product = Products::find($item['product_id']);
                    return $product ? $product->price * $item['quantity'] : 0;
                }, $request->items)) : 0,
                'status' => 'completed'
            ]);


            foreach ($request->items as $item) {
                $order->items()->create([
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'price' => 0
                ]);
            }

            return response()->json([
                'message' => 'Order stored successfully',
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
