<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PushSubscriptionController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'endpoint' => 'required',
            'keys.p256dh' => 'required',
            'keys.auth' => 'required',
        ]);

        // Удаляем старую подписку для этого endpoint
        PushSubscription::where('endpoint', $request->endpoint)->delete();

        Auth::user()->pushSubscriptions()->create([
            'endpoint' => $request->endpoint,
            'public_key' => $request->keys['p256dh'],
            'auth_token' => $request->keys['auth'],
        ]);

        return response()->json(['success' => true]);
    }
}
