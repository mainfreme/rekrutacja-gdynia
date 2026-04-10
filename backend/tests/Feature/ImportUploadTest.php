<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Import;
use App\Models\ImportLogs;
use App\Models\Transactions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class ImportUploadTest extends TestCase
{
    use RefreshDatabase;

    private const IBAN = 'PL61109010140000071219812874';

    public function test_csv_upload_inserts_rows_and_completes_import(): void
    {
        Storage::fake('local');

        $csv = <<<'CSV'
transaction_id,account_number,transaction_date,amount,currency
T1,PL61109010140000071219812874,2024-01-01,100.50,PLN
T2,PL61109010140000071219812874,2024-01-02,200.00,PLN
CSV;
        $file = UploadedFile::fake()->createWithContent('transactions.csv', $csv);

        $response = $this->postJson('/api/imports', [
            'file' => $file,
        ]);

        $response->assertStatus(202);
        $response->assertJsonPath('data.import.status', 'completed');

        $this->assertDatabaseCount('imports', 1);
        $this->assertDatabaseCount('transactions', 2);

        $import = Import::firstOrFail();
        $this->assertSame(2, $import->successful_records);
        $this->assertSame(0, $import->failed_records);
        $this->assertSame(2, $import->total_records);

        $row = Transactions::query()->where('transaction_id', 'T1')->firstOrFail();
        $this->assertSame(self::IBAN, $row->account_number);
        $this->assertSame('100.50', $row->amount);
    }

    public function test_mismatched_column_count_records_import_error(): void
    {
        Storage::fake('local');

        $csv = <<<'CSV'
transaction_id,account_number,transaction_date,amount,currency
T1,PL61109010140000071219812874,2024-01-01,100.50,PLN
only_one
CSV;
        $file = UploadedFile::fake()->createWithContent('bad.csv', $csv);

        $response = $this->postJson('/api/imports', [
            'file' => $file,
        ]);

        $response->assertStatus(202);
        $response->assertJsonPath('data.import.status', 'completed');

        $import = Import::firstOrFail();
        $this->assertSame(1, $import->successful_records);
        $this->assertSame(1, $import->failed_records);

        $this->assertTrue(
            ImportLogs::query()
                ->where('import_id', $import->id)
                ->where('transaction_id', 'line:3')
                ->where('error_message', 'like', '%Column count%')
                ->exists()
        );
    }

    public function test_invalid_json_file_fails_import_job(): void
    {
        Storage::fake('local');

        $file = UploadedFile::fake()->createWithContent('bad.json', '{ not valid json');

        $response = $this->postJson('/api/imports', [
            'file' => $file,
        ]);

        $response->assertStatus(202);

        $import = Import::firstOrFail();
        $this->assertSame('failed', $import->status);
    }
}
