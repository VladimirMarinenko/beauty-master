<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\PushService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendPushNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;
    public int $backoff = 10;
    public int $timeout = 20;

    public function __construct(
        public User $user,
        public array $payload
    ) {}

    public function handle(): void
    {
        PushService::sendToUser($this->user, $this->payload);
    }

    public function failed(\Throwable $e): void
    {
        Log::warning('SendPushNotification failed', [
            'user_id' => $this->user->id,
            'title'   => $this->payload['title'] ?? null,
            'error'   => $e->getMessage(),
        ]);
    }
}
