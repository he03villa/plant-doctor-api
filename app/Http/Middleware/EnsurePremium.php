<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsurePremium
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

        if (!$subscription || $subscription?->plan?->slug === 'free' || $subscription->is_expired) {
            return response()->json([
                'success' => false,
                'message' => 'Esta función requiere un plan premium.',
                'error_code' => 'PREMIUM_REQUIRED',
                'upgrade_url' => '/billing',
            ], 403);
        }

        return $next($request);
    }
}
