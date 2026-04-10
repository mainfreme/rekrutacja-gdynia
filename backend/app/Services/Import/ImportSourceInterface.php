<?php

declare(strict_types=1);

namespace App\Services\Import;

interface ImportSourceInterface
{
    public function records(): \Generator;
}
