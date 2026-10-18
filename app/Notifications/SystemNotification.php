<?php

namespace App\Notifications;

use App\Support\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Generic in-app (+ optional email) notification. Only translation keys and
 * parameters are stored, so each user reads the message in their own language.
 * Queued: run `php artisan queue:work` in production (see docs/06-deployment.md).
 */
class SystemNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  string  $kind  short machine name, e.g. task_assigned, task_overdue, approval_pending
     * @param  string  $titleKey  translation key, e.g. tasks.notify.assigned
     * @param  array<string, scalar>  $params  translation parameters (plain values only)
     * @param  string|null  $url  relative or absolute link inside the application
     */
    public function __construct(
        public string $kind,
        public string $titleKey,
        public array $params = [],
        public ?string $url = null,
    ) {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        $channels = ['database'];
        if (Settings::get('notifications.email_enabled') && ! empty($notifiable->email)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'kind' => $this->kind,
            'title_key' => $this->titleKey,
            'params' => $this->params,
            'url' => $this->url,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $locale = $notifiable->locale ?? config('app.locale');
        $message = __($this->titleKey, $this->params, $locale);
        $org = (string) Settings::get('org.short_name', 'NDC');

        $mail = (new MailMessage)
            ->subject($org.': '.\Illuminate\Support\Str::limit($message, 120))
            ->line($message);
        if ($this->url) {
            $mail->action(__('notifications.open', [], $locale), url($this->url));
        }

        return $mail->line(__('notifications.mail_footer', [], $locale));
    }
}
