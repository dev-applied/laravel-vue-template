<?php

declare(strict_types=1);

namespace Modules\Realtime\Support;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Broadcast;

/**
 * Where a project declares who may listen to what.
 *
 * Thin on purpose — this is `Broadcast::channel()` with one rule attached: a
 * channel with NO guard is refused rather than allowed. Laravel's default is
 * already to refuse an unmatched private channel, but a project that reaches
 * for `Broadcast::channel('{anything}', fn () => true)` to make a demo work has
 * opened every channel in the app, and that line reads as harmless.
 *
 *   app(ChannelGuards::class)->define('order.{order}', function (User $user, Order $order) {
 *       return $user->can('view', $order);
 *   });
 *
 * A presence channel returns an array (the payload other members see) instead
 * of true, which is worth saying out loud because returning `true` from a
 * presence guard silently produces a member list of nothing.
 */
class ChannelGuards
{
    /** @var array<int, string> */
    private array $defined = [];

    public function define(string $channel, Closure $guard): void
    {
        $this->defined[] = $channel;

        Broadcast::channel($channel, $guard);
    }

    /**
     * Presence channels, where the return value is the member payload.
     *
     * Split from define() so the return type is impossible to get wrong by
     * habit: `true` is a valid authorisation and an empty member record, so a
     * presence channel guarded like a private one connects fine and shows
     * nobody.
     *
     * @param  Closure(Authenticatable, mixed...): (array<string, mixed>|null)  $guard
     */
    public function presence(string $channel, Closure $guard): void
    {
        $this->define($channel, $guard);
    }

    /** @return array<int, string> */
    public function defined(): array
    {
        return $this->defined;
    }
}
