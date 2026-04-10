<?php

declare(strict_types=1);

namespace App\Services\Import;

final class CsvRowReader
{
    public function __construct(
        private readonly string $path,
        private readonly string $delimiter = ',',
    ) {
    }

    /**
     * @return \Generator<int, array{line: int, fields: array<int, string|null>}>
     */
    public function lines(): \Generator
    {
        $handle = fopen($this->path, 'rb');
        if ($handle === false) {
            throw new \RuntimeException("Cannot open file for reading: {$this->path}");
        }

        try {
            $line = 0;
            while (($fields = fgetcsv($handle, 0, $this->delimiter)) !== false) {
                $line++;
                yield ['line' => $line, 'fields' => $fields];
            }
        } finally {
            fclose($handle);
        }
    }
}
