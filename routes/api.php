<?php

use App\Http\Controllers\AccountingController;
use App\Http\Controllers\Admin\AdminPaymentController;
use App\Http\Controllers\Admin\AdminPlanController;
use App\Http\Controllers\Admin\AdminProductController;
use App\Http\Controllers\Admin\AdminSaleController;
use App\Http\Controllers\Admin\AdminStatsController;
use App\Http\Controllers\Admin\AdminStoreController;
use App\Http\Controllers\Admin\AdminSubscriptionController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DiagnosisController;
use App\Http\Controllers\DiseaseController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderPaymentController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\PlantController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ViveroController;
use App\Http\Controllers\WebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Stripe Webhook (no auth — Stripe signs the request)
Route::post('webhooks/stripe', [WebhookController::class, 'handleWebhook']);

Route::group(['prefix' => 'auth'], function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:5,1');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:10,1');
    Route::post('/change-password', [AuthController::class, 'changePassword'])->middleware('auth:api');
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:api');
    Route::post('/refresh', [AuthController::class, 'refresh']);
    Route::get('/me', [AuthController::class, 'me'])->middleware('auth:api');
    Route::get('/me/roles-and-permissions', [AuthController::class, 'meRolesAndPermissions'])->middleware('auth:api');
    Route::put('/profile', [AuthController::class, 'updateProfile'])->middleware('auth:api');
});

// Public routes — Plans
Route::get('plans', [PlanController::class, 'index']);
Route::get('plans/{slug}', [PlanController::class, 'show']);

Route::middleware('auth:api')->group(function () {
    Route::apiResource('plants', PlantController::class);
    Route::apiResource('diseases', DiseaseController::class)->only(['index', 'show']);
    Route::apiResource('diagnoses', DiagnosisController::class)->only(['index', 'store', 'show']);
    Route::post('/diagnoses/{diagnosis}/request-expert-review', [DiagnosisController::class, 'requestExpertReview']);
    Route::post('/diagnoses/{diagnosis}/review', [DiagnosisController::class, 'review']);

    // Orders — premium only for parse (AI invoicing)
    Route::middleware('premium')->group(function () {
        Route::post('/orders/parse', [OrderController::class, 'parse']);
    });
    Route::get('/orders/pending-items', [OrderController::class, 'pendingItems']);
    Route::post('/orders/items/link', [OrderController::class, 'linkItems']);
    Route::apiResource('orders', OrderController::class);
    Route::post('/orders/{order}/verify', [OrderController::class, 'verify']);
    Route::apiResource('orders/{order}/payments', OrderPaymentController::class)->only(['index', 'store', 'destroy']);

    // Dashboard — advanced analytics requires Pro+
    Route::middleware('feature:has_advanced_dashboard')->group(function () {
        Route::get('/vivero/dashboard', [ViveroController::class, 'dashboard']);
        Route::get('/vivero/accounting/profit-loss', [AccountingController::class, 'profitLoss']);
        Route::get('/vivero/accounting/profit-loss/export', [AccountingController::class, 'export']);
        Route::get('/vivero/accounting/daily-sales', [AccountingController::class, 'dailySales']);
        Route::get('/vivero/accounting/tax-summary', [AccountingController::class, 'taxSummary']);
        Route::get('/vivero/accounting/balance-sheet', [AccountingController::class, 'balanceSheet']);
        Route::get('/vivero/accounting/monthly-close', [AccountingController::class, 'monthlyClose']);
    });

    Route::get('/stores/nearby', [StoreController::class, 'nearby']);

    // Subscriptions
    Route::get('subscriptions/current', [SubscriptionController::class, 'current']);
    Route::post('subscriptions', [SubscriptionController::class, 'store']);
    Route::post('subscriptions/portal', [SubscriptionController::class, 'portal']);
    Route::patch('subscriptions/{id}', [SubscriptionController::class, 'update']);
    Route::delete('subscriptions/{id}', [SubscriptionController::class, 'cancel']);

    // Payments
    Route::get('payments', [PaymentController::class, 'index']);
    Route::get('payments/recent', [PaymentController::class, 'recent']);

    Route::middleware('store.owner')->group(function () {
        // Stores — limit check on creation (1 for Free/Pro, unlimited for Business)
        Route::apiResource('stores', StoreController::class)->except(['store']);
        Route::post('stores', [StoreController::class, 'store'])
            ->middleware('check.store.limit');

        Route::put('/stores/{store}/onboarding', [StoreController::class, 'onboarding']);
        Route::put('/stores/{store}/toggle-map', [StoreController::class, 'toggleMap']);

        // Products — all methods except 'store' (no limit check)
        Route::apiResource('stores/{store}/products', ProductController::class)
            ->except(['store']);
        Route::patch('/stores/{store}/products/{product}/visibility', [ProductController::class, 'toggleVisibility']);

        // Product creation — with limit check
        Route::post('stores/{store}/products', [ProductController::class, 'store'])
            ->middleware('check.product.limit');

        // Sales — POS transactions per store
        Route::apiResource('stores/{store}/sales', SaleController::class)->only(['index', 'store', 'show', 'destroy']);
    });

    // Admin — Diagnoses (super_admin or expert review queue)
    Route::prefix('admin')->middleware(['role:super_admin|expert'])->group(function () {
        Route::get('diagnoses', [DiagnosisController::class, 'adminIndex']);
    });

    // Admin — Roles & Permissions
    Route::prefix('admin')->middleware(['role:super_admin'])->group(function () {
        Route::apiResource('roles', RoleController::class);
        Route::get('permissions', [PermissionController::class, 'index']);
        Route::post('users/{id}/roles', [RoleController::class, 'assignToUser']);
        Route::delete('users/{id}/roles/{role}', [RoleController::class, 'removeFromUser']);

        // Admin — User Management
        Route::apiResource('users', UserController::class);
        Route::patch('users/{id}/toggle', [UserController::class, 'toggleActive']);

        // Admin — Stores
        Route::get('stores', [AdminStoreController::class, 'index']);
        Route::get('stores/{id}', [AdminStoreController::class, 'show']);
        Route::delete('stores/{id}', [AdminStoreController::class, 'destroy']);
        Route::patch('stores/{id}/toggle', [AdminStoreController::class, 'toggleActive']);
        Route::post('stores/{id}/subscription', [AdminStoreController::class, 'assignSubscription']);

        // Admin — Products (global)
        Route::get('products', [AdminProductController::class, 'index']);
        Route::get('products/{id}', [AdminProductController::class, 'show']);
        Route::patch('products/{id}/toggle', [AdminProductController::class, 'toggleVisibility']);
        Route::delete('products/{id}', [AdminProductController::class, 'destroy']);

        // Admin — Sales (global)
        Route::get('sales', [AdminSaleController::class, 'index']);
        Route::get('sales/{id}', [AdminSaleController::class, 'show']);
        Route::delete('sales/{id}', [AdminSaleController::class, 'destroy']);

        // Admin — Plans
        Route::apiResource('plans', AdminPlanController::class);

        // Admin — Subscriptions
        Route::get('subscriptions', [AdminSubscriptionController::class, 'index']);
        Route::get('subscriptions/{id}', [AdminSubscriptionController::class, 'show']);
        Route::patch('subscriptions/{id}', [AdminSubscriptionController::class, 'update']);

        // Admin — Payments
        Route::get('payments', [AdminPaymentController::class, 'index']);

        // Admin — Stats
        Route::get('stats', [AdminStatsController::class, 'index']);
    });
});
