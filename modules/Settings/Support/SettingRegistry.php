<?php

declare(strict_types=1);

namespace Modules\Settings\Support;

use RuntimeException;

/**
 * What settings exist.
 *
 * Nothing undeclared can be written. A free-form key/value store accumulates
 * typos silently — `site_name` and `siteName` both present, one of them read
 * by nothing — and gives the UI no way to know whether a value is a boolean, a
 * URL or a credential. Declaring them means the management screen generates
 * itself and validation is real.
 */
class SettingRegistry
{
    /** @var array<string, SettingDefinition> */
    private array $definitions = [];

    public function define(SettingDefinition $definition): void
    {
        $this->definitions[$definition->key] = $definition;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function add(string $key, string $label, array $attributes = []): void
    {
        $this->define(new SettingDefinition(
            key: $key,
            label: $label,
            type: $attributes['type'] ?? SettingDefinition::TYPE_STRING,
            default: $attributes['default'] ?? null,
            group: $attributes['group'] ?? 'General',
            help: $attributes['help'] ?? null,
            choices: $attributes['choices'] ?? [],
            rules: $attributes['rules'] ?? [],
            isPublic: $attributes['public'] ?? false,
            isSecret: $attributes['secret'] ?? false,
        ));
    }

    public function has(string $key): bool
    {
        return isset($this->definitions[$key]);
    }

    public function get(string $key): SettingDefinition
    {
        if (! $this->has($key)) {
            throw new RuntimeException("No setting registered as [{$key}].");
        }

        return $this->definitions[$key];
    }

    /**
     * @return array<string, SettingDefinition>
     */
    public function all(): array
    {
        return $this->definitions;
    }

    /**
     * @return array<string, SettingDefinition>
     */
    public function public(): array
    {
        return array_filter($this->definitions, fn (SettingDefinition $d) => $d->isPublic);
    }

    /**
     * Definitions grouped for the management UI, groups and keys both sorted
     * so the screen does not reshuffle between deploys.
     *
     * @return array<string, list<SettingDefinition>>
     */
    public function grouped(): array
    {
        $grouped = [];

        foreach ($this->definitions as $definition) {
            $grouped[$definition->group][] = $definition;
        }

        ksort($grouped);

        foreach ($grouped as $group => $definitions) {
            usort($definitions, fn ($a, $b) => $a->label <=> $b->label);
            $grouped[$group] = $definitions;
        }

        return $grouped;
    }
}
