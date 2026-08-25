<?php

declare(strict_types=1);

namespace Modules\Settings\Support;

class SettingDefinition
{
    public const TYPE_STRING = 'string';

    public const TYPE_TEXT = 'text';

    public const TYPE_BOOLEAN = 'boolean';

    public const TYPE_INTEGER = 'integer';

    public const TYPE_FLOAT = 'float';

    public const TYPE_SELECT = 'select';

    public const TYPE_JSON = 'json';

    public const TYPES = [
        self::TYPE_STRING, self::TYPE_TEXT, self::TYPE_BOOLEAN,
        self::TYPE_INTEGER, self::TYPE_FLOAT, self::TYPE_SELECT, self::TYPE_JSON,
    ];

    /**
     * @param  array<string, string>  $choices  For TYPE_SELECT: value => label.
     * @param  list<string>  $rules  Extra validation on top of the type's own.
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly string $type = self::TYPE_STRING,
        public readonly mixed $default = null,
        public readonly string $group = 'General',
        public readonly ?string $help = null,
        public readonly array $choices = [],
        public readonly array $rules = [],
        /**
         * Readable without authentication — an app name or a maintenance
         * banner. Never set this on anything a signed-out visitor should not
         * see.
         */
        public readonly bool $isPublic = false,
        /**
         * A credential. Its value is never returned; the API sends a masked
         * placeholder and accepts writes only.
         */
        public readonly bool $isSecret = false,
    ) {}

    /**
     * Validation for this setting's value, combining the type's own rules with
     * whatever the project added.
     *
     * @return list<string>
     */
    public function validationRules(): array
    {
        $base = match ($this->type) {
            self::TYPE_BOOLEAN => ['boolean'],
            self::TYPE_INTEGER => ['integer'],
            self::TYPE_FLOAT   => ['numeric'],
            self::TYPE_TEXT    => ['string', 'max:65535'],
            self::TYPE_JSON    => ['array'],
            self::TYPE_SELECT  => $this->choices === [] ? ['string'] : ['in:'.implode(',', array_keys($this->choices))],
            default            => ['string', 'max:1000'],
        };

        return [...$base, ...$this->rules];
    }

    public function cast(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($this->type) {
            self::TYPE_BOOLEAN => (bool) $value,
            self::TYPE_INTEGER => (int) $value,
            self::TYPE_FLOAT   => (float) $value,
            self::TYPE_JSON    => (array) $value,
            default            => is_scalar($value) ? (string) $value : $value,
        };
    }
}
