<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DiagnosisController;
use App\Http\Controllers\DiseaseController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PlantController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\ViveroController;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (\Illuminate\Http\Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::group(['prefix' => 'auth'], function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:api');
    Route::post('/refresh', [AuthController::class, 'refresh']);
    Route::get('/me', [AuthController::class, 'me'])->middleware('auth:api');
    Route::put('/profile', [AuthController::class, 'updateProfile'])->middleware('auth:api');
});

Route::middleware('auth:api')->group(function () {
    Route::apiResource('plants', PlantController::class);
    Route::apiResource('diseases', DiseaseController::class)->only(['index', 'show']);
    Route::apiResource('diagnoses', DiagnosisController::class)->only(['index', 'store', 'show']);
    Route::post('/diagnoses/{diagnosis}/request-expert-review', [DiagnosisController::class, 'requestExpertReview']);

    Route::post('/orders/parse', [OrderController::class, 'parse']);
    Route::apiResource('orders', OrderController::class);
    Route::post('/orders/{order}/verify', [OrderController::class, 'verify']);

    Route::get('/vivero/dashboard', [ViveroController::class, 'dashboard']);

    Route::get('/stores/nearby', [StoreController::class, 'nearby']);

    Route::middleware('store.owner')->group(function () {
        Route::apiResource('stores', StoreController::class);
        Route::put('/stores/{store}/onboarding', [StoreController::class, 'onboarding']);
        Route::put('/stores/{store}/toggle-map', [StoreController::class, 'toggleMap']);

        Route::apiResource('stores/{store}/products', ProductController::class);
        Route::patch('/stores/{store}/products/{product}/visibility', [ProductController::class, 'toggleVisibility']);
    });
});
