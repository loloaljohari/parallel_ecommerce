<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{id}', [ProductController::class, 'show']);

Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::post('/products', [ProductController::class, 'store'])
        ->middleware(['aop.load']);

    Route::post('/orders', [OrderController::class, 'store'])
        ->middleware(['aop.load', 'aop.log', 'aop.stock']);

    Route::get('/orders', [OrderController::class, 'userOrders']);
});
