<?php

declare(strict_types=1);

namespace App\ValueObjects;

use Illuminate\Support\Facades\Validator;
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
        $normalized = strtoupper($value);

        $validator = Validator::make(
            ['currency' => $normalized],
            [
                'currency' => [
                    'bail',
                    'required',
                    'regex:/^[A-Z]{3}$/',
                ],
            ],
            [
                'currency.required' => 'Waluta jest wymagana',
                'currency.regex' => 'Waluta musi miec 3 litery (e.g. PLN, USD)',
            ]
        );

        if ($validator->fails()) {
            throw new InvalidArgumentException((string) $validator->errors()->first('currency'));
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
