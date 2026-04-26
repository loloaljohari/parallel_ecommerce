<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Products;
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
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $product = Products::create([
                'name' => $request->name,
                'description' => $request->description,
                'price' => $request->price,
                'stock' => $request->stock,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Product added successfully',
                'data' => $product
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while saving the product, please try again later'
            ], 500);
        }}
    public function index() {

    return Products::where('stock', '>', 0)->get();
}

public function show($id) {
    $product = Products::find($id);
    if(!$product) {
        return response()->json(['message' => 'Product not found'], 404);
    }
    return $product;
}
}
