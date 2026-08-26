<?php

namespace App\Http\Controllers;

use App\Notifications\OperationalPushNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
    public function config(Request $request): JsonResponse
    {
        return response()->json([
            'publicKey' => config('webpush.vapid.public_key'),
            'subscribed' => $request->user()->pushSubscriptions()->exists(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'endpoint' => ['required', 'url', 'max:500'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'contentEncoding' => ['nullable', 'in:aesgcm,aes128gcm'],
        ]);

        $request->user()->updatePushSubscription(
            $data['endpoint'],
            $data['keys']['p256dh'],
            $data['keys']['auth'],
            $data['contentEncoding'] ?? 'aes128gcm',
        );

        return response()->json(['message' => 'Notifikasi perangkat berhasil diaktifkan.']);
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate(['endpoint' => ['required', 'url', 'max:500']]);
        $request->user()->deletePushSubscription($data['endpoint']);

        return response()->json(['message' => 'Notifikasi perangkat dinonaktifkan.']);
    }

    public function test(Request $request): JsonResponse
    {
        $request->user()->notify(new OperationalPushNotification(
            title: 'Notifikasi Ekspedisi Aktif',
            body: 'Perangkat ini siap menerima informasi operasional secara realtime.',
            url: $request->user()->homePath(),
            tag: 'push-ready-'.$request->user()->id,
        ));

        return response()->json(['message' => 'Notifikasi pengujian dikirim.']);
    }
}
