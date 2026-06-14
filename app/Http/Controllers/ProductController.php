<?php

namespace App\Http\Controllers;

use App\Models\Products;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ProductController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid input data',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $product = Products::create([
                'name' => $request->name,
                'description' => $request->description,
                'price' => $request->price,
                'stock' => $request->stock,
            ]);

            Cache::forget('products.available');
            Cache::forget("product.show.{$product->id}");

            return response()->json([
                'status' => 'success',
                'message' => 'Product added successfully',
                'data' => $product,
            ], 201);
        } catch (\Throwable $e) {
            Log::error('Product creation failed', [
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while saving the product',
            ], 500);
        }
    }

    public function index()
    {
        $startedAt = microtime(true);
        $cacheKey = 'products.available';
        $cacheHit = Cache::has($cacheKey);

        $products = Cache::remember(
            $cacheKey,
            now()->addMinutes(10),
            function () {
                return Products::query()
                    ->select(['id', 'name', 'price', 'stock'])
                    ->where('stock', '>', 0)
                    ->orderBy('name')
                    ->get();
            }
        );

        $durationMs = round((microtime(true) - $startedAt) * 1000, 2);

        Log::info(
            'Products index ' . ($cacheHit ? 'cache hit' : 'cache miss'),
            [
                'duration_ms' => $durationMs,
            ]
        );

        return response()->json([
            'status' => 'success',
            'data' => $products,
            'meta' => [
                'cache_hit' => $cacheHit,
                'duration_ms' => $durationMs,
            ],
        ], 200);
    }

    public function show($id)
    {
        $product = Cache::remember(
            "product.show.{$id}",
            now()->addMinutes(10),
            function () use ($id) {
                return Products::query()->whereKey($id)->first();
            }
        );

        if (! $product) {
            return response()->json([
                'status' => 'error',
                'message' => 'Product not found',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $product,
        ], 200);
    }
}
