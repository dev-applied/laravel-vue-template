<?php

declare(strict_types=1);

namespace Modules\FormBuilder\Support;

use Illuminate\Support\Str;

/**
 * Checks a form definition before it is saved.
 *
 * A builder UI that lets someone save a broken definition produces a form that
 * 500s for the public and works fine for its author, which is the worst place
 * to find out.
 *
 * @phpstan-type Field array<string, mixed>
 */
class SchemaValidator
{
    /**
     * @return list<string> Human-readable problems; empty means valid.
     */
    public function problems(mixed $schema): array
    {
        if (! is_array($schema) || $schema === []) {
            return ['A form needs at least one field.'];
        }

        $problems = [];
        $keys     = [];

        foreach ($schema as $index => $field) {
            $position = $index + 1;

            if (! is_array($field)) {
                $problems[] = "Field {$position} is not a field definition.";

                continue;
            }

            // Two different situations, deliberately treated differently:
            //
            //  - key ABSENT     — the builder UI did not supply one. normalise()
            //                     derives it from the label, so that is not an
            //                     error.
            //  - key DUPLICATED — the author wrote the same key twice on
            //                     purpose. One answer would silently overwrite
            //                     the other and nothing about the form would
            //                     look wrong, so this is rejected rather than
            //                     quietly renamed.
            $key = (string) ($field['key'] ?? '');

            if ($key !== '' && ! preg_match('/^[a-z][a-z0-9_]*$/', $key)) {
                $problems[] = "Field {$position} needs a key of lowercase letters, digits and underscores.";
            } elseif ($key !== '' && in_array($key, $keys, true)) {
                $problems[] = "Field {$position} reuses the key [{$key}].";
            } elseif ($key !== '') {
                $keys[] = $key;
            }

            if (mb_trim((string) ($field['label'] ?? '')) === '') {
                $problems[] = "Field {$position} needs a label.";
            }

            $type = (string) ($field['type'] ?? FieldType::TEXT);

            if (! in_array($type, FieldType::ALL, true)) {
                $problems[] = "Field {$position} has an unknown type [{$type}].";
            }

            if (in_array($type, FieldType::CHOICE_TYPES, true)) {
                $options = (array) ($field['options'] ?? []);

                if ($options === []) {
                    // A required dropdown with no options can never be
                    // satisfied — the form is unsubmittable and it looks fine.
                    $problems[] = "Field {$position} is a choice field with no options.";
                }

                foreach ($options as $option) {
                    if (! is_array($option) || ! isset($option['value'], $option['label'])) {
                        $problems[] = "Field {$position} has an option missing a value or a label.";

                        break;
                    }
                }
            }
        }

        return $problems;
    }

    /**
     * Normalise a definition: fill in keys, drop unknown attributes.
     *
     * @param  array<int, array<string, mixed>>  $schema
     * @return list<array<string, mixed>>
     */
    public function normalise(array $schema): array
    {
        $out = [];

        // Seeded with every EXPLICIT key up front, so a generated key can
        // never collide with one the author wrote — even a later one.
        $seen = array_values(array_filter(array_map(
            fn ($f) => is_array($f) ? (string) ($f['key'] ?? '') : '',
            $schema
        )));

        foreach ($schema as $field) {
            $label = (string) ($field['label'] ?? '');
            $key   = (string) ($field['key'] ?? '');

            if ($key === '') {
                $key = Str::snake(Str::slug($label, '_')) ?: 'field';
            }

            // Deduplicate rather than reject: a builder UI generating keys from
            // labels will produce "email" twice the moment someone adds a
            // second "Email" field.
            $explicit = ((string) ($field['key'] ?? '')) !== '';

            if (! $explicit) {
                $base = $key;
                $n    = 2;
                while (in_array($key, $seen, true)) {
                    $key = $base.'_'.$n++;
                }
                $seen[] = $key;
            }

            $out[] = array_filter([
                'key'         => $key,
                'label'       => $label,
                'type'        => $field['type'] ?? FieldType::TEXT,
                'required'    => (bool) ($field['required'] ?? false),
                'help'        => $field['help'] ?? null,
                'placeholder' => $field['placeholder'] ?? null,
                'options'     => $field['options'] ?? null,
                'min'         => $field['min'] ?? null,
                'max'         => $field['max'] ?? null,
            ], fn ($v) => $v !== null);
        }

        return $out;
    }
}
