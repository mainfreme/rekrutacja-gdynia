<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use RuntimeException;

final class GenerateImportJsonFileCommand extends Command
{
    protected $signature = 'import:generate-json-file
                            {path? : Ścieżka pliku wyjściowego (domyślnie: exports/import.json — w Dockerze widoczna na hoście w ./exports/)}
                            {--limit=10000 : Liczba rekordów w tablicy JSON}';

    protected $description = 'Generuje plik JSON z losowymi transakcjami (do testów importu)';

    public function handle(): int
    {
        $limit = max(1, (int) $this->option('limit'));
        $path = $this->argument('path')
            ?? base_path('exports/import.json');

        File::ensureDirectoryExists(dirname($path));

        $handle = fopen($path, 'w');
        if ($handle === false) {
            $this->error("Nie można utworzyć pliku: {$path}");

            return self::FAILURE;
        }

        fwrite($handle, "[\n");

        $this->info("Zapisuję {$limit} rekordów do: {$path}");

        $bar = $this->output->createProgressBar($limit);

        for ($i = 0; $i < $limit; $i++) {
            $record = [
                'transaction_id' => uniqid('', true),
                'account_number' => $this->randomPolishIban(),
                'transaction_date' => $this->randomDate(),
                'amount' => random_int(100, 200_000),
                'currency' => $this->randomCurrency(),
            ];

            fwrite($handle, json_encode($record, JSON_THROW_ON_ERROR));

            if ($i < $limit - 1) {
                fwrite($handle, ",\n");
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        fwrite($handle, "\n]");
        fclose($handle);

        $this->info('Gotowe.');

        return self::SUCCESS;
    }

    private function randomDate(): string
    {
        $start = strtotime('2025-01-01');
        $end = strtotime('2025-12-31');

        return date('Y-m-d', random_int($start, $end));
    }

    /**
     * Losowy polski IBAN z poprawnym checksum (zgodny z walidacją {@see \App\ValueObjects\AccountNumber}).
     */
    private function randomPolishIban(): string
    {
        $bban = '';
        for ($j = 0; $j < 24; $j++) {
            $bban .= (string) random_int(0, 9);
        }

        for ($checkVal = 0; $checkVal <= 99; $checkVal++) {
            $check = str_pad((string) $checkVal, 2, '0', STR_PAD_LEFT);
            $iban = 'PL'.$check.$bban;
            if ($this->ibanChecksumValid($iban)) {
                return $iban;
            }
        }

        throw new RuntimeException('Nie udało się wygenerować poprawnego numeru IBAN.');
    }

    private function ibanChecksumValid(string $iban): bool
    {
        $rearranged = substr($iban, 4).substr($iban, 0, 4);

        $numeric = '';
        foreach (str_split($rearranged) as $char) {
            if (ctype_alpha($char)) {
                $numeric .= (string) (ord($char) - 55);
            } else {
                $numeric .= $char;
            }
        }

        return $this->mod97($numeric) === 1;
    }

    private function mod97(string $number): int
    {
        $checksum = 0;

        foreach (str_split($number, 7) as $chunk) {
            $checksum = (int) ($checksum.$chunk) % 97;
        }

        return $checksum;
    }

    /**
     * @return 'PLN'|'USD'|'EUR'
     */
    private function randomCurrency(): string
    {
        $currencies = ['PLN', 'USD', 'EUR'];

        return $currencies[array_rand($currencies)];
    }
}
