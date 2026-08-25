<?php

declare(strict_types=1);

namespace Modules\FormBuilder\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormSubmission extends Model
{
    protected $fillable = ['form_id', 'user_id', 'answers', 'schema_snapshot', 'ip_address'];

    protected $casts = [
        'answers'         => 'array',
        'schema_snapshot' => 'array',
    ];

    /** @return BelongsTo<Form, self> */
    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    /** @return BelongsTo<User, self> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Answers paired with the labels THEY were collected under.
     *
     * Reads from the snapshot, not the live form. A form edited after the fact
     * would otherwise relabel every answer already collected — someone who
     * answered "Yes" to one question appears to have answered a different one.
     *
     * @return list<array{key: string, label: string, value: mixed}>
     */
    public function labelled(): array
    {
        $out = [];

        foreach ((array) $this->schema_snapshot as $field) {
            $key = $field['key'] ?? null;

            if ($key === null) {
                continue;
            }

            $out[] = [
                'key'   => $key,
                'label' => $field['label'] ?? $key,
                'value' => $this->answers[$key] ?? null,
            ];
        }

        return $out;
    }
}
