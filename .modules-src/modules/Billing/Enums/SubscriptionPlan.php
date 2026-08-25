<?php

declare(strict_types=1);

namespace Modules\Billing\Enums;

enum SubscriptionPlan: string
{
    case None = 'none';

    case Monthly = 'monthly';

    case Annual = 'annual';

    public static function fromIdentifiers(string ...$haystacks): self
    {
        // Same precedence rule as SubscriptionTier: first haystack that
        // resolves wins, rather than searching everything at once.
        foreach ($haystacks as $haystack) {
            $subject = mb_strtolower($haystack);

            if ($subject === '') {
                continue;
            }

            $plan = match (true) {
                str_contains($subject, 'annual') || str_contains($subject, 'yearly') => self::Annual,
                str_contains($subject, 'month')                                      => self::Monthly,
                default                                                              => null,
            };

            if ($plan !== null) {
                return $plan;
            }
        }

        return self::None;
    }
}
