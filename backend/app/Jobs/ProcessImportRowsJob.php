<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Import;
use App\Models\ImportLogs;
use App\Models\Transactions;
use App\Services\Import\TransactionRowParser;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class ProcessImportRowsJob implements ShouldQueue
{
    use Batchable;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * @param  array<int, array{row: array<string, mixed>, line: int}>  $records
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

        $successful = 0;
        $failed = 0;

        $parser = new TransactionRowParser();

        foreach ($this->records as $record) {
            $row = $record['row'];
            $line = $record['line'];

            try {
                $dto = $parser->parse($row);
                Transactions::query()->create($dto->toArray());
                $successful++;
            } catch (\Throwable $e) {
                $failed++;
                ImportLogs::query()->create([
                    'import_id' => $this->importId,
                    'transaction_id' => Str::limit(
                        $this->guessTransactionId($row, $line),
                        255,
                        '',
                    ),
                    'error_message' => $e->getMessage(),
                    'created_at' => now(),
                ]);
            }
        }

        if ($successful > 0) {
            Import::where('id', $this->importId)->increment('successful_records', $successful);
        }

        if ($failed > 0) {
            Import::where('id', $this->importId)->increment('failed_records', $failed);
        }
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function guessTransactionId(array $row, int $line): string
    {
        foreach (['transaction_id', 'id', 'txn_id', 'trans_id'] as $k) {
            foreach ($row as $rk => $rv) {
                if (strtolower((string) $rk) === $k && $rv !== null && $rv !== '') {
                    return (string) $rv;
                }
            }
        }

        return 'line:'.$line;
    }
}
