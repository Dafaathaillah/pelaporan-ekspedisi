<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class OperationalPushNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $title,
        public string $body,
        public string $url,
        public string $tag,
    ) {}

    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, Notification $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title($this->title)
            ->body($this->body)
            ->icon('/images/push-icon-192.png')
            ->badge('/images/push-badge-96.png')
            ->tag($this->tag)
            ->renotify()
            ->vibrate([180, 80, 180])
            ->action('Buka aplikasi', 'open')
            ->data(['url' => $this->url])
            ->options(['TTL' => 86400, 'urgency' => 'high']);
    }
}
