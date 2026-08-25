<?php

declare(strict_types=1);

namespace Modules\Billing\Enums;

enum PaymentProvider: string
{
    case None = 'none';

    case Apple = 'apple';

    case Google = 'google';

    case Web = 'web';

    /** Set by the admin switcher or by a support action, never by a store. */
    case Manual = 'manual';

    public static function fromStore(?string $store): self
    {
        return match (mb_strtolower((string) $store)) {
            'app_store', 'mac_app_store'     => self::Apple,
            'play_store', 'amazon'           => self::Google,
            'stripe', 'rc_billing', 'paddle' => self::Web,
            'promotional'                    => self::Manual,
            default                          => self::None,
        };
    }

    /**
     * Where this subscription is managed.
     *
     * Follows the processor, not the device. Null means "no link exists" —
     * for Apple on the web that is deliberate: linking an iOS subscriber to
     * web billing is external-purchase steering and gets the app rejected, so
     * the UI shows copy rather than a link.
     */
    public function managementUrl(): ?string
    {
        return match ($this) {
            self::Apple  => 'https://apps.apple.com/account/subscriptions',
            self::Google => 'https://play.google.com/store/account/subscriptions',
            self::Web    => config('billing.management_url'),
            default      => null,
        };
    }
}
