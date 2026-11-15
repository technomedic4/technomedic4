<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MedicineCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('seeders/data/medicine_catalog.csv');
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException('Berkas katalog obat tidak dapat dibuka.');
        }

        try {
            $headers = fgetcsv($handle);

            if ($headers !== ['code', 'generic_name', 'strength', 'dosage_form', 'unit', 'price']) {
                throw new RuntimeException('Format berkas katalog obat tidak sesuai.');
            }

            $medicines = [];
            $codes = [];
            $now = now();

            while (($row = fgetcsv($handle)) !== false) {
                if (count($row) !== 6) {
                    throw new RuntimeException('Data katalog obat tidak valid.');
                }

                [$code, $name, $strength, $form, $unit, $price] = array_map(
                    static fn (?string $value): string => trim((string) $value),
                    $row,
                );

                if (
                    $code === ''
                    || $name === ''
                    || $unit === ''
                    || ! ctype_digit($price)
                    || (int) $price < 1
                    || isset($codes[$code])
                ) {
                    throw new RuntimeException('Data katalog obat tidak valid.');
                }

                $codes[$code] = true;
                $medicines[] = [
                    'code' => $code,
                    'generic_name' => $name,
                    'strength' => $strength !== '' ? $strength : null,
                    'dosage_form' => $form !== '' ? $form : null,
                    'unit' => $unit,
                    'price' => (int) $price,
                    'price_is_estimate' => true,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if ($medicines === []) {
                throw new RuntimeException('Berkas katalog obat tidak berisi data.');
            }

            DB::table('medicine_catalog')->insertOrIgnore($medicines);
        } finally {
            fclose($handle);
        }
    }
}
