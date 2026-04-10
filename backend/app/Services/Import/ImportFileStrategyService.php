<?php

declare(strict_types=1);

namespace App\Services\Import;

use App\Services\Import\Strategies\ImportSourceFactory;

final class ImportFileStrategyService
{
    public function __construct(
        private readonly ImportSourceFactory $importSourceFactory,
    ) {
    }

    /**
     * @param  'csv'|'json'|'xml'  $extension
     */
    public function resolve(string $extension, string $absolutePath): ImportSourceInterface
    {
        return $this->importSourceFactory->make($extension, $absolutePath);
    }
}
