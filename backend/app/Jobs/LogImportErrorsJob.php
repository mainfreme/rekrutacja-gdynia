<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Import;
use App\Models\ImportLogs;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class LogImportErrorsJob implements ShouldQueue
{
    use Batchable;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<int, array{line: int, errors: array<int, string>, row?: array<string, mixed>, raw?: string}>  $records
     */
    public function __construct(
        public readonly int $importId,
        public readonly array $records,
    ) {
    }

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        foreach ($this->records as $record) {
            $line = (int) ($record['line'] ?? 0);
            $errors = $record['errors'] ?? [];

            ImportLogs::query()->create([
                'import_id' => $this->importId,
                'transaction_id' => Str::limit(
                    $this->guessTransactionId($record, $line),
                    255,
                    '',
                ),
                'error_message' => implode('; ', $errors),
                'created_at' => now(),
            ]);
        }

        $count = count($this->records);

        if ($count > 0) {
            Import::where('id', $this->importId)->increment('failed_records', $count);
        }
    }

    /**
     * @param  array{line?: int, row?: array<string, mixed>|null, errors?: array<int, string>, raw?: string}  $record
     */
    private function guessTransactionId(array $record, int $line): string
    {
        $row = $record['row'] ?? null;

        if (is_array($row)) {
            foreach (['transaction_id', 'id', 'txn_id', 'trans_id'] as $k) {
                foreach ($row as $rk => $rv) {
                    if (strtolower((string) $rk) === $k && $rv !== null && $rv !== '') {
                        return (string) $rv;
                    }
                }
            }
        }

        return 'line:'.$line;
    }
}
