<?php

declare(strict_types=1);

namespace App\Support\Modules;

use InvalidArgumentException;

/**
 * Turn a module's options schema + inputs into concrete selections.
 *
 * Precedence per option: an explicit --option flag wins; else the prompter is
 * asked (interactive); else the schema default. The prompter is injected so
 * this class stays free of Laravel Prompts and is unit-testable.
 */
class ModuleOptionResolver
{
    /**
     * @param  array<string, mixed>  $schema  module.json "options"
     * @param  array<int, string>  $flagPairs  ["auth=sanctum+oauth", "channels=sms,email"]
     * @param  (callable(string, array<string, mixed>): (string|array<int,string>|bool|null))|null  $prompt
     *                                                                                                       Called for options with no flag when interactive; null = non-interactive (use defaults).
     * @return array<string, string|array<int, string>> selected choice key(s) per option
     */
    public function resolve(array $schema, array $flagPairs, ?callable $prompt): array
    {
        $flags    = $this->parseFlags($flagPairs);
        $resolved = [];

        foreach ($schema as $key => $def) {
            $type = $def['type'] ?? 'select';

            if (array_key_exists($key, $flags)) {
                $value = $this->coerce($type, $flags[$key]);
            } elseif ($prompt !== null) {
                $answer = $prompt($key, (array) $def);
                $value  = $answer === null ? $this->default($type, (array) $def) : $this->coerce($type, $answer);
            } else {
                $value = $this->default($type, (array) $def);
            }

            $this->validate($key, (array) $def, $value);
            $resolved[$key] = $value;
        }

        return $resolved;
    }

    /**
     * @param  array<int, string>  $flagPairs
     * @return array<string, string>
     */
    private function parseFlags(array $flagPairs): array
    {
        $out = [];

        foreach ($flagPairs as $pair) {
            if (! str_contains($pair, '=')) {
                throw new InvalidArgumentException("--option must be key=value, got \"{$pair}\".");
            }
            [$k, $v]          = explode('=', $pair, 2);
            $out[mb_trim($k)] = mb_trim($v);
        }

        return $out;
    }

    /**
     * @param  string|array<int, string>|bool  $raw
     * @return string|array<int, string>|bool
     */
    private function coerce(string $type, string|array|bool $raw): string|array|bool
    {
        if ($type === 'confirm') {
            return is_bool($raw) ? $raw : in_array(mb_strtolower((string) $raw), ['1', 'true', 'yes', 'on'], true);
        }

        if ($type === 'multiselect') {
            return is_array($raw) ? array_values($raw) : array_values(array_filter(array_map('trim', explode(',', (string) $raw))));
        }

        return is_array($raw) ? (string) ($raw[0] ?? '') : (string) $raw;
    }

    /**
     * @param  array<string, mixed>  $def
     * @return string|array<int, string>|bool
     */
    private function default(string $type, array $def): string|array|bool
    {
        if (array_key_exists('default', $def)) {
            return $this->coerce($type, $def['default']);
        }

        return match ($type) {
            'confirm'     => false,
            'multiselect' => [],
            default       => (string) array_key_first((array) ($def['choices'] ?? [])),
        };
    }

    /**
     * @param  array<string, mixed>  $def
     * @param  string|array<int, string>|bool  $value
     */
    private function validate(string $key, array $def, string|array|bool $value): void
    {
        $type = $def['type'] ?? 'select';

        if ($type === 'confirm') {
            return;
        }

        $valid = array_keys((array) ($def['choices'] ?? []));

        foreach ((array) $value as $choice) {
            if (! in_array($choice, $valid, true)) {
                throw new InvalidArgumentException(
                    "Option \"{$key}\": unknown choice \"{$choice}\". Valid: ".implode(', ', $valid).'.'
                );
            }
        }
    }
}
