<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureFeature
{
    public function handle(Request $request, Closure $next, string $feature): Response
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

        if (!$subscription) {
            return $this->denied($feature);
        }

        $value = $subscription?->plan?->features[$feature] ?? false;

        if (!$value) {
            return $this->denied($feature);
        }

        return $next($request);
    }

    private function denied(string $feature): Response
    {
        $messages = [
            'has_map' => 'Esta función requiere un plan Pro o superior.',
            'has_advanced_dashboard' => 'El dashboard avanzado requiere un plan Pro o superior.',
            'has_invoicing' => 'La facturación con IA requiere un plan Pro o superior.',
            'has_multi_store' => 'Multi-tienda requiere un plan Business.',
        ];

        return response()->json([
            'success' => false,
            'message' => $messages[$feature] ?? 'Esta función no está disponible en tu plan.',
            'error_code' => 'FEATURE_NOT_AVAILABLE',
            'required_feature' => $feature,
            'upgrade_url' => '/billing',
        ], 403);
    }
}
