<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\ValueObjects\Currency;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class CurrencyTest extends TestCase
{
    #[DataProvider('validCurrencyCodes')]
    public function test_accepts_valid_currency_codes(string $input, string $expectedStored): void
    {
        $currency = new Currency($input);

        $this->assertSame($expectedStored, $currency->value());
    }

    public static function validCurrencyCodes(): \Generator
    {
        yield 'uppercase code' => ['PLN', 'PLN'];
        yield 'lowercase code normalized to uppercase' => ['usd', 'USD'];
        yield 'mixed case normalized to uppercase' => ['eUr', 'EUR'];
    }

    #[DataProvider('invalidCurrencyCodes')]
    public function test_rejects_invalid_currency_codes(string $input, string $expectedMessage): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedMessage);

        new Currency($input);
    }

    public static function invalidCurrencyCodes(): \Generator
    {
        yield 'empty string' => ['', 'Waluta jest wymagana'];
        yield 'too short' => ['PL', 'Waluta musi miec 3 litery (e.g. PLN, USD)'];
        yield 'too long' => ['PLNN', 'Waluta musi miec 3 litery (e.g. PLN, USD)'];
        yield 'contains digit' => ['U1D', 'Waluta musi miec 3 litery (e.g. PLN, USD)'];
        yield 'contains space' => ['US D', 'Waluta musi miec 3 litery (e.g. PLN, USD)'];
        yield 'contains dash' => ['US-', 'Waluta musi miec 3 litery (e.g. PLN, USD)'];
        yield 'contains special character' => ['€UR', 'Waluta musi miec 3 litery (e.g. PLN, USD)'];
        yield 'leading and trailing spaces' => [' usd ', 'Waluta musi miec 3 litery (e.g. PLN, USD)'];
    }

    public function test_equals_true_for_same_code_after_normalization(): void
    {
        $a = new Currency('usd');
        $b = new Currency('USD');

        $this->assertTrue($a->equals($b));
    }

    public function test_equals_false_for_different_codes(): void
    {
        $a = new Currency('PLN');
        $b = new Currency('USD');

        $this->assertFalse($a->equals($b));
    }

    public function test_to_string_matches_stored_value(): void
    {
        $currency = new Currency('pln');

        $this->assertSame($currency->value(), (string) $currency);
    }
}
