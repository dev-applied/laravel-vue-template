<?php

declare(strict_types=1);

namespace Modules\Checklists\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A reusable checklist definition.
 *
 * The template/instance split is the whole shape worth extracting — it is
 * identical in two production apps that arrived at it independently. A template
 * is edited over time; an instance must NOT change under the person who filled
 * it in, so an instance copies its items at creation rather than referencing
 * them. See Checklist::instantiate().
 *
 * @property string $name
 * @property string|null $description
 * @property bool $is_active
 */
class ChecklistTemplate extends Model
{
    use HasFactory;

    protected $table = 'checklist_templates';

    protected $fillable = ['name', 'description', 'is_active'];

    /** @return HasMany<ChecklistTemplateItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(ChecklistTemplateItem::class, 'checklist_template_id')->orderBy('position');
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
