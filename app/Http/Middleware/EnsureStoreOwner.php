<?php

namespace App\Http\Middleware;

use App\Models\Store;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureStoreOwner
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

        if ($user->hasRole('admin')) {
            return $next($request);
        }

        if (!$user->hasRole('store_owner')) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden: store_owner role required',
            ], 403);
        }

        $storeId = $request->route('store');

        if ($storeId) {
            $ownsStore = Store::where('id', $storeId)
                ->where('user_id', $user->id)
                ->exists();

            if (!$ownsStore) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not own this store',
                ], 403);
            }
        }

        return $next($request);
    }
}
