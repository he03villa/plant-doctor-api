<?php

namespace App\Http\Controllers;

use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;

class BillingController extends Controller
{
    public function handleRedirect(Request $request)
    {
        $sessionId = $request->query('session_id');
        $success = $request->query('success');

        $webUrl = config('app.web_billing_url', 'http://localhost:4200');
        $mobileDeepLink = config('app.mobile_billing_url', 'plantdoctor://billing');

        if ($sessionId) {
            $store = Store::where('checkout_session_id', $sessionId)->first();

            if ($store) {
                $platform = $store->checkout_platform;

                $store->update([
                    'checkout_platform' => null,
                    'checkout_session_id' => null,
                ]);

                $params = http_build_query(array_filter([
                    'session_id' => $sessionId,
                    'success' => $success,
                ]));

                if ($platform === 'mobile') {
                    return Redirect::to("{$mobileDeepLink}?{$params}");
                }

                return Redirect::to("{$webUrl}/billing?{$params}");
            }
        }

        if ($success) {
            return Redirect::to("{$webUrl}/billing?success=1");
        }

        $params = http_build_query(array_filter([
            'session_id' => $sessionId,
            'success' => $success,
        ]));

        if ($params) {
            return Redirect::to("{$webUrl}/billing?{$params}");
        }

        return Redirect::to($webUrl);
    }
}
