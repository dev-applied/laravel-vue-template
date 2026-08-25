<?php

declare(strict_types=1);

namespace Modules\Checklists\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One filled-in instance of a template, against one subject.
 *
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $completed_at
 * @property string|null $signed_by
 */
class Checklist extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_COMPLETE = 'complete';

    protected $table = 'checklists';

    protected $fillable = [
        'checklist_template_id', 'subject_type', 'subject_id', 'name', 'status', 'completed_at', 'signed_by',
    ];

    /**
     * Copy the template's items onto a new instance.
     *
     * A COPY, not a reference. The template is edited over time — somebody adds
     * a step, rewords another, deletes a third — and an instance that pointed at
     * the live items would change under the person who filled it in. A checklist
     * signed last month has to keep saying what was actually checked, which is
     * the entire reason anybody keeps one.
     *
     * The template name is copied for the same reason.
     */
    public static function instantiate(ChecklistTemplate $template, Model $subject): self
    {
        $checklist = static::query()->create([
            'checklist_template_id' => $template->getKey(),
            'subject_type'          => $subject->getMorphClass(),
            'subject_id'            => $subject->getKey(),
            'name'                  => $template->name,
            'status'                => self::STATUS_OPEN,
        ]);

        foreach ($template->items as $item) {
            $checklist->responses()->create([
                'label'             => $item->label,
                'help'              => $item->help,
                'requires_evidence' => $item->requires_evidence,
                'is_required'       => $item->is_required,
                'position'          => $item->position,
                'status'            => ChecklistResponse::STATUS_PENDING,
            ]);
        }

        return $checklist->fresh(['responses']);
    }

    /** @return HasMany<ChecklistResponse, $this> */
    public function responses(): HasMany
    {
        return $this->hasMany(ChecklistResponse::class)->orderBy('position');
    }

    /** @return MorphTo<Model, $this> */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * What is still stopping this being completed.
     *
     * Returned rather than thrown so the UI can show all of it at once. A
     * checklist that reveals one missing item per attempt is the reason people
     * stop using them.
     *
     * @return list<string>
     */
    public function outstanding(): array
    {
        $reasons = [];

        foreach ($this->responses as $response) {
            if ($response->is_required && $response->status === ChecklistResponse::STATUS_PENDING) {
                $reasons[] = "“{$response->label}” has not been answered.";
            }

            // Evidence is checked against the ANSWER, not the item: a step
            // marked not-applicable cannot have a photo of itself, and
            // demanding one is how a checklist becomes unfinishable.
            if ($response->requires_evidence
                && $response->status === ChecklistResponse::STATUS_PASS
                && $response->file_id === null) {
                $reasons[] = "“{$response->label}” needs a photo.";
            }
        }

        return $reasons;
    }

    protected function casts(): array
    {
        return ['completed_at' => 'datetime'];
    }
}
