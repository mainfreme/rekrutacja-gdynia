<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ImportLogs;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @mixin ImportLogs
 */
class ImportLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'import_id'       => $this->import_id,
            'transaction_id'  => $this->transaction_id,
            'error_message'   => $this->error_message,
            'created_at'      => Carbon::make($this->created_at)?->toIso8601String(),
        ];
    }
}
