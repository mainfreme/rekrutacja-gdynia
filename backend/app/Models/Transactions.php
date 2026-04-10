<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Transactions extends Model
{
    public $timestamps = false;

    protected $table = 'transactions';

    protected $fillable = [
        'import_id',
        'transaction_id',
        'account_number',
        'transaction_date',
        'amount',
        'currency',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'import_id' => 'integer',
            'transaction_date' => 'date',
            'amount' => 'decimal:2',
            'created_at' => 'date',
        ];
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(Import::class);
    }
}
