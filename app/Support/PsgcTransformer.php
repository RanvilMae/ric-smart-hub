<?php

namespace App\Support;

use RuntimeException;

/**
 * Turns the flat PSGC list into: Region -> Province -> City/Municipality -> Barangay
 *
 * - NCR has no provinces: its cities and Pateros go under "Metro Manila".
 * - Highly urbanized / independent cities placed directly under a region
 *   (Cebu City, Davao City, Baguio...) become their own "province" entry.
 * - Manila's 14 districts are skipped; their barangays go under the City of Manila.
 */
class PsgcTransformer
{
    public const NCR_PSEUDO_PROVINCE = '1399999999';

    /** First two PSGC digits => our region code (see RegionSeeder). */
    public const REGION_CODES = [
        '01' => 'I', '02' => 'II', '03' => 'III', '04' => 'IVA', '05' => 'V',
        '06' => 'VI', '07' => 'VII', '08' => 'VIII', '09' => 'IX', '10' => 'X',
        '11' => 'XI', '12' => 'XII', '13' => 'NCR', '14' => 'CAR', '16' => 'XIII',
        '17' => 'IVB', '18' => 'NIR', '19' => 'BARMM',
    ];

    private const CITY_TYPES = [
        'municipality' => 'Municipality',
        'component_city' => 'City',
        'highly_urbanized_city' => 'Highly Urbanized City',
        'independent_component_city' => 'Independent Component City',
    ];

    private array $byCode = [];

    public function transform(array $entries): array
    {
        foreach ($entries as $e) {
            $this->byCode[(string) $e['psgc_id']] = $e;
        }

        // 1. Provinces (real ones + stand-ins for NCR and independent cities)
        $provinces = [];
        foreach ($this->byCode as $code => $e) {
            $code = (string) $code;
            $parentType = $this->typeOf($e['parent_psgc_id']);

            if ($e['type'] === 'province') {
                $provinces[$code] = $this->province($code, $e['name'], false);
            } elseif (
                $parentType === 'region'
                && in_array($e['type'], ['highly_urbanized_city', 'independent_component_city', 'special_geographic_area'], true)
                && ! str_starts_with($code, '13')
            ) {
                $provinces[$code] = $this->province($code, str_replace(' (Not a Province)', '', $e['name']), true);
            }
        }
        $provinces[self::NCR_PSEUDO_PROVINCE] = ['region_code' => 'NCR', 'name' => 'Metro Manila', 'is_independent_city' => true];

        // 2. Cities and municipalities
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

        // 3. Barangays
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
        return self::REGION_CODES[substr($code, 0, 2)]
            ?? throw new RuntimeException("Unknown region for PSGC code {$code}");
    }

    private function typeOf(string|int $code): ?string
    {
        return $this->byCode[(string) $code]['type'] ?? null;
    }

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