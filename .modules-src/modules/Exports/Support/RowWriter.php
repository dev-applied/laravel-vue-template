<?php

declare(strict_types=1);

namespace Modules\Exports\Support;

/**
 * Writes rows to a local temp file one at a time. Implementations must never
 * hold the full result set in memory — an export exists precisely because the
 * listing is too big to hand over in a request.
 */
interface RowWriter
{
    public static function extension(): string;

    /** @param  array<int, string>  $headers */
    public function open(string $path, array $headers): void;

    /** @param  array<int, mixed>  $row */
    public function write(array $row): void;

    public function close(): void;
}
