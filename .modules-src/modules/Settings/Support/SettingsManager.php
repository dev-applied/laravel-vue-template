<?php

declare(strict_types=1);

namespace Modules\Settings\Support;

use Illuminate\Support\Facades\Cache;
use Modules\Settings\Models\Setting;

/**
 * Reads and writes settings.
 *
 * Settings get read on nearly every request — a nav label, a feature toggle, a
 * support address — so the whole table is cached as one entry rather than
 * queried per key. One row-set, one cache read, invalidated on any write.
 */
class SettingsManager
{
    private const CACHE_KEY = 'settings:all';

    /** @var array<string, mixed>|null */
    /** Bounds a missed invalidation; the write path still flushes explicitly. */
    private const CACHE_TTL = 86400;

    private ?array $memo = null;

    public function __construct(private readonly SettingRegistry $registry) {}

    public function get(string $key, mixed $fallback = null): mixed
    {
        $definition = $this->registry->has($key) ? $this->registry->get($key) : null;

        $stored = $this->stored();

        if (array_key_exists($key, $stored)) {
            return $definition?->cast($stored[$key]) ?? $stored[$key];
        }

        // An unset setting falls back to its declared default, so a fresh
        // install behaves like a configured one rather than like a broken one.
        return $fallback ?? $definition?->default;
    }

    public function set(string $key, mixed $value): void
    {
        Setting::updateOrCreate(['key' => $key], ['value' => $value]);

        $this->flush();
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        // Flushed once at the end, not per key — otherwise saving a screenful
        // of settings thrashes the cache and every concurrent request rebuilds
        // it from scratch.
        $this->flush();
    }

    /**
     * Every declared setting with its current value. Secrets are excluded —
     * use `get()` server-side when you actually need one.
     *
     * @return array<string, mixed>
     */
    public function allDeclared(bool $includeSecrets = false): array
    {
        $out = [];

        foreach ($this->registry->all() as $key => $definition) {
            if ($definition->isSecret && ! $includeSecrets) {
                continue;
            }

            $out[$key] = $this->get($key);
        }

        return $out;
    }

    /**
     * The public subset, safe to hand to a signed-out visitor.
     *
     * @return array<string, mixed>
     */
    public function publicValues(): array
    {
        $out = [];

        foreach ($this->registry->public() as $key => $definition) {
            // Belt and braces: a setting marked both public and secret is a
            // declaration mistake, and the safe reading is "secret".
            if ($definition->isSecret) {
                continue;
            }

            $out[$key] = $this->get($key);
        }

        return $out;
    }

    public function flush(): void
    {
        $this->memo = null;
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<string, mixed>
     */
    private function stored(): array
    {
        if ($this->memo !== null) {
            return $this->memo;
        }

        // A TTL rather than rememberForever. Every write calls flush(), so
        // this should never be the thing that refreshes it — but "forever"
        // means a single missed invalidation (a failed forget, a cache node
        // that dropped the delete) is permanent, with no way back short of
        // clearing the cache by hand. A day bounds that without making the
        // cache do any real work.
        return $this->memo = Cache::remember(
            self::CACHE_KEY,
            self::CACHE_TTL,
            fn () => Setting::query()->pluck('value', 'key')->all()
        );
    }
}
