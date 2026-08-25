<?php

declare(strict_types=1);

namespace Modules\SmsMessaging\Notifications;

use Illuminate\Notifications\Notification;
use Modules\SmsMessaging\Support\SmsManager;

/**
 * Laravel notification channel, so SMS is a `via()` entry like mail:
 *
 *   public function via($notifiable) { return ['mail', 'sms']; }
 *   public function toSms($notifiable): string { return 'Your order shipped.'; }
 *
 * The number comes from `routeNotificationForSms()` on the notifiable, falling
 * back to a `phone_number` attribute — the module never assumes a column name
 * on a model it does not own.
 */
class SmsChannel
{
    public function __construct(private readonly SmsManager $manager) {}

    public function send(mixed $notifiable, Notification $notification): void
    {
        if (! method_exists($notification, 'toSms')) {
            return;
        }

        $to = method_exists($notifiable, 'routeNotificationForSms')
            ? $notifiable->routeNotificationForSms($notification)
            : ($notifiable->phone_number ?? null);

        if (blank($to)) {
            return;
        }

        $this->manager->send((string) $to, (string) $notification->toSms($notifiable), $notifiable);
    }
}
