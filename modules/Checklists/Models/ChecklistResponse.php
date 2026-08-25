<?php

declare(strict_types=1);

namespace Modules\Checklists\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One answered line of a checklist instance.
 *
 * The label and flags are copied from the template item rather than joined to
 * it — see Checklist::instantiate() for why.
 *
 * @property string $label
 * @property string $status
 * @property string|null $note
 * @property int|null $file_id
 */
class ChecklistResponse extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PASS = 'pass';

    public const STATUS_FAIL = 'fail';

    public const STATUS_NA = 'na';

    /** Every answer a caller may set. `pending` is absent on purpose — see the request. */
    public const ANSWERS = [self::STATUS_PASS, self::STATUS_FAIL, self::STATUS_NA];

    protected $table = 'checklist_responses';

    protected $fillable = [
        'checklist_id', 'label', 'help', 'status', 'note', 'file_id',
        'requires_evidence', 'is_required', 'position', 'answered_at',
    ];

    protected function casts(): array
    {
        return [
            'requires_evidence' => 'boolean',
            'is_required'       => 'boolean',
            'position'          => 'integer',
            'answered_at'       => 'datetime',
        ];
    }
}
