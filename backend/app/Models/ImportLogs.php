<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ImportLogs extends Model
{
    protected $fillable = [
        'import_id',
        'line_number',
        'raw_payload',
        'errors',
    ];

    protected function casts(): array
    {
        return [
            'line_number' => 'integer',
            'errors' => 'array',
        ];
    }

    public function import(): BelongsTo
    {
        return $this->belongsTo(Import::class);
    }
}
