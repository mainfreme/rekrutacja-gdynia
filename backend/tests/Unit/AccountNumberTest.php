<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\ValueObjects\AccountNumber;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AccountNumberTest extends TestCase
{
    #[DataProvider('validIbans')]
    public function test_accepts_valid_iban(string $input, string $expectedCanonical): void
    {
        $accountNumber = new AccountNumber($input);

        $this->assertSame($expectedCanonical, $accountNumber->value());
    }

    public static function validIbans(): \Generator
    {
        yield 'PL — typowy' => [
            'PL61109010140000071219812874',
            'PL61109010140000071219812874',
        ];

        yield 'DE — inny kraj' => [
            'DE89370400440532013000',
            'DE89370400440532013000',
        ];

        yield 'dolna granica dlugosci (15 znakow)' => [
            'NO9386011117947',
            'NO9386011117947',
        ];

        yield 'gorna granica dlugosci (34 znaki)' => [
            'LC04BOSLCHBLSESTVQWF6D8LI70GE8M2C3',
            'LC04BOSLCHBLSESTVQWF6D8LI70GE8M2C3',
        ];

        yield 'normalizacja — spacje w grupach' => [
            'PL61 1090 1014 0000 0712 1981 2874',
            'PL61109010140000071219812874',
        ];

        yield 'normalizacja — male litery' => [
            'pl61109010140000071219812874',
            'PL61109010140000071219812874',
        ];
    }

    #[DataProvider('emptyValueCases')]
    public function test_rejects_when_value_empty_after_space_normalization(string $input): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Account number cannot be empty');

        new AccountNumber($input);
    }

    public static function emptyValueCases(): \Generator
    {
        yield 'pusty string' => [''];

        yield 'same biale znaki' => ['   '];
    }

    #[DataProvider('invalidIbanFormatCases')]
    public function test_rejects_when_iban_format_is_invalid(string $input): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid IBAN format');

        new AccountNumber($input);
    }

    public static function invalidIbanFormatCases(): \Generator
    {
        yield 'niedozwolony znak (myslnik)' => [
            'PL61-1090-1014-0000-0712-1981-2874',
        ];

        yield 'tabulatory i nowe linie nie sa usuwane' => [
            "DE89\t3704\n0044\r0532013000",
        ];

        yield 'kod kraju jako cyfry' => [
            '9961109010140000071219812874',
        ];
    }

    #[DataProvider('invalidIbanChecksumCases')]
    public function test_rejects_when_iban_checksum_is_invalid(string $input): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid IBAN checksum');

        new AccountNumber($input);
    }

    public static function invalidIbanChecksumCases(): \Generator
    {
        yield 'za krotki — 14 znakow (ponizej minimum 15)' => [
            'NO938601111794',
        ];

        yield 'za dlugi — 35 znakow (powyzej maksimum 34)' => [
            'LC04BOSLCHBLSESTVQWF6D8LI70GE8M2C31',
        ];

        yield 'bledna suma kontrolna (ostatnia cyfra)' => [
            'PL61109010140000071219812875',
        ];

        yield 'cyfry kontrolne jako litery' => [
            'PLxx109010140000071219812874',
        ];
    }

    public function test_equals_returns_true_for_same_iban_after_normalization(): void
    {
        $a = new AccountNumber('PL61 1090 1014 0000 0712 1981 2874');
        $b = new AccountNumber('pl61109010140000071219812874');

        $this->assertTrue($a->equals($b));
    }

    public function test_equals_returns_false_for_different_ibans(): void
    {
        $a = new AccountNumber('PL61109010140000071219812874');
        $b = new AccountNumber('DE89370400440532013000');

        $this->assertFalse($a->equals($b));
    }

    public function test_to_string_matches_value(): void
    {
        $account = new AccountNumber('pl61109010140000071219812874');

        $this->assertSame($account->value(), (string) $account);
    }
}
