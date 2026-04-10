<?php

declare(strict_types=1);

namespace App\Services\Import\Strategies;

use App\Services\Import\ImportSourceInterface;

final class XmlImportSource implements ImportSourceInterface
{
    public function __construct(
        private readonly string $path,
        private readonly string $recordLocalName = 'row',
    ) {}

    public function records(): \Generator
    {
        $reader = new \XMLReader;
        if (! $reader->open($this->path)) {
            throw new \RuntimeException("Cannot open XML file: {$this->path}");
        }

        $line = 0;
        try {
            while ($reader->read()) {
                if ($reader->nodeType !== \XMLReader::ELEMENT || $reader->localName !== $this->recordLocalName) {
                    continue;
                }

                if ($reader->isEmptyElement) {
                    $line++;
                    yield [
                        'line' => $line,
                        'errors' => ['Empty record element.'],
                        'raw' => '',
                    ];

                    continue;
                }

                $outer = $reader->readOuterXml();
                $line++;

                try {
                    $sx = new \SimpleXMLElement($outer);
                } catch (\Exception $e) {
                    yield [
                        'line' => $line,
                        'errors' => ['Invalid XML fragment: '.$e->getMessage()],
                        'raw' => $outer,
                    ];

                    continue;
                }

                $asJson = json_encode($sx, JSON_THROW_ON_ERROR);
                /** @var array<string, mixed> $row */
                $row = json_decode($asJson, true, 512, JSON_THROW_ON_ERROR);

                if (! is_array($row)) {
                    yield [
                        'line' => $line,
                        'errors' => ['Could not normalize XML record to an associative structure.'],
                        'raw' => $outer,
                    ];

                    continue;
                }

                if (array_is_list($row)) {
                    yield [
                        'line' => $line,
                        'errors' => ['Record element did not map to an object-like structure.'],
                        'raw' => $outer,
                    ];

                    continue;
                }

                yield [
                    'line' => $line,
                    'row' => $row,
                    'errors' => [],
                ];
            }
        } finally {
            $reader->close();
        }

        if ($line === 0) {
            throw new \InvalidArgumentException("No <{$this->recordLocalName}> elements found in XML.");
        }
    }
}
