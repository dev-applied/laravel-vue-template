<?php

declare(strict_types=1);

namespace Modules\Otp\Support;

use App\Exceptions\AppException;
use Illuminate\Contracts\Container\Container;
use Modules\Otp\Channels\OtpChannel;

/**
 * Resolves a channel name to an implementation.
 *
 * The module registers `email`. A project binds `otp.channel.sms` (or anything
 * else) to add more, and nothing about a vendor reaches this module.
 */
class ChannelRegistry
{
    public function __construct(private readonly Container $container) {}

    public function get(string $name): OtpChannel
    {
        $abstract = 'otp.channel.'.$name;

        if (! $this->container->bound($abstract)) {
            throw new AppException("No OTP channel is configured for [{$name}].", 422);
        }

        return $this->container->make($abstract);
    }

    public function has(string $name): bool
    {
        return $this->container->bound('otp.channel.'.$name);
    }

    /**
     * Pick a channel from the shape of the identifier. An email address goes
     * to email; anything else is assumed to be a phone number, which fails
     * cleanly when no SMS channel is bound rather than silently emailing it.
     */
    public function guess(string $identifier): string
    {
        return filter_var($identifier, FILTER_VALIDATE_EMAIL) !== false ? 'email' : 'sms';
    }
}
