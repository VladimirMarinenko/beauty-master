<?php

namespace App\Services;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription as WebPushSubscription;
use Minishlink\WebPush\WebPush;

class PushService
{
    public static function sendToUser(User $user, array $payload): void
    {
        $subs = $user->pushSubscriptions()->get();
        if ($subs->isEmpty()) {
            Log::info('PushService: no subscriptions', ['user_id' => $user->id]);
            return;
        }
        self::sendToSubscriptions($subs, $payload);
    }

    public static function sendToSubscriptions($subs, array $payload): void
    {
        $publicKey  = config('webpush.vapid.public_key');
        $privateKey = config('webpush.vapid.private_key');
        $subject    = config('webpush.vapid.subject');

        // Страховка: subject должен быть mailto:... или http(s)://...
        if (!$subject || (!str_starts_with($subject, 'mailto:') && !str_starts_with($subject, 'http'))) {
            $subject = 'https://beauty-master.duckdns.org';
        }

        if (!$publicKey || !$privateKey) {
            Log::warning('PushService: VAPID keys missing');
            return;
        }

        Log::info('PushService: sending', [
            'subject'         => $subject,
            'public_key_len'  => strlen($publicKey),
            'private_key_len' => strlen($privateKey),
            'subs_count'      => count($subs),
        ]);

        $webPush = new WebPush([
            'VAPID' => [
                'subject'    => $subject,
                'publicKey'  => $publicKey,
                'privateKey' => $privateKey,
            ],
        ]);

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $queued = 0;

        foreach ($subs as $sub) {
            if (empty($sub->endpoint) || empty($sub->public_key) || empty($sub->auth_token)) {
                Log::warning('PushService: incomplete subscription', ['sub_id' => $sub->id]);
                continue;
            }

            try {
                $webSub = WebPushSubscription::create([
                    'endpoint'        => $sub->endpoint,
                    'publicKey'       => $sub->public_key,
                    'authToken'       => $sub->auth_token,
                    'contentEncoding' => 'aes128gcm',
                ]);
                $webPush->queueNotification($webSub, $json);
                $queued++;
            } catch (\Throwable $e) {
                Log::warning('PushService: bad subscription', [
                    'sub_id' => $sub->id,
                    'error'  => $e->getMessage(),
                ]);
            }
        }

        if ($queued === 0) {
            Log::warning('PushService: nothing queued');
            return;
        }

        foreach ($webPush->flush() as $report) {
            $endpoint = (string) $report->getEndpoint();

            if ($report->isSuccess()) {
                Log::info('PushService: sent', ['endpoint' => substr($endpoint, 0, 60)]);
                continue;
            }

            $statusCode = $report->getResponse()?->getStatusCode();

            Log::info('PushService: delivery failed', [
                'endpoint' => substr($endpoint, 0, 60),
                'reason'   => $report->getReason(),
                'status'   => $statusCode,
            ]);

            if (in_array($statusCode, [404, 410], true)) {
                PushSubscription::where('endpoint', $endpoint)->delete();
                Log::info('PushService: removed dead subscription');
            }
        }
    }
}
