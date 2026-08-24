<?php

declare(strict_types=1);

namespace App\Support\Modules;

use Illuminate\Support\Facades\File;

/**
 * Read/write a module's module.json.
 *
 * Two distinct "options" live here and must not be confused:
 *  - the OPTIONS SCHEMA (`options`) — authored upstream, the questions +
 *    per-choice effects. Stays put forever.
 *  - the INSTALLED SELECTIONS (`installed_options`) — the answers chosen when
 *    this project pulled the module. Written at install, replayed on update,
 *    edited by module:configure.
 */
class ModuleManifest
{
    /** @var array<string, mixed> */
    private array $data;

    public function __construct(public readonly string $path)
    {
        $this->data = File::exists($path)
            ? (array) json_decode(File::get($path), true)
            : [];
    }

    public static function forModuleDir(string $dir): self
    {
        return new self(mb_rtrim($dir, '/').'/module.json');
    }

    /** @return array<string, mixed> */
    public function data(): array
    {
        return $this->data;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    /** @return array<string, mixed> The authored options schema. */
    public function optionsSchema(): array
    {
        return (array) ($this->data['options'] ?? []);
    }

    /** @return array<string, string|array<int, string>> The chosen selections. */
    public function installedOptions(): array
    {
        return (array) ($this->data['installed_options'] ?? []);
    }

    /** @return array<int, string> Base composer packages the module always needs. */
    public function composerRequires(): array
    {
        return array_values((array) ($this->data['composer_requires'] ?? []));
    }

    /** @return array<int, string> Base composer dev packages the module always needs. */
    public function composerRequiresDev(): array
    {
        return array_values((array) ($this->data['composer_requires_dev'] ?? []));
    }

    /**
     * Merge a patch into the manifest and write it back (pretty, stable order).
     *
     * @param  array<string, mixed>  $patch
     */
    public function persist(array $patch): void
    {
        $this->data = array_merge($this->data, $patch);
        File::put($this->path, json_encode($this->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
    }
}
