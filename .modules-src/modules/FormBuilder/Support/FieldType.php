<?php

declare(strict_types=1);

namespace Modules\FormBuilder\Support;

/**
 * The field types a form may contain.
 *
 * A closed list on purpose. An open one means the renderer meets a type it has
 * no component for and renders nothing — a required field the person cannot
 * see and cannot fill in, and a form that can never be submitted.
 */
class FieldType
{
    public const TEXT = 'text';

    public const TEXTAREA = 'textarea';

    public const EMAIL = 'email';

    public const NUMBER = 'number';

    public const DATE = 'date';

    public const SELECT = 'select';

    public const MULTISELECT = 'multiselect';

    public const RADIO = 'radio';

    public const CHECKBOX = 'checkbox';

    public const ALL = [
        self::TEXT, self::TEXTAREA, self::EMAIL, self::NUMBER,
        self::DATE, self::SELECT, self::MULTISELECT, self::RADIO, self::CHECKBOX,
    ];

    /** Types whose answer is chosen from a declared list. */
    public const CHOICE_TYPES = [self::SELECT, self::MULTISELECT, self::RADIO];

    /**
     * Validation rules for one field, derived from its definition.
     *
     * @param  array<string, mixed>  $field
     * @return list<string>
     */
    public static function rulesFor(array $field): array
    {
        $type     = $field['type'] ?? self::TEXT;
        $required = (bool) ($field['required'] ?? false);

        $rules = [$required ? 'required' : 'nullable'];

        $rules = [...$rules, ...match ($type) {
            self::EMAIL       => ['email', 'max:255'],
            self::NUMBER      => ['numeric'],
            self::DATE        => ['date'],
            self::TEXTAREA    => ['string', 'max:10000'],
            self::CHECKBOX    => ['boolean'],
            self::MULTISELECT => ['array'],
            default           => ['string', 'max:1000'],
        }];

        // A choice field must only accept its own choices. Without this the
        // form is a free-text field wearing a dropdown, and the "options" are
        // a suggestion.
        $choices = array_column((array) ($field['options'] ?? []), 'value');

        if (in_array($type, self::CHOICE_TYPES, true) && $choices !== []) {
            $rules[] = $type === self::MULTISELECT
                ? 'array'
                : 'in:'.implode(',', $choices);
        }

        if (isset($field['min']) && in_array($type, [self::NUMBER], true)) {
            $rules[] = 'min:'.$field['min'];
        }

        if (isset($field['max']) && in_array($type, [self::NUMBER], true)) {
            $rules[] = 'max:'.$field['max'];
        }

        return $rules;
    }
}
