<?php

namespace App\Listeners;

use App\Models\NotificationLog;
use App\Models\User;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Str;

/** Stores the delivery status of every notification per channel. */
class RecordNotificationDelivery
{
    public function handle(NotificationSent|NotificationFailed $event): void
    {
        $error = null;
        if ($event instanceof NotificationFailed) {
            $exception = $event->data['exception'] ?? null;
            $error = $exception instanceof \Throwable ? Str::limit($exception->getMessage(), 480) : 'Delivery failed';
        }

        NotificationLog::query()->create([
            'user_id' => $event->notifiable instanceof User ? $event->notifiable->id : null,
            'notification_type' => class_basename($event->notification),
            'channel' => (string) $event->channel,
            'status' => $event instanceof NotificationSent ? 'sent' : 'failed',
            'error' => $error,
        ]);
    }
}
