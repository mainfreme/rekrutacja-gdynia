<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'               => $this->id,
            'import_id'        => $this->import_id,
            'transaction_id'   => $this->transaction_id,
            'account_number'   => $this->account_number,
            'transaction_date' => $this->transaction_date?->toDateString(),
            'amount'           => $this->amount,
            'currency'         => $this->currency,
            'created_at'       => $this->created_at?->toDateString(),
        ];
    }
}
