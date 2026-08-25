<?php

declare(strict_types=1);

namespace Modules\Checklists\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $label
 * @property string|null $help
 * @property bool $requires_evidence
 * @property bool $is_required
 * @property int $position
 */
class ChecklistTemplateItem extends Model
{
    protected $table = 'checklist_template_items';

    protected $fillable = ['checklist_template_id', 'label', 'help', 'requires_evidence', 'is_required', 'position'];

    protected function casts(): array
    {
        return [
            'requires_evidence' => 'boolean',
            'is_required'       => 'boolean',
            'position'          => 'integer',
        ];
    }
}
