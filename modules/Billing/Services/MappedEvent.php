<?php

declare(strict_types=1);

namespace Modules\Billing\Services;

/**
 * The result of mapping a webhook payload — a plain value, no IO, no clock.
 */
final class MappedEvent
{
    public const KIND_GRANT = 'grant';

    public const KIND_REVOKE = 'revoke';

    public const KIND_CANCEL = 'cancel';

    public const KIND_TRANSFER = 'transfer';

    /** Recognised, but nothing about access changes. */
    public const KIND_INERT = 'inert';

    /**
     * @param  array<string, mixed>  $patch
     * @param  list<string>  $transferredTo
     * @param  list<string>  $transferredFrom
     */
    private function __construct(
        public readonly string $kind,
        public readonly ?string $userId = null,
        public readonly array $patch = [],
        public readonly bool $isSandbox = false,
        public readonly array $transferredTo = [],
        public readonly array $transferredFrom = [],
        public readonly ?string $reason = null,
        /**
         * The vendor's own clock for this event, in milliseconds.
         *
         * Null when the payload carried none. That is not the same as "now" —
         * an event we cannot place in time must not be allowed to advance the
         * ordering watermark, or it silently discards every real event that
         * happened before it.
         */
        public readonly ?int $eventAtMs = null,
    ) {}

    /**
     * @param  array<string, mixed>  $patch
     */
    public static function grant(string $userId, array $patch, bool $isSandbox, ?int $eventAtMs = null): self
    {
        return new self(self::KIND_GRANT, $userId, $patch, $isSandbox, [], [], null, $eventAtMs);
    }

    /**
     * @param  array<string, mixed>  $patch
     */
    public static function revoke(string $userId, array $patch, bool $isSandbox, ?int $eventAtMs = null): self
    {
        return new self(self::KIND_REVOKE, $userId, $patch, $isSandbox, [], [], null, $eventAtMs);
    }

    /**
     * @param  array<string, mixed>  $patch
     */
    public static function cancel(string $userId, array $patch, bool $isSandbox, ?int $eventAtMs = null): self
    {
        return new self(self::KIND_CANCEL, $userId, $patch, $isSandbox, [], [], null, $eventAtMs);
    }

    /**
     * @param  list<string>  $to
     * @param  list<string>  $from
     */
    public static function transfer(array $to, array $from, bool $isSandbox, ?int $eventAtMs = null): self
    {
        return new self(self::KIND_TRANSFER, null, [], $isSandbox, $to, $from, null, $eventAtMs);
    }

    public static function inert(bool $isSandbox, ?string $reason = null): self
    {
        return new self(self::KIND_INERT, null, [], $isSandbox, [], [], $reason);
    }

    public function changesAccess(): bool
    {
        return in_array($this->kind, [self::KIND_GRANT, self::KIND_REVOKE, self::KIND_CANCEL], true);
    }
}
