<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\Import\TransactionRowParser;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class TransactionRowParserTest extends TestCase
{
    private const VALID_IBAN = 'PL61109010140000071219812874';

    private TransactionRowParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new TransactionRowParser();
    }

    /**
     * Pełny wiersz ze standardowymi nazwami kolumn.
     */
    public function test_parse_accepts_canonical_column_names(): void
    {
        $dto = $this->parser->parse([
            'transaction_id' => 'TX-1',
            'account_number' => self::VALID_IBAN,
            'transaction_date' => '2024-06-15',
            'amount' => '100.50',
            'currency' => 'PLN',
        ]);

        $this->assertSame('TX-1', $dto->transactionId);
        $this->assertSame(self::VALID_IBAN, $dto->accountNumber->value());
        $this->assertSame('2024-06-15', $dto->transactionDate->toDateString());
        $this->assertSame('100.50', $dto->amount->value());
        $this->assertSame('PLN', $dto->currency->value());
    }

    /**
     * Alias kolumn zgodnie z kolejnością w parserze (id zamiast transaction_id).
     */
    #[DataProvider('aliasColumnSets')]
    public function test_parse_resolves_alternative_column_names(array $row): void
    {
        $dto = $this->parser->parse($row);

        $this->assertSame('ALT-42', $dto->transactionId);
        $this->assertSame(self::VALID_IBAN, $dto->accountNumber->value());
        $this->assertSame('PLN', $dto->currency->value());
    }

    public static function aliasColumnSets(): \Generator
    {
        yield 'id + iban + date + kwota + waluta' => [[
            'id' => 'ALT-42',
            'iban' => self::VALID_IBAN,
            'date' => '2024-01-02',
            'kwota' => '10.00',
            'waluta' => 'PLN',
        ]];

        yield 'txn_id + account + txn_date + value + curr' => [[
            'txn_id' => 'ALT-42',
            'account' => self::VALID_IBAN,
            'txn_date' => '2024-01-02',
            'value' => '10.00',
            'curr' => 'pln',
        ]];

        yield 'trans_id' => [[
            'trans_id' => 'ALT-42',
            'account_number' => self::VALID_IBAN,
            'transaction_date' => '2024-01-02',
            'amount' => '10.00',
            'currency' => 'PLN',
        ]];
    }

    /**
     * Klucze z białymi znakami i inną wielkością liter są normalizowane.
     */
    public function test_parse_normalizes_keys_with_mixed_case_and_whitespace(): void
    {
        $dto = $this->parser->parse([
            ' Transaction_ID ' => 'NORM-1',
            'IBAN' => self::VALID_IBAN,
            'DATE' => '2024-03-01',
            'Amount' => '5.00',
            'CURRENCY' => 'EUR',
        ]);

        $this->assertSame('NORM-1', $dto->transactionId);
        $this->assertSame('EUR', $dto->currency->value());
    }

    /**
     * Przy wielu aliasach pierwszy niepusty w kolejności definicji wygrywa.
     */
    public function test_parse_prefers_first_non_empty_alias_in_declaration_order(): void
    {
        $dto = $this->parser->parse([
            'transaction_id' => 'FIRST',
            'id' => 'SECOND',
            'account_number' => self::VALID_IBAN,
            'transaction_date' => '2024-01-01',
            'amount' => '1.00',
            'currency' => 'PLN',
        ]);

        $this->assertSame('FIRST', $dto->transactionId);
    }

    /**
     * Kwota z przecinkiem dziesiętnym i NBSP (jak w CSV z Excela).
     */
    #[DataProvider('amountBoundaryStrings')]
    public function test_parse_normalizes_amount_strings(string $raw, string $expectedAmount): void
    {
        $dto = $this->parser->parse([
            'transaction_id' => 'A1',
            'account_number' => self::VALID_IBAN,
            'transaction_date' => '2024-01-01',
            'amount' => $raw,
            'currency' => 'PLN',
        ]);

        $this->assertSame($expectedAmount, $dto->amount->value());
    }

    public static function amountBoundaryStrings(): \Generator
    {
        yield 'przecinek jako separator' => ['1,5', '1.50'];

        yield 'spacja jako separator tysiecy' => ['1 234,56', '1234.56'];

        yield 'NBSP jako separator tysiecy' => ["1\xc2\xa0234.50", '1234.50'];

        yield 'liczba zmiennoprzecinkowa float' => ['1.234567', '1.23'];
    }

    #[DataProvider('numericAmountScalars')]
    public function test_parse_accepts_int_and_float_amounts(mixed $raw, string $expectedAmount): void
    {
        $dto = $this->parser->parse([
            'transaction_id' => 'N1',
            'account_number' => self::VALID_IBAN,
            'transaction_date' => '2024-01-01',
            'amount' => $raw,
            'currency' => 'PLN',
        ]);

        $this->assertSame($expectedAmount, $dto->amount->value());
    }

    public static function numericAmountScalars(): \Generator
    {
        yield 'integer' => [42, '42.00'];

        yield 'float' => [3.14159, '3.14'];
    }

    /**
     * Data jest parsowana przez Carbon i normalizowana do początku dnia.
     */
    public function test_parse_strips_time_component_from_datetime_string(): void
    {
        $dto = $this->parser->parse([
            'transaction_id' => 'T1',
            'account_number' => self::VALID_IBAN,
            'transaction_date' => '2024-07-20 14:30:00',
            'amount' => '1.00',
            'currency' => 'PLN',
        ]);

        $this->assertSame('2024-07-20', $dto->transactionDate->toDateString());
        $this->assertSame('00:00:00', $dto->transactionDate->format('H:i:s'));
    }

    #[DataProvider('missingTransactionIdCases')]
    public function test_parse_throws_when_transaction_identifier_missing(array $row): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing transaction_id');

        $this->parser->parse($row);
    }

    public static function missingTransactionIdCases(): \Generator
    {
        yield 'brak wszystkich aliasów' => [[
            'account_number' => self::VALID_IBAN,
            'transaction_date' => '2024-01-01',
            'amount' => '1.00',
            'currency' => 'PLN',
        ]];

        yield 'pusty transaction_id' => [[
            'transaction_id' => '',
            'account_number' => self::VALID_IBAN,
            'transaction_date' => '2024-01-01',
            'amount' => '1.00',
            'currency' => 'PLN',
        ]];

        yield 'same biale znaki' => [[
            'id' => '   ',
            'account_number' => self::VALID_IBAN,
            'transaction_date' => '2024-01-01',
            'amount' => '1.00',
            'currency' => 'PLN',
        ]];
    }

    #[DataProvider('missingDateCases')]
    public function test_parse_throws_when_transaction_date_missing(array $row): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Missing transaction_date');

        $this->parser->parse($row);
    }

    public static function missingDateCases(): \Generator
    {
        yield 'brak kolumny daty' => [[
            'transaction_id' => 'X',
            'account_number' => self::VALID_IBAN,
            'amount' => '1.00',
            'currency' => 'PLN',
        ]];

        yield 'pusta data' => [[
            'transaction_id' => 'X',
            'account_number' => self::VALID_IBAN,
            'transaction_date' => '',
            'amount' => '1.00',
            'currency' => 'PLN',
        ]];
    }

    #[DataProvider('missingOrInvalidAmountCases')]
    public function test_parse_throws_when_amount_missing_or_unparseable(mixed $amount, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        $this->parser->parse([
            'transaction_id' => 'X',
            'account_number' => self::VALID_IBAN,
            'transaction_date' => '2024-01-01',
            'amount' => $amount,
            'currency' => 'PLN',
        ]);
    }

    public static function missingOrInvalidAmountCases(): \Generator
    {
        yield 'brak kwoty' => [null, 'Missing amount'];

        yield 'pusty string' => ['', 'Missing amount'];

        yield 'nie numeryczne' => ['12abc', 'Invalid amount: 12abc'];
    }

    public function test_parse_propagates_amount_vo_rejection_for_zero(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Amount must be greater than 0');

        $this->parser->parse([
            'transaction_id' => 'X',
            'account_number' => self::VALID_IBAN,
            'transaction_date' => '2024-01-01',
            'amount' => '0.00',
            'currency' => 'PLN',
        ]);
    }

    public function test_parse_propagates_amount_vo_rejection_for_negative(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Amount must be greater than 0');

        $this->parser->parse([
            'transaction_id' => 'X',
            'account_number' => self::VALID_IBAN,
            'transaction_date' => '2024-01-01',
            'amount' => '-5.00',
            'currency' => 'PLN',
        ]);
    }

    public function test_parse_propagates_invalid_iban_from_account_number(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid IBAN checksum');

        $this->parser->parse([
            'transaction_id' => 'X',
            'account_number' => 'PL61109010140000071219812875',
            'transaction_date' => '2024-01-01',
            'amount' => '1.00',
            'currency' => 'PLN',
        ]);
    }

    public function test_parse_propagates_invalid_currency_code(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Currency must be a 3-letter code');

        $this->parser->parse([
            'transaction_id' => 'X',
            'account_number' => self::VALID_IBAN,
            'transaction_date' => '2024-01-01',
            'amount' => '1.00',
            'currency' => 'PL',
        ]);
    }

    public function test_parse_propagates_empty_account_number(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Account number cannot be empty');

        $this->parser->parse([
            'transaction_id' => 'X',
            'account_number' => '',
            'transaction_date' => '2024-01-01',
            'amount' => '1.00',
            'currency' => 'PLN',
        ]);
    }
}
