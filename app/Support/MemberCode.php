<?php

namespace App\Support;

use App\Models\Region;
use Illuminate\Support\Facades\DB;

/**
 * Generates RIC member IDs: RIC-{region code}-{year}-{000-000}
 * e.g. RIC-II-2026-000-001. The counter is separate per region and year.
 */
class MemberCode
{
    public static function next(int $regionId, int $year): string
    {
        return DB::transaction(function () use ($regionId, $year) {
            // Make sure a counter row exists, then lock it so no one else gets the same number
            DB::table('member_code_sequences')->insertOrIgnore([
                'region_id' => $regionId,
                'year' => $year,
                'last_number' => 0,
            ]);

            $row = DB::table('member_code_sequences')
                ->where('region_id', $regionId)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            $number = $row->last_number + 1;

            DB::table('member_code_sequences')->where('id', $row->id)->update(['last_number' => $number]);

            return self::format(Region::whereKey($regionId)->value('code'), $year, $number);
        });
    }

    /** RIC-II-2026-000-001 */
    public static function format(string $regionCode, int $year, int $number): string
    {
        $digits = str_pad((string) $number, 6, '0', STR_PAD_LEFT);

        return sprintf('RIC-%s-%d-%s-%s', $regionCode, $year, substr($digits, 0, 3), substr($digits, 3, 3));
    }
}
