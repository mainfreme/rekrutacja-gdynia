<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Import;
use App\Services\Import\ImportFileStrategyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessImportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $timeout = 0;

    private const CHUNK_SIZE = 50;

    public function __construct(
        public int $importId,
        public string $filePath,
    ) {
    }

    public function handle(ImportFileStrategyService $importFileStrategy): void
    {
        $import = Import::query()->findOrFail($this->importId);
        $import->update(['status' => 'processing']);

        $fullPath = Storage::path($this->filePath);
        $extension = strtolower((string) pathinfo($fullPath, PATHINFO_EXTENSION));

        try {
            $source = $importFileStrategy->resolve(
                self::assertSupportedImportExtension($extension),
                $fullPath
            );
        } catch (Throwable $e) {
            $import->update(['status' => 'failed']);
            Storage::delete($this->filePath);

            return;
        }

        $jobs = [];
        $rowBuffer = [];
        $errorBuffer = [];
        $totalRecords = 0;

        try {
            foreach ($source->records() as $record) {
                $totalRecords++;

                if (! empty($record['errors'])) {
                    $errorBuffer[] = $record;

                    if (count($errorBuffer) >= self::CHUNK_SIZE) {
                        $jobs[] = new LogImportErrorsJob($this->importId, $errorBuffer);
                        $errorBuffer = [];
                    }

                    continue;
                }

                $rowBuffer[] = [
                    'row' => $record['row'] ?? [],
                    'line' => (int) ($record['line'] ?? 0),
                ];

                if (count($rowBuffer) >= self::CHUNK_SIZE) {
                    $jobs[] = new ProcessImportRowsJob($this->importId, $rowBuffer);
                    $rowBuffer = [];
                }
            }
        } catch (Throwable) {
            $import->update(['status' => 'failed']);
            Storage::delete($this->filePath);

            return;
        }

        if ($errorBuffer !== []) {
            $jobs[] = new LogImportErrorsJob($this->importId, $errorBuffer);
        }

        if ($rowBuffer !== []) {
            $jobs[] = new ProcessImportRowsJob($this->importId, $rowBuffer);
        }

        $import->update(['total_records' => $totalRecords]);

        if ($jobs === []) {
            $import->update(['status' => 'completed']);
            Storage::delete($this->filePath);

            return;
        }

        $importId = $this->importId;
        $filePath = $this->filePath;

        Bus::batch($jobs)
            ->allowFailures()
            ->name("import-{$importId}")
            ->finally(function () use ($importId, $filePath) {
                Import::where('id', $importId)->update(['status' => 'completed']);
                Storage::delete($filePath);
            })
            ->dispatch();
    }

    /**
     * @return 'csv'|'json'|'xml'
     */
    private static function assertSupportedImportExtension(string $extension): string
    {
        return match ($extension) {
            'csv', 'json', 'xml' => $extension,
            default => throw new \InvalidArgumentException(
                sprintf('Unsupported import file extension: %s', $extension)
            ),
        };
    }
}
