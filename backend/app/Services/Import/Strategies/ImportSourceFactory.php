<?php

declare(strict_types=1);

namespace App\Services\Import\Strategies;

use App\Services\Import\ImportSourceInterface;

final class ImportSourceFactory
{
    public function make(string $type, string $absolutePath): ImportSourceInterface
    {
        return match ($type) {
            'csv' => new CsvImportSource($absolutePath),
            'json' => new JsonImportSource($absolutePath),
            'xml' => new XmlImportSource($absolutePath),
            default => throw new \InvalidArgumentException(
                "Import type [{$type}] is not supported."
            ),
        };
    }
}
