<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Request;

class ImportResource extends JsonResource
{
    /** 
     * @param Request $request
     * @return array<string, mixed>
    */
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'file_name'           => $this->file_name,
            'total_records'       => $this->total_records,
            'successful_records'  => $this->successful_records,
            'failed_records'      => $this->failed_records,
            'status'              => $this->status,
            'created_at'          => $this->created_at,
        ];
    }
}