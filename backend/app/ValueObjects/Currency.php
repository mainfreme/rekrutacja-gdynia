<?php

declare(strict_types=1);

namespace App\ValueObjects;

use InvalidArgumentException;

final class Currency
{
    private string $value;

    public function __construct(string $value)
    {
        $this->assertValid($value);

        $this->value = strtoupper($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    private function assertValid(string $value): void
    {
        // dokładnie 3 litery A-Z
        if (!preg_match('/^[A-Z]{3}$/', strtoupper($value))) {
            throw new InvalidArgumentException(
                'Currency must be a 3-letter code (e.g. PLN, USD)'
            );
        }
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}