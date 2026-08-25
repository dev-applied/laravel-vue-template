<?php

declare(strict_types=1);

namespace Modules\Onboarding\Support;

use Closure;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * One declared first-run step.
 *
 * `completedWhen` is the interesting field. A step is usually already satisfied
 * by something the user did elsewhere — they uploaded an avatar on the profile
 * screen, they invited a colleague from the team page — and asking them to come
 * back and tick a box for work they have already done is the thing that makes
 * onboarding feel like paperwork. When the closure is given, the step reports
 * itself complete without anyone posting anything.
 *
 * A step with no `completedWhen` is completed only by an explicit POST.
 */
class OnboardingStep
{
    /** @param  Closure(Authenticatable): bool|null  $completedWhen */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly ?string $description = null,
        public readonly ?string $icon = null,
        /** @var array<string, mixed>|null */
        public readonly ?array $route = null,
        public readonly bool $required = true,
        public readonly ?Closure $completedWhen = null,
        public readonly int $order = 0,
    ) {}

    /** Has the user satisfied this step somewhere else in the app already? */
    public function isSatisfiedBy(Authenticatable $user): bool
    {
        return $this->completedWhen !== null && ($this->completedWhen)($user) === true;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'description' => $this->description,
            'icon' => $this->icon,
            'route' => $this->route,
            'required' => $this->required,
        ];
    }
}
