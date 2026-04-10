<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\ValueObjects\Amount;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AmountTest extends TestCase
{
    #[DataProvider('validAmounts')]
    public function test_accepts_positive_amount(string $input, string $expectedStored): void
    {
        $amount = new Amount($input);

        $this->assertSame($expectedStored, $amount->value());
    }

    public static function validAmounts(): \Generator
    {
        yield 'calkowita' => [
            '100',
            '100',
        ];

        yield 'ulamek dziesietny' => [
            '12.34',
            '12.34',
        ];

        yield 'granica — minimalna dodatnia (0.01)' => [
            '0.01',
            '0.01',
        ];

        yield 'zera wiodace i koncowe — bez normalizacji w VO' => [
            '0001.50',
            '0001.50',
        ];

        yield 'kropka na koncu — is_numeric' => [
            '10.',
            '10.',
        ];
    }

    #[DataProvider('notNumericCases')]
    public function test_rejects_when_not_numeric(string $input): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Amount must be numeric');

        new Amount($input);
    }

    public static function notNumericCases(): \Generator
    {
        yield 'pusty string' => [''];

        yield 'same biale znaki' => ['   '];

        yield 'przecinek zamiast kropki' => ['1,5'];

        yield 'dwa separatory' => ['1.2.3'];

        yield 'nie numeryczne' => ['abc'];
    }

    #[DataProvider('notPositiveCases')]
    public function test_rejects_when_not_greater_than_zero(string $input): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Amount must be greater than 0');

        new Amount($input);
    }

    public static function notPositiveCases(): \Generator
    {
        yield 'zero' => ['0'];

        yield 'zero z czescia ulamkowa' => ['0.00'];

        yield 'ujemna' => ['-10'];

        yield 'ujemny ulamek' => ['-0.01'];
    }

    #[DataProvider('invalidPrecisionOrFormatCases')]
    public function test_rejects_when_precision_or_format_is_invalid(string $input): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Amount must have up to 2 decimal places');

        new Amount($input);
    }

    public static function invalidPrecisionOrFormatCases(): \Generator
    {
        yield 'za duzo miejsc po przecinku' => ['0.001'];

        yield 'notacja naukowa' => ['1e2'];

        yield 'spacje wokol liczby — bccomp nie akceptuje' => ['  42.7 '];

        yield 'znak nowej linii — bccomp nie akceptuje' => ["  42.7 \n"];
    }

    public function test_equals_true_when_values_equal_at_scale_two(): void
    {
        $a = new Amount('1.50');
        $b = new Amount('01.5');

        $this->assertTrue($a->equals($b));
    }

    public function test_equals_false_when_values_differ(): void
    {
        $a = new Amount('10');
        $b = new Amount('10.01');

        $this->assertFalse($a->equals($b));
    }

    public function test_to_string_matches_value(): void
    {
        $amount = new Amount('3.14');

        $this->assertSame($amount->value(), (string) $amount);
    }
}
