<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $created_at
 */
final class Import extends Model
{
    public $timestamps = false;

    protected $table = 'imports';

    protected $fillable = [
        'file_name',
        'total_records',
        'successful_records',
        'failed_records',
        'status',
        'created_at',
    ];

    /** @var array<string, int|string> */
    protected $attributes = [
        'total_records' => 0,
        'successful_records' => 0,
        'failed_records' => 0,
        'status' => 'pending',
    ];

    protected static function booted(): void
    {
        static::creating(function (Import $import): void {
            if ($import->created_at === null) {
                $import->created_at = now();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'total_records' => 'integer',
            'successful_records' => 'integer',
            'failed_records' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<ImportLogs, $this>
     */
    public function logs(): HasMany
    {
        return $this->hasMany(ImportLogs::class);
    }

    /**
     * @return HasMany<Transactions, $this>
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transactions::class);
    }
}
