<?php

declare(strict_types=1);

namespace App\Dto;

use App\ValueObjects\AccountNumber;
use App\ValueObjects\Amount;
use App\ValueObjects\Currency;
use Carbon\Carbon;

final class TransactionDataDto
{
    public function __construct(
        public readonly string $transactionId,
        public readonly AccountNumber $accountNumber,
        public readonly Carbon $transactionDate,
        public readonly Amount $amount,
        public readonly Currency $currency,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'transaction_id' => $this->transactionId,
            'account_number' => $this->accountNumber->value(),
            'transaction_date' => $this->transactionDate->toDateString(),
            'amount' => $this->amount->value(),
            'currency' => $this->currency->value(),
            'created_at' => now()->toDateString(),
        ];
    }
}
