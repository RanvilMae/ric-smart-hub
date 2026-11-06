<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Seeds the complete Philippine geography:
 *   18 regions -> provinces -> cities/municipalities -> 42,010 barangays
 *
 * Source: PSA Philippine Standard Geographic Code (PSGC), October 12 2026
 * masterlist, via the MIT-licensed "barangay" dataset on GitHub. The file
 * is pinned to one exact version, checked with SHA-256, downloaded once
 * to storage/app/psgc/ and reused after that.
 *
 * To fit simple dropdowns (Region -> Province -> City -> Barangay):
 * - NCR has no provinces, so its cities and Pateros go under "Metro Manila".
 * - Highly urbanized / independent cities that PSGC places directly under
 *   a region (Cebu City, Davao City, Baguio...) become their own
 *   "province" entry, flagged is_independent_city.
 * - Manila's 14 districts are skipped; their barangays go under the City of Manila.
 *
 * Safe to run again: everything is matched by code, never duplicated.
 */
class PhilippineLocationSeeder extends Seeder
{
    private const SOURCE_URL = 'https://raw.githubusercontent.com/bendlikeabamboo/barangay/16ce5315dad13cf3df80d43edc15053646ae61a5/barangay/data/barangay_flat.json';

    private const SOURCE_SHA256 = '440755bcb8a119e1314104352c280b2096d58007baaeb487a589a1f54b445bad';

    /** code => [PSGC code, name (as used in the RIC Google Form), full name] */
    private const REGIONS = [
        'NCR' => ['1300000000', 'NCR', 'National Capital Region'],
        'CAR' => ['1400000000', 'CAR', 'Cordillera Administrative Region'],
        'I' => ['0100000000', 'Region I', 'Ilocos Region'],
        'II' => ['0200000000', 'Region II', 'Cagayan Valley'],
        'III' => ['0300000000', 'Region III', 'Central Luzon'],
        'IVA' => ['0400000000', 'Region IVA', 'CALABARZON'],
        'IVB' => ['1700000000', 'Region IVB', 'MIMAROPA'],
        'V' => ['0500000000', 'Region V', 'Bicol Region'],
        'VI' => ['0600000000', 'Region VI', 'Western Visayas'],
        'NIR' => ['1800000000', 'NIR', 'Negros Island Region'],
        'VII' => ['0700000000', 'Region VII', 'Central Visayas'],
        'VIII' => ['0800000000', 'Region VIII', 'Eastern Visayas'],
        'IX' => ['0900000000', 'Region IX', 'Zamboanga Peninsula'],
        'X' => ['1000000000', 'Region X', 'Northern Mindanao'],
        'XI' => ['1100000000', 'Region XI', 'Davao Region'],
        'XII' => ['1200000000', 'Region XII', 'SOCCSKSARGEN'],
        'XIII' => ['1600000000', 'Region XIII', 'Caraga'],
        'BARMM' => ['1900000000', 'BARMM', 'Bangsamoro Autonomous Region in Muslim Mindanao'],
    ];

    private const NCR_PSEUDO_PROVINCE = '1399999999';

    private const CITY_TYPES = [
        'municipality' => 'Municipality',
        'component_city' => 'City',
        'highly_urbanized_city' => 'Highly Urbanized City',
        'independent_component_city' => 'Independent Component City',
    ];

    private array $byCode = [];

    private array $regionByPrefix = [];

    public function run(): void
    {
        ini_set('memory_limit', '512M');

        // 1. Regions
        $now = now();
        $rows = [];
        $sort = 0;
        foreach (self::REGIONS as $code => [$psgc, $name, $label]) {
            $rows[] = [
                'code' => $code, 'psgc_code' => $psgc, 'name' => $name, 'label' => $label,
                'sort_order' => ++$sort, 'created_at' => $now, 'updated_at' => $now,
            ];
            $this->regionByPrefix[substr($psgc, 0, 2)] = $code;
        }
        DB::table('regions')->upsert($rows, ['code'], ['psgc_code', 'name', 'label', 'sort_order', 'updated_at']);
        $regionIds = DB::table('regions')->pluck('id', 'code');
        $this->command->info(str_pad('regions', 10).count($rows));

        // 2. Provinces, cities/municipalities, barangays
        $data = $this->transform($this->download());

        $this->save('provinces', $data['provinces'], fn ($p) => [
            'region_id' => $regionIds[$p['region_code']],
            'name' => $p['name'],
            'is_independent_city' => $p['is_independent_city'],
        ]);
        $provinceIds = DB::table('provinces')->pluck('id', 'psgc_code');

        $this->save('cities', $data['cities'], fn ($c) => [
            'region_id' => $regionIds[$c['region_code']],
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

    /** Download the PSGC list once and keep it in storage/app/psgc/. */
    private function download(): array
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

    /** Insert or update rows by PSGC code, 1,000 at a time. */
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

    /** Flat PSGC list => provinces, cities and barangays keyed by PSGC code. */
    private function transform(array $entries): array
    {
        foreach ($entries as $e) {
            $this->byCode[(string) $e['psgc_id']] = $e;
        }

        // Provinces (real ones + stand-ins for NCR and independent cities)
        $provinces = [];
        foreach ($this->byCode as $code => $e) {
            $code = (string) $code;

            if ($e['type'] === 'province') {
                $provinces[$code] = $this->province($code, $e['name'], false);
            } elseif (
                $this->typeOf($e['parent_psgc_id']) === 'region'
                && in_array($e['type'], ['highly_urbanized_city', 'independent_component_city', 'special_geographic_area'], true)
                && ! str_starts_with($code, '13')
            ) {
                $provinces[$code] = $this->province($code, str_replace(' (Not a Province)', '', $e['name']), true);
            }
        }
        $provinces[self::NCR_PSEUDO_PROVINCE] = ['region_code' => 'NCR', 'name' => 'Metro Manila', 'is_independent_city' => true];

        // Cities and municipalities
        $cities = [];
        foreach ($this->byCode as $code => $e) {
            $code = (string) $code;

            if (! isset(self::CITY_TYPES[$e['type']])) {
                continue;
            }

            $isOwnProvince = isset($provinces[$code]);

            // "City of Isabela (Not a Province)" is only a container; its real city is a child entry.
            if ($isOwnProvince && str_contains($e['name'], '(Not a Province)')) {
                continue;
            }

            $cities[$code] = [
                'region_code' => $this->regionCode($code),
                'province_code' => $isOwnProvince ? $code : $this->provinceOf($code, $provinces),
                'name' => $e['name'],
                'type' => self::CITY_TYPES[$e['type']],
            ];
        }

        // Barangays
        $barangays = [];
        foreach ($this->byCode as $code => $e) {
            if ($e['type'] !== 'barangay') {
                continue;
            }

            $parent = (string) $e['parent_psgc_id'];

            if ($this->typeOf($parent) === 'submunicipality') {
                $parent = (string) $this->byCode[$parent]['parent_psgc_id'];
            }

            if (! isset($cities[$parent])) {
                throw new RuntimeException("Barangay {$code} ({$e['name']}) has no city/municipality.");
            }

            $barangays[(string) $code] = ['city_code' => $parent, 'name' => $e['name']];
        }

        return compact('provinces', 'cities', 'barangays');
    }

    private function province(string $code, string $name, bool $independent): array
    {
        return ['region_code' => $this->regionCode($code), 'name' => $name, 'is_independent_city' => $independent];
    }

    private function regionCode(string $code): string
    {
        return $this->regionByPrefix[substr($code, 0, 2)]
            ?? throw new RuntimeException("Unknown region for PSGC code {$code}");
    }

    private function typeOf(string|int $code): ?string
    {
        return $this->byCode[(string) $code]['type'] ?? null;
    }

    /** Walk up the PSGC tree until a (stand-in) province is found. */
    private function provinceOf(string $code, array $provinces): string
    {
        if (str_starts_with($code, '13')) {
            return self::NCR_PSEUDO_PROVINCE;
        }

        $current = $code;
        while (isset($this->byCode[$current])) {
            if (isset($provinces[$current])) {
                return $current;
            }
            $current = (string) $this->byCode[$current]['parent_psgc_id'];
        }

        throw new RuntimeException("No province found for PSGC code {$code}");
    }
}