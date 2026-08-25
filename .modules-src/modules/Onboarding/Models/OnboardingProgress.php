<?php

declare(strict_types=1);

namespace Modules\Onboarding\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $user_id
 * @property string $step_key
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property \Illuminate\Support\Carbon|null $skipped_at
 */
class OnboardingProgress extends Model
{
    protected $table = 'onboarding_progress';

    protected $fillable = ['user_id', 'step_key', 'completed_at', 'skipped_at'];

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
            'skipped_at' => 'datetime',
        ];
    }

    /**
     * Completed and skipped are mutually exclusive, and setting one clears the
     * other on purpose.
     *
     * Skipping a step then doing it anyway is the ordinary path — a user skips
     * "invite your team" on day one and invites somebody in week two. If the
     * skip stuck, the checklist would keep showing that step as passed-over
     * while the work was done, and a completion gate keyed on "not outstanding"
     * would disagree with one keyed on "completed".
     */
    public static function markCompleted(int $userId, string $stepKey): self
    {
        return static::updateOrCreateFor($userId, $stepKey, [
            'completed_at' => now(),
            'skipped_at' => null,
        ]);
    }

    public static function markSkipped(int $userId, string $stepKey): self
    {
        return static::updateOrCreateFor($userId, $stepKey, [
            'skipped_at' => now(),
            'completed_at' => null,
        ]);
    }

    /** @param  array<string, mixed>  $attributes */
    private static function updateOrCreateFor(int $userId, string $stepKey, array $attributes): self
    {
        return static::query()->updateOrCreate(
            ['user_id' => $userId, 'step_key' => $stepKey],
            $attributes,
        );
    }
}
