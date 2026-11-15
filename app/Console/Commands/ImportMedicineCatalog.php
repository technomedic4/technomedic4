<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('app:import-medicine-catalog {file : CSV path with code,generic_name,strength,dosage_form,unit,price columns} {--prices-verified : Mark imported prices as verified by the facility}')]
#[Description('Import an authorized medicine catalog and its prices from CSV')]
class ImportMedicineCatalog extends Command
{
    public function handle(): int
    {
        $path = (string) $this->argument('file');

        if (! is_file($path) || ! is_readable($path)) {
            $this->error('Berkas CSV tidak ditemukan atau tidak dapat dibaca.');

            return self::FAILURE;
        }

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            $this->error('Berkas CSV tidak dapat dibuka.');

            return self::FAILURE;
        }

        try {
            $headers = fgetcsv($handle);

            if ($headers === false) {
                $this->error('Berkas CSV kosong.');

                return self::FAILURE;
            }

            $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
            $expectedHeaders = ['code', 'generic_name', 'strength', 'dosage_form', 'unit', 'price'];

            if ($headers !== $expectedHeaders) {
                $this->error('Kolom CSV harus berurutan: '.implode(',', $expectedHeaders).'.');

                return self::FAILURE;
            }

            $medicines = [];
            $codes = [];
            $line = 1;
            $now = now();

            while (($row = fgetcsv($handle)) !== false) {
                $line++;

                if ($row === [null]) {
                    continue;
                }

                if (count($row) !== count($expectedHeaders)) {
                    $this->error("Jumlah kolom tidak sesuai pada baris {$line}.");

                    return self::FAILURE;
                }

                [$code, $name, $strength, $form, $unit, $price] = array_map(
                    static fn (?string $value): string => trim((string) $value),
                    $row,
                );

                if (
                    $code === ''
                    || mb_strlen($code) > 100
                    || $name === ''
                    || mb_strlen($name) > 180
                    || mb_strlen($strength) > 100
                    || mb_strlen($form) > 100
                    || $unit === ''
                    || mb_strlen($unit) > 50
                    || ! ctype_digit($price)
                    || (int) $price < 1
                    || (int) $price > 999999999999
                    || isset($codes[$code])
                ) {
                    $this->error("Data obat tidak valid pada baris {$line}.");

                    return self::FAILURE;
                }

                $codes[$code] = true;
                $medicines[] = [
                    'code' => $code,
                    'generic_name' => $name,
                    'strength' => $strength !== '' ? $strength : null,
                    'dosage_form' => $form !== '' ? $form : null,
                    'unit' => $unit,
                    'price' => (int) $price,
                    'price_is_estimate' => ! $this->option('prices-verified'),
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if ($medicines === []) {
                $this->error('Berkas CSV tidak berisi data obat.');

                return self::FAILURE;
            }

            DB::table('medicine_catalog')->upsert(
                $medicines,
                ['code'],
                ['generic_name', 'strength', 'dosage_form', 'unit', 'price', 'is_active', 'updated_at'],
            );
        } finally {
            fclose($handle);
        }

        $this->info(count($medicines).' data obat berhasil dimuat.');

        return self::SUCCESS;
    }
}
