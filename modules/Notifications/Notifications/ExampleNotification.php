<?php

declare(strict_types=1);

namespace Modules\Notifications\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Copy-me reference for this module's payload convention.
 *
 * The `data` array is free-form as far as Laravel is concerned, but
 * NotificationResource — and therefore the bell and the notifications page —
 * reads exactly these keys. Keep them when writing your own notifications:
 *
 *   title  required  one line, shown as the row title
 *   body   optional  supporting line, truncated in the bell
 *   icon   optional  Material Symbols name, e.g. 'task_alt'
 *   color  optional  Vuetify theme colour, e.g. 'success'
 *   url    optional  in-app path the row links to when clicked
 */
class ExampleNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $title,
        private readonly ?string $body = null,
        private readonly ?string $url = null,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'body'  => $this->body,
            'icon'  => 'notifications',
            'color' => 'primary',
            'url'   => $this->url,
        ];
    }
}
