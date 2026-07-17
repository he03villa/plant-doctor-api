<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DiagnosisController;
use App\Http\Controllers\DiseaseController;
use App\Http\Controllers\PlantController;
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
});
