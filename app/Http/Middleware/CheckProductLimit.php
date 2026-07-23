<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckProductLimit
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        if ($user->hasAnyRole(['admin', 'super_admin'])) {
            return $next($request);
        }

        $store = $user->store;

        if (!$store) {
            return response()->json([
                'success' => false,
                'message' => 'No store found for this user',
            ], 404);
        }

        $subscription = $store->subscription;
        $limit = $subscription?->getFeatureLimit('max_products');

        if ($limit === null || $limit == -1) {
            return $next($request);
        }

        $currentCount = $store->storeProducts()->count();

        if ($currentCount >= $limit) {
            return response()->json([
                'success' => false,
                'message' => 'Límite de productos alcanzado. Actualiza a un plan superior.',
                'error_code' => 'PRODUCT_LIMIT_REACHED',
                'current_count' => $currentCount,
                'limit' => $limit,
                'upgrade_url' => '/billing',
            ], 403);
        }

        return $next($request);
    }
}
