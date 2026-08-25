<?php

declare(strict_types=1);

namespace Modules\DataImport\Support;

use Closure;

/**
 * One registered, importable thing.
 *
 * `fields` is the shape a row must arrive in AFTER mapping; `rules` validates
 * each mapped row; `handler` persists one row. Keeping persistence in a closure
 * the project owns means the module never guesses how a client's model should
 * be created or matched for updates.
 */
class ImportTarget
{
    /**
     * @param  array<string, string>  $fields  label keyed by field name
     * @param  array<string, mixed>  $rules  per-field validation rules
     * @param  Closure(array<string, mixed>, int): void  $handler
     * @param  array<int, string>  $required  fields that must be mapped
     */
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly array $fields,
        public readonly array $rules,
        public readonly Closure $handler,
        public readonly array $required = [],
        public readonly ?string $ability = null,
    ) {}

    /** @param  array<string, mixed>  $row */
    public function handle(array $row, int $lineNumber): void
    {
        ($this->handler)($row, $lineNumber);
    }
}
