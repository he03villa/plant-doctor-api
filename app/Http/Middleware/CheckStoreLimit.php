<?php

namespace App\Http\Middleware;

use App\Models\Store;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckStoreLimit
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        if ($user->hasRole('super_admin')) {
            return $next($request);
        }

        $store = $user->store;

        if (! $store) {
            return $next($request);
        }

        $subscription = $store->subscription;

        if (! $subscription) {
            $storeCount = Store::where('user_id', $user->id)->count();
            if ($storeCount >= 1) {
                return $this->denied();
            }

            return $next($request);
        }

        $hasMultiStore = $subscription->plan->features['has_multi_store'] ?? false;

        if ($hasMultiStore) {
            return $next($request);
        }

        $storeCount = Store::where('user_id', $user->id)->count();

        if ($storeCount >= 1) {
            return $this->denied();
        }

        return $next($request);
    }

    private function denied(): Response
    {
        return response()->json([
            'success' => false,
            'message' => 'Ya tienes una tienda. Actualiza a plan Business para crear múltiples tiendas.',
            'error_code' => 'STORE_LIMIT_REACHED',
            'upgrade_url' => '/billing',
        ], 403);
    }
}
