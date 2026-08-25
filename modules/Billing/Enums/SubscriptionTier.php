<?php

declare(strict_types=1);

namespace Modules\Billing\Enums;

enum SubscriptionTier: string
{
    case Free = 'free';

    case Basic = 'basic';

    case Premium = 'premium';

    /**
     * Resolve a tier from whatever identifiers an event carried.
     *
     * The ENTITLEMENT identifier is passed first and wins, with the product
     * SKU only as a fallback: entitlements are what you configured to mean
     * "has premium", while SKUs are free-form store strings a client can and
     * will rename.
     */
    public static function fromIdentifiers(string ...$haystacks): self
    {
        // Each haystack is tried IN ORDER and the first that resolves wins.
        // Concatenating them and searching the whole string would not be
        // precedence at all — a legacy SKU like `legacy_premium_bundle_v2`
        // would silently upgrade a `basic` entitlement holder to Premium,
        // which is a money bug in the customer's favour and a support ticket
        // in ours.
        foreach ($haystacks as $haystack) {
            $subject = mb_strtolower($haystack);

            if ($subject === '') {
                continue;
            }

            $tier = match (true) {
                str_contains($subject, 'premium') => self::Premium,
                str_contains($subject, 'basic')   => self::Basic,
                default                           => null,
            };

            if ($tier !== null) {
                return $tier;
            }
        }

        return self::Free;
    }

    public function rank(): int
    {
        return match ($this) {
            self::Free    => 0,
            self::Basic   => 1,
            self::Premium => 2,
        };
    }

    public function atLeast(self $other): bool
    {
        return $this->rank() >= $other->rank();
    }
}
