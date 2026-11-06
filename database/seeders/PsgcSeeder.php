<?php

namespace Database\Seeders;

use App\Models\Region;
use App\Support\PsgcTransformer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Loads provinces, cities/municipalities and barangays from the official
 * PSGC masterlist (PSA, October 12 2026 release), via the MIT-licensed
 * "barangay" dataset on GitHub, pinned to one version and hash-checked.
 * Downloaded once to storage/app/psgc/. Safe to run again.
 */
class PsgcSeeder extends Seeder
{
    private const SOURCE_URL = 'https://raw.githubusercontent.com/bendlikeabamboo/barangay/16ce5315dad13cf3df80d43edc15053646ae61a5/barangay/data/barangay_flat.json';

    private const SOURCE_SHA256 = '440755bcb8a119e1314104352c280b2096d58007baaeb487a589a1f54b445bad';

    private const REGION_PSGC = [
        'I' => '0100000000', 'II' => '0200000000', 'III' => '0300000000',
        'IVA' => '0400000000', 'V' => '0500000000', 'VI' => '0600000000',
        'VII' => '0700000000', 'VIII' => '0800000000', 'IX' => '0900000000',
        'X' => '1000000000', 'XI' => '1100000000', 'XII' => '1200000000',
        'NCR' => '1300000000', 'CAR' => '1400000000', 'XIII' => '1600000000',
        'IVB' => '1700000000', 'NIR' => '1800000000', 'BARMM' => '1900000000',
    ];

    public function run(): void
    {
        ini_set('memory_limit', '512M');

        $regions = Region::pluck('id', 'code');
        if ($regions->count() < 18) {
            throw new RuntimeException('Run RegionSeeder first (php artisan db:seed --class=RegionSeeder).');
        }

        foreach (self::REGION_PSGC as $code => $psgc) {
            Region::where('code', $code)->update(['psgc_code' => $psgc]);
        }

        $data = (new PsgcTransformer)->transform($this->sourceData());

        $this->save('provinces', $data['provinces'], fn ($p) => [
            'region_id' => $regions[$p['region_code']],
            'name' => $p['name'],
            'is_independent_city' => $p['is_independent_city'],
        ]);
        $provinceIds = DB::table('provinces')->pluck('id', 'psgc_code');

        $this->save('cities', $data['cities'], fn ($c) => [
            'region_id' => $regions[$c['region_code']],
            'province_id' => $provinceIds[$c['province_code']],
            'name' => $c['name'],
            'type' => $c['type'],
        ]);
        $cityIds = DB::table('cities')->pluck('id', 'psgc_code');

        $this->save('barangays', $data['barangays'], fn ($b) => [
            'city_id' => $cityIds[$b['city_code']],
            'name' => $b['name'],
        ]);
    }

    private function sourceData(): array
    {
        $path = storage_path('app/psgc/barangay_flat.json');

        if (! File::exists($path) || hash_file('sha256', $path) !== self::SOURCE_SHA256) {
            $this->command->info('Downloading PSGC data (about 7 MB)...');

            $body = Http::timeout(180)->retry(3, 2000)->get(self::SOURCE_URL)->throw()->body();

            if (hash('sha256', $body) !== self::SOURCE_SHA256) {
                throw new RuntimeException('Downloaded PSGC file does not match the expected version.');
            }

            File::ensureDirectoryExists(dirname($path));
            File::put($path, $body);
        }

        return json_decode(File::get($path), true, flags: JSON_THROW_ON_ERROR);
    }

    private function save(string $table, array $rows, callable $map): void
    {
        $now = now();

        DB::transaction(function () use ($table, $rows, $map, $now) {
            foreach (array_chunk($rows, 1000, true) as $chunk) {
                $batch = [];
                foreach ($chunk as $code => $row) {
                    $batch[] = ['psgc_code' => (string) $code, ...$map($row), 'created_at' => $now, 'updated_at' => $now];
                }
                DB::table($table)->upsert($batch, ['psgc_code']);
            }
        });

        $this->command->info(str_pad($table, 10).number_format(count($rows)));
    }
}