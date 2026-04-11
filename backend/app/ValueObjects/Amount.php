<?php

declare(strict_types=1);

namespace App\ValueObjects;

use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

final class Amount
{
    /** @var numeric-string */
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

    /**
     * @phpstan-assert numeric-string $value
     */
    private function assertValid(string $value): void
    {
        $validator = Validator::make(
            ['amount' => $value],
            [
                'amount' => [
                    'bail',
                    'required',
                    'numeric',
                    'regex:/^-?\d+(?:\.\d{0,2})?$/',
                    function (string $attribute, mixed $val, \Closure $fail): void {
                        if (bccomp((string) $val, '0', 2) <= 0) {
                            $fail('Kwota musi byc wieksza od 0');
                        }
                    },
                ],
            ],
            [
                'amount.required' => 'Kwota jest wymagana',
                'amount.numeric' => 'Kwota musi byc liczba',
                'amount.regex' => 'Kwota musi miec maksymalnie 2 miejsca po przecinku',
            ]
        );

        if ($validator->fails()) {
            throw new InvalidArgumentException((string) $validator->errors()->first('amount'));
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
