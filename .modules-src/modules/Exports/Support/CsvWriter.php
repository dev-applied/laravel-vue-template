<?php

declare(strict_types=1);

namespace Modules\Exports\Support;

use RuntimeException;

/**
 * Native CSV — no dependency, constant memory. A UTF-8 BOM is written first so
 * Excel opens accented characters correctly instead of mojibake.
 */
class CsvWriter implements RowWriter
{
    /** @var resource|null */
    private $handle = null;

    public static function extension(): string
    {
        return 'csv';
    }

    /** @param  array<int, string>  $headers */
    public function open(string $path, array $headers): void
    {
        $handle = fopen($path, 'w');

        if ($handle === false) {
            throw new RuntimeException("Unable to open [{$path}] for writing.");
        }

        $this->handle = $handle;
        fwrite($this->handle, "\xEF\xBB\xBF");
        $this->write($headers);
    }

    /** @param  array<int, mixed>  $row */
    public function write(array $row): void
    {
        if ($this->handle === null) {
            throw new RuntimeException('Writer is not open.');
        }

        fputcsv($this->handle, array_map(
            fn (mixed $v): string => $v === null ? '' : (is_scalar($v) ? (string) $v : json_encode($v)),
            $row
        ));
    }

    public function close(): void
    {
        if ($this->handle !== null) {
            fclose($this->handle);
            $this->handle = null;
        }
    }
}
