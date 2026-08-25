<?php

declare(strict_types=1);

namespace Modules\Onboarding\Support;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use RuntimeException;

/**
 * Where a project declares its first-run steps. Register from
 * AppServiceProvider::boot():
 *
 *   app(OnboardingRegistry::class)->register(
 *       key:           'profile',
 *       label:         'Complete your profile',
 *       description:   'Add a name and a photo so colleagues recognise you.',
 *       route:         ['name' => 'profile.edit'],
 *       icon:          'account_circle',
 *       required:      true,
 *       completedWhen: fn (User $user) => filled($user->avatar_path),
 *       order:         0,
 *   );
 *
 * The registry is the whole configuration surface: there is no onboarding
 * config file and no seeded rows. A step that is no longer wanted is deleted
 * from the provider, and its progress rows become inert rather than resurfacing
 * a step nobody declares any more.
 */
class OnboardingRegistry
{
    /** @var array<string, OnboardingStep> */
    private array $steps = [];

    /** @param  Closure(Authenticatable): bool|null  $completedWhen */
    public function register(
        string $key,
        string $label,
        ?string $description = null,
        ?string $icon = null,
        ?array $route = null,
        bool $required = true,
        ?Closure $completedWhen = null,
        int $order = 0,
    ): void {
        $this->steps[$key] = new OnboardingStep(
            $key, $label, $description, $icon, $route, $required, $completedWhen, $order,
        );
    }

    public function has(string $key): bool
    {
        return isset($this->steps[$key]);
    }

    public function get(string $key): OnboardingStep
    {
        return $this->steps[$key] ?? throw new RuntimeException("No onboarding step registered for [{$key}].");
    }

    /** @return array<int, string> */
    public function keys(): array
    {
        return array_keys($this->steps);
    }

    /**
     * Declared steps in display order.
     *
     * @return array<string, OnboardingStep>
     */
    public function all(): array
    {
        $steps = $this->steps;

        uasort($steps, fn (OnboardingStep $a, OnboardingStep $b) => [$a->order, $a->label] <=> [$b->order, $b->label]);

        return $steps;
    }
}
