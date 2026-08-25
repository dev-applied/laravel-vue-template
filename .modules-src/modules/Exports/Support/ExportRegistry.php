<?php

declare(strict_types=1);

namespace Modules\Exports\Support;

use Closure;
use RuntimeException;

/**
 * Where a project declares what can be exported. Register from
 * AppServiceProvider::boot():
 *
 *   app(ExportRegistry::class)->register(
 *       key:     'items',
 *       label:   'Items',
 *       columns: ['id' => 'ID', 'name' => 'Name', 'owner.name' => 'Owner'],
 *       query:   fn (array $filters) => Item::query()->filter($filters),
 *       ability: 'viewAny',
 *   );
 *
 * Keeping this a registry rather than a per-model trait means the export
 * surface is an explicit allow-list: a user can only ever export a listing
 * somebody deliberately exposed.
 */
class ExportRegistry
{
    /** @var array<string, ExportSource> */
    private array $sources = [];

    /**
     * @param  array<string, string>  $columns
     * @param  Closure(array<string, mixed>): mixed  $query
     */
    public function register(
        string $key,
        string $label,
        array $columns,
        Closure $query,
        ?Closure $format = null,
        ?string $ability = null,
    ): void {
        $this->sources[$key] = new ExportSource($key, $label, $columns, $query, $format, $ability);
    }

    public function has(string $key): bool
    {
        return isset($this->sources[$key]);
    }

    public function get(string $key): ExportSource
    {
        return $this->sources[$key] ?? throw new RuntimeException("No export source registered for [{$key}].");
    }

    /** @return array<int, string> */
    public function keys(): array
    {
        return array_keys($this->sources);
    }

    /** @return array<string, ExportSource> */
    public function all(): array
    {
        return $this->sources;
    }
}
