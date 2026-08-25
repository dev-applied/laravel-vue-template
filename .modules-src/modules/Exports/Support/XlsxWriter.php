<?php

declare(strict_types=1);

namespace Modules\Exports\Support;

use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

/**
 * XLSX via openspout, which streams sheets rather than building a DOM.
 *
 * Only present when the module was installed with formats=csv+xlsx; the `csv`
 * choice drops this file and the openspout dependency with it.
 */
class XlsxWriter implements RowWriter
{
    private ?Writer $writer = null;

    public static function extension(): string
    {
        return 'xlsx';
    }

    /** @param  array<int, string>  $headers */
    public function open(string $path, array $headers): void
    {
        $this->writer = new Writer;
        $this->writer->openToFile($path);
        $this->write($headers);
    }

    /** @param  array<int, mixed>  $row */
    public function write(array $row): void
    {
        $this->writer?->addRow(Row::fromValues(array_map(
            fn (mixed $v): mixed => is_scalar($v) || $v === null ? $v : json_encode($v),
            $row
        )));
    }

    public function close(): void
    {
        $this->writer?->close();
        $this->writer = null;
    }
}
