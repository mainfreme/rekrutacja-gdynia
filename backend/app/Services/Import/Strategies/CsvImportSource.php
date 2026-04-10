<?php

declare(strict_types=1);

namespace App\Services\Import\Strategies;

use App\Services\Import\CsvRowReader;
use App\Services\Import\ImportSourceInterface;

final class CsvImportSource implements ImportSourceInterface
{
    public function __construct(
        private readonly string $path,
        private readonly string $delimiter = ',',
    ) {
    }

    public function records(): \Generator
    {
        $reader = new CsvRowReader($this->path, $this->delimiter);
        $headers = null;

        foreach ($reader->lines() as $row) {
            $lineNumber = $row['line'];
            $fields = $row['fields'];

            if ($headers === null) {
                $headers = $this->normalizeHeaders($fields);
                if ($this->headersEmpty($headers)) {
                    throw new \InvalidArgumentException('CSV has no header row.');
                }

                continue;
            }

            if ($this->rowIsCompletelyEmpty($fields)) {
                continue;
            }

            if (count($fields) !== count($headers)) {
                yield [
                    'line' => $lineNumber,
                    'errors' => ['Column count does not match header count.'],
                    'raw' => $this->rawFromFields($fields),
                ];

                continue;
            }

            $data = array_combine($headers, $fields);
            if ($data === false) {
                yield [
                    'line' => $lineNumber,
                    'errors' => ['Could not map CSV columns to headers.'],
                    'raw' => $this->rawFromFields($fields),
                ];

                continue;
            }

            yield [
                'line' => $lineNumber,
                'row' => $data,
                'errors' => [],
            ];
        }

        if ($headers === null) {
            throw new \InvalidArgumentException('CSV file is empty.');
        }
    }

    /**
     * @param  array<int, string|null>  $headers
     * @return array<int, string>
     */
    private function normalizeHeaders(array $headers): array
    {
        $headers = array_map(fn ($h) => is_string($h) ? trim($h) : (string) $h, $headers);

        if (isset($headers[0])) {
            $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]) ?? $headers[0];
        }

        return $headers;
    }

    /**
     * @param  array<int, string|null>  $headers
     */
    private function headersEmpty(array $headers): bool
    {
        foreach ($headers as $h) {
            if ($h !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, string|null>  $fields
     */
    private function rowIsCompletelyEmpty(array $fields): bool
    {
        foreach ($fields as $v) {
            if ($v === null || $v === '') {
                continue;
            }
            if (trim((string) $v) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, string|null>  $fields
     */
    private function rawFromFields(array $fields): string
    {
        return implode(',', array_map(fn ($v) => (string) ($v ?? ''), $fields));
    }
}
