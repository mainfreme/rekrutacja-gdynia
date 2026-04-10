<?php

declare(strict_types=1);

namespace App\Services\Import\Strategies;

use App\Services\Import\ImportSourceInterface;

final class JsonImportSource implements ImportSourceInterface
{
    public function __construct(
        private readonly string $path,
    ) {
    }

    public function records(): \Generator
    {
        $json = file_get_contents($this->path);
        if ($json === false) {
            throw new \RuntimeException("Cannot read JSON file: {$this->path}");
        }

        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \InvalidArgumentException('Invalid JSON: '.$e->getMessage(), 0, $e);
        }

        if (is_array($decoded) && array_is_list($decoded)) {
            $line = 0;
            foreach ($decoded as $item) {
                $line++;
                if (! is_array($item)) {
                    yield [
                        'line' => $line,
                        'errors' => ['Each JSON array element must be an object (associative array).'],
                        'raw' => is_string($item) ? $item : json_encode($item, JSON_THROW_ON_ERROR),
                    ];

                    continue;
                }

                if (array_is_list($item)) {
                    yield [
                        'line' => $line,
                        'errors' => ['Expected JSON object with named fields, got a sequential array.'],
                        'raw' => json_encode($item, JSON_THROW_ON_ERROR),
                    ];

                    continue;
                }

                yield [
                    'line' => $line,
                    'row' => $item,
                    'errors' => [],
                ];
            }

            return;
        }

        if (is_array($decoded) && ! array_is_list($decoded)) {
            yield [
                'line' => 1,
                'row' => $decoded,
                'errors' => [],
            ];

            return;
        }

        throw new \InvalidArgumentException('JSON root must be an object or an array of objects.');
    }
}
