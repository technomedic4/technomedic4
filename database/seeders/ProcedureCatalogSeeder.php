<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ProcedureCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('seeders/data/procedure_tariffs.csv');
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            throw new RuntimeException('Berkas tarif tindakan tidak dapat dibuka.');
        }

        try {
            $headers = fgetcsv($handle);

            if ($headers !== ['Category', 'Name', 'Price']) {
                throw new RuntimeException('Format berkas tarif tindakan tidak sesuai.');
            }

            $now = now();
            $procedures = [];

            while (($row = fgetcsv($handle)) !== false) {
                if (count($row) !== 3 || ! ctype_digit($row[2]) || (int) $row[2] < 1) {
                    throw new RuntimeException('Data tarif tindakan tidak valid.');
                }

                $procedures[] = [
                    'category' => trim($row[0]),
                    'name' => trim($row[1]),
                    'price' => (int) $row[2],
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if ($procedures === []) {
                throw new RuntimeException('Berkas tarif tindakan tidak berisi data.');
            }

            DB::table('procedure_catalog')->upsert(
                $procedures,
                ['category', 'name'],
                ['price', 'is_active', 'updated_at'],
            );
        } finally {
            fclose($handle);
        }
    }
}
