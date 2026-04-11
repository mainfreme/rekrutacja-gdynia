<?php

declare(strict_types=1);

namespace App\ValueObjects;

use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

final class AccountNumber
{
    private string $value;

    public function __construct(string $value)
    {
        $value = strtoupper(str_replace(' ', '', $value));

        $this->assertValid($value);

        $this->value = $value;
    }

    public function value(): string
    {
        return $this->value;
    }

    private function assertValid(string $value): void
    {
        $validator = Validator::make(
            ['account' => $value],
            [
                'account' => [
                    'bail',
                    'required',
                    'regex:/^[A-Z]{2}[0-9A-Z]+$/',
                    function (string $attribute, mixed $iban, \Closure $fail): void {
                        if (!is_string($iban) || !$this->isValidIbanChecksum($iban)) {
                            $fail('Nieprawidlowy IBAN');
                        }
                    },
                ],
            ],
            [
                'account.required' => 'Numer konta jest wymagany',
                'account.regex' => 'Nieprawidlowy IBAN',
            ]
        );

        if ($validator->fails()) {
            throw new InvalidArgumentException((string) $validator->errors()->first('account'));
        }
    }

    private function isValidIbanChecksum(string $iban): bool
    {
        $rearranged = substr($iban, 4).substr($iban, 0, 4);

        $numeric = '';
        foreach (str_split($rearranged) as $char) {
            if (ctype_alpha($char)) {
                $numeric .= (string) (ord($char) - 55);
            } else {
                $numeric .= $char;
            }
        }

        return $this->mod97($numeric) === 1;
    }

    private function mod97(string $number): int
    {
        $checksum = 0;

        foreach (str_split($number, 7) as $chunk) {
            $checksum = (int) ($checksum.$chunk) % 97;
        }

        return $checksum;
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
