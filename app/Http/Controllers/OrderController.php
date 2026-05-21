<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Products;
use Illuminate\Support\Facades\DB;
use App\Jobs\ProcessOrderDetails;
use Exception;

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
            $itemsData = [];

            if ($request->items) {
                foreach ($request->items as $item) {
                    $product = Products::where('id', $item['product_id'])->lockForUpdate()->first();

                    if (!$product) {
                        throw new Exception("Product ID {$item['product_id']} not found.");
                    }

                    if ($product->stock < $item['quantity']) {
                        throw new Exception("Insufficient stock for product ID {$item['product_id']}.");
                    }

                    $totalPrice += $product->price * $item['quantity'];

                    $product->decrement('stock', $item['quantity']);

                    $itemsData[] = [
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'price' => $product->price
                    ];
                }
            }

            $order = $user->orders()->create([
                'total_price' => $totalPrice,
                'status' => 'pending'
            ]);

            foreach ($itemsData as $data) {
                $order->items()->create($data);
            }

            ProcessOrderDetails::dispatch($order)->afterCommit();

            return response()->json([
                'status' => 'success',
                'message' => 'Order processed safely',
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
