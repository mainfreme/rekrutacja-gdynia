<?php

declare(strict_types=1);

namespace App\ValueObjects;

use InvalidArgumentException;

final class Amount
{
    private string $value;

    public function __construct(string $value)
    {
        $this->assertValid($value);

        $this->value = $value;
    }

    public function value(): string
    {
        return $this->value;
    }

    private function assertValid(string $value): void
    {
        if (!is_numeric($value)) {
            throw new InvalidArgumentException('Amount must be numeric');
        }

        if (!preg_match('/^-?\d+(?:\.\d{0,2})?$/', $value)) {
            throw new InvalidArgumentException('Amount must have up to 2 decimal places');
        }

        if (bccomp($value, '0', 2) <= 0) {
            throw new InvalidArgumentException('Amount must be greater than 0');
        }
    }

    public function equals(self $other): bool
    {
        return bccomp($this->value, $other->value, 2) === 0;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
