<?php

namespace App\Traits;

use App\Models\Store;

trait StoreScoped
{
    private function getStoreForUser($user): Store
    {
        $store = $user->store;

        if (!$store) {
            abort(404, 'No store found for this user. Create a store first.');
        }

        return $store;
    }
}
