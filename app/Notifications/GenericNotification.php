<?php

namespace App\Notifications;

/**
 * Notification built from translation keys; used by modules for simple
 * events (assignments, reminders) so each one does not need its own class.
 * Keys are translated when the notification is delivered, so every recipient
 * reads it in their own language.
 *
 * Example:
 *   $user->notify(new GenericNotification('task_assigned', 'New task assigned',
 *       'You have been assigned the task ":title".', ['title' => $task->title], route('tasks.show', $task)));
 */
class GenericNotification extends AppNotification
{
    /**
     * @param  array<string, string>  $replace
     */
    public function __construct(
        public string $kind,
        public string $titleKey,
        public string $bodyKey,
        public array $replace,
        public string $link,
    ) {
    }

    protected function type(): string
    {
        return $this->kind;
    }

    protected function title(): string
    {
        return __($this->titleKey, $this->replace);
    }

    protected function body(): string
    {
        return __($this->bodyKey, $this->replace);
    }

    protected function url(): string
    {
        return $this->link;
    }
}
