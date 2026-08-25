<?php

declare(strict_types=1);

namespace Modules\DataImport\Support;

use Generator;
use RuntimeException;

/**
 * Streams a delimited file row by row. An import file is exactly the kind of
 * thing that arrives at 200k rows, so nothing here loads the whole file.
 */
class CsvReader
{
    public function __construct(private readonly string $path) {}

    /** @return array<int, string> */
    public function headers(): array
    {
        foreach ($this->rows(1) as $row) {
            return $row;
        }

        return [];
    }

    /**
     * @return Generator<int, array<int, string>>
     */
    public function rows(?int $limit = null): Generator
    {
        $handle = fopen($this->path, 'r');

        if ($handle === false) {
            throw new RuntimeException("Unable to read [{$this->path}].");
        }

        // Strip a UTF-8 BOM, or the first header comes back as "\xEF\xBB\xBFid"
        // and never matches a mapping.
        // The explicit escape argument is required from PHP 8.4: the default
        // is deprecated and changes in PHP 9. Passing '' selects the RFC 4180
        // behaviour, where a backslash is a literal character rather than an
        // escape — which is what a spreadsheet exports and what every importer
        // actually wants.
        $first = true;
        $count = 0;

        try {
            while (($row = fgetcsv($handle, null, ',', '"', '')) !== false) {
                if ($row === [null]) {
                    continue;   // blank line
                }

                if ($first) {
                    $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $row[0]);
                    $first  = false;
                }

                yield array_map(fn ($v): string => mb_trim((string) $v), $row);

                if ($limit !== null && ++$count >= $limit) {
                    return;
                }
            }
        } finally {
            fclose($handle);
        }
    }
}
