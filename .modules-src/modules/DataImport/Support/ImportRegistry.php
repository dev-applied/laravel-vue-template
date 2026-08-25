<?php

declare(strict_types=1);

namespace Modules\DataImport\Support;

use Closure;
use RuntimeException;

/**
 * Where a project declares what may be imported. Register from
 * AppServiceProvider::boot():
 *
 *   app(ImportRegistry::class)->register(
 *       key:      'items',
 *       label:    'Items',
 *       fields:   ['name' => 'Name', 'status' => 'Status'],
 *       rules:    ['name' => 'required|string|max:255', 'status' => 'required|in:draft,live'],
 *       required: ['name'],
 *       handler:  fn (array $row) => Item::updateOrCreate(['name' => $row['name']], $row),
 *   );
 *
 * An allow-list for the same reason ExportRegistry is one: an import writes to
 * the database, so the set of writable targets must be something a developer
 * deliberately exposed, never a model name off the wire.
 */
class ImportRegistry
{
    /** @var array<string, ImportTarget> */
    private array $targets = [];

    /**
     * @param  array<string, string>  $fields
     * @param  array<string, mixed>  $rules
     * @param  array<int, string>  $required
     */
    public function register(
        string $key,
        string $label,
        array $fields,
        array $rules,
        Closure $handler,
        array $required = [],
        ?string $ability = null,
    ): void {
        $this->targets[$key] = new ImportTarget($key, $label, $fields, $rules, $handler, $required, $ability);
    }

    public function has(string $key): bool
    {
        return isset($this->targets[$key]);
    }

    public function get(string $key): ImportTarget
    {
        return $this->targets[$key] ?? throw new RuntimeException("No import target registered for [{$key}].");
    }

    /** @return array<int, string> */
    public function keys(): array
    {
        return array_keys($this->targets);
    }

    /** @return array<string, ImportTarget> */
    public function all(): array
    {
        return $this->targets;
    }
}
