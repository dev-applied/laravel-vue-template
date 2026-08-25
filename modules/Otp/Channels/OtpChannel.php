<?php

declare(strict_types=1);

namespace Modules\Otp\Channels;

/**
 * How a code reaches someone.
 *
 * The module ships email and nothing else. SMS is the obvious second channel
 * and Twilio is the obvious vendor — which is exactly why it is a contract
 * rather than a dependency: a module that hard-couples to one vendor refuses
 * to install anywhere that uses a different one, and every project that has
 * done this has had to fork it.
 *
 *   $this->app->bind('otp.channel.sms', TwilioOtpChannel::class);
 */
interface OtpChannel
{
    /**
     * Deliver the plaintext code. Throwing marks the request failed; the code
     * is not consumed and the caller can retry.
     */
    public function send(string $identifier, string $code, string $purpose): void;

    /**
     * Whether this identifier is addressable on this channel — an email
     * address for mail, an E.164 number for SMS. Rejecting here is what stops
     * a phone number being emailed into the void.
     */
    public function supports(string $identifier): bool;

    /**
     * The identifier partly masked, for "we sent a code to j••@example.com".
     * Never return it in full: the confirmation screen is shown to whoever
     * typed the address, who may not be its owner.
     */
    public function mask(string $identifier): string;
}
