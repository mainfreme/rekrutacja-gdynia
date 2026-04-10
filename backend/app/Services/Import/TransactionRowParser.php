<?php

declare(strict_types=1);

namespace App\Services\Import;

use App\Dto\TransactionDataDto;
use App\ValueObjects\AccountNumber;
use App\ValueObjects\Amount;
use App\ValueObjects\Currency;
use Carbon\Carbon;
use InvalidArgumentException;

final class TransactionRowParser
{
    /**
     * @param  array<string, mixed>  $row
     */
    public function parse(array $row): TransactionDataDto
    {
        $norm = [];
        foreach ($row as $key => $value) {
            $norm[strtolower(trim((string) $key))] = $value;
        }

        $txnId = $this->stringValue($norm, ['transaction_id', 'id', 'txn_id', 'trans_id']);
        if ($txnId === '') {
            throw new InvalidArgumentException('Missing transaction_id');
        }

        $accountRaw = $this->stringValue($norm, ['account_number', 'account', 'iban']);
        $accountNumber = new AccountNumber($accountRaw);

        $dateRaw = $this->scalarValue($norm, ['transaction_date', 'date', 'txn_date']);
        if ($dateRaw === null || $dateRaw === '') {
            throw new InvalidArgumentException('Missing transaction_date');
        }
        $transactionDate = Carbon::parse((string) $dateRaw)->startOfDay();

        $amountRaw = $this->scalarValue($norm, ['amount', 'value', 'kwota']);
        $amount = new Amount($this->parseAmount($amountRaw));

        $currencyRaw = $this->stringValue($norm, ['currency', 'curr', 'waluta']);
        $currency = new Currency($currencyRaw);

        return new TransactionDataDto(
            transactionId: $txnId,
            accountNumber: $accountNumber,
            transactionDate: $transactionDate,
            amount: $amount,
            currency: $currency,
        );
    }

    /**
     * @param  array<string, mixed>  $norm
     * @param  array<int, string>    $keys
     */
    private function stringValue(array $norm, array $keys): string
    {
        $v = $this->scalarValue($norm, $keys);

        return $v === null ? '' : trim((string) $v);
    }

    /**
     * @param  array<string, mixed>  $norm
     * @param  array<int, string>    $keys
     */
    private function scalarValue(array $norm, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $norm) && $norm[$key] !== null && $norm[$key] !== '') {
                return $norm[$key];
            }
        }

        return null;
    }

    private function parseAmount(mixed $raw): string
    {
        if ($raw === null || $raw === '') {
            throw new InvalidArgumentException('Missing amount');
        }

        if (is_int($raw) || is_float($raw)) {
            return number_format((float) $raw, 2, '.', '');
        }

        $s = trim((string) $raw);
        $s = str_replace([' ', "\xc2\xa0"], '', $s);
        $s = str_replace(',', '.', $s);

        if ($s === '' || ! is_numeric($s)) {
            throw new InvalidArgumentException('Invalid amount: '.(string) $raw);
        }

        return number_format((float) $s, 2, '.', '');
    }
}
