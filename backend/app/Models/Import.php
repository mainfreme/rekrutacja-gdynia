<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Import extends Model
{
    protected $fillable = [
        'file_name',
        'total_records',
        'successful_records',
        'failed_records',
        'status',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'total_records' => 'integer',
            'successful_records' => 'integer',
            'failed_records' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function logs(): HasMany
    {
        return $this->hasMany(ImportLogs::class);
    }
}
