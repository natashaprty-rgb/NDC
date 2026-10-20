<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Base class for system notifications. Every notification is stored in the
 * database (in-app inbox); e-mail is sent as well when enabled in Settings
 * and the recipient is active. Delivery runs on the queue.
 */
abstract class AppNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Short category key, e.g. "task_assigned"; used for icons and filters.
     */
    abstract protected function type(): string;

    abstract protected function title(): string;

    abstract protected function body(): string;

    abstract protected function url(): string;

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if (setting('mail_notifications_enabled') && ($notifiable->status ?? 'active') === 'active' && ! empty($notifiable->email)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->type(),
            'title' => $this->title(),
            'body' => $this->body(),
            'url' => $this->url(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title())
            ->greeting(__('Hello :name,', ['name' => $notifiable->name]))
            ->line($this->body())
            ->action(__('Open in NDC Management System'), $this->url());
    }
}
