<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Dto\TransactionDataDto;
use App\ValueObjects\AccountNumber;
use App\ValueObjects\Amount;
use App\ValueObjects\Currency;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class TransactionDataDtoTest extends TestCase
{
    private const IBAN = 'PL61109010140000071219812874';

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Kontrakt wyjścia: stały zestaw kluczy i typy wartości użyteczne dalej (create / logi).
     */
    public function test_toArray_exposes_stable_schema_and_scalar_payload(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-12-31 09:15:00', 'UTC'));

        $dto = new TransactionDataDto(
            transactionId: 'IMP-99',
            accountNumber: new AccountNumber(self::IBAN),
            transactionDate: Carbon::parse('2025-06-01')->startOfDay(),
            amount: new Amount('123.45'),
            currency: new Currency('usd'),
        );

        $row = $dto->toArray();

        $this->assertSame(
            ['transaction_id', 'account_number', 'transaction_date', 'amount', 'currency', 'created_at'],
            array_keys($row),
        );

        $this->assertSame([
            'transaction_id' => 'IMP-99',
            'account_number' => self::IBAN,
            'transaction_date' => '2025-06-01',
            'amount' => '123.45',
            'currency' => 'USD',
            'created_at' => '2025-12-31',
        ], $row);
    }

    /**
     * Data transakcji jest serializowana jako dzień kalendarzowy, nie jako pełny timestamp.
     */
    #[DataProvider('transactionDatesWithTime')]
    public function test_toArray_uses_date_string_independent_of_time_on_carbon_instance(Carbon $transactionDate, string $expectedDateKey): void
    {
        Carbon::setTestNow('2024-02-29 18:00:00');

        $dto = new TransactionDataDto(
            transactionId: 'T',
            accountNumber: new AccountNumber(self::IBAN),
            transactionDate: $transactionDate,
            amount: new Amount('0.01'),
            currency: new Currency('PLN'),
        );

        $this->assertSame($expectedDateKey, $dto->toArray()['transaction_date']);
    }

    public static function transactionDatesWithTime(): \Generator
    {
        yield 'koniec dnia — nadal ten sam dzień kalendarzowy' => [
            Carbon::parse('2020-01-15 23:59:59'),
            '2020-01-15',
        ];

        yield 'start dnia' => [
            Carbon::parse('2020-01-15 00:00:01'),
            '2020-01-15',
        ];

        yield 'rok przestępny' => [
            Carbon::parse('2024-02-29 12:00:00'),
            '2024-02-29',
        ];
    }

    /**
     * created_at podąża za bieżącym czasem aplikacji (granica zależności od now()).
     */
    public function test_toArray_created_at_follows_test_now_boundary_midnight_utc(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-10 00:00:01', 'UTC'));

        $dto = new TransactionDataDto(
            transactionId: 'X',
            accountNumber: new AccountNumber(self::IBAN),
            transactionDate: Carbon::parse('2026-01-01'),
            amount: new Amount('1.00'),
            currency: new Currency('PLN'),
        );

        $this->assertSame('2026-04-10', $dto->toArray()['created_at']);
    }

    public function test_toArray_created_at_changes_when_clock_advances(): void
    {
        Carbon::setTestNow('2026-01-01 10:00:00');
        $dto = new TransactionDataDto(
            transactionId: 'X',
            accountNumber: new AccountNumber(self::IBAN),
            transactionDate: Carbon::parse('2026-01-01'),
            amount: new Amount('1.00'),
            currency: new Currency('PLN'),
        );

        $first = $dto->toArray()['created_at'];

        Carbon::setTestNow('2026-01-02 10:00:00');
        $second = $dto->toArray()['created_at'];

        $this->assertSame('2026-01-01', $first);
        $this->assertSame('2026-01-02', $second);
    }
}
