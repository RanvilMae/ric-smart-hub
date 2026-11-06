<?php

namespace App\Http\Controllers;

use App\Models\City;
use App\Models\Province;
use App\Models\Region;
use Illuminate\Http\JsonResponse;

/**
 * JSON lists for the cascading address dropdowns:
 * Region -> Province -> City/Municipality -> Barangay
 */
class LocationController extends Controller
{
    public function provinces(Region $region): JsonResponse
    {
        return response()->json(
            $region->provinces()
                ->orderBy('is_independent_city')   // real provinces first, then independent cities
                ->orderBy('name')
                ->get(['id', 'name']),
        );
    }

    public function cities(Province $province): JsonResponse
    {
        return response()->json(
            $province->cities()->orderBy('name')->get(['id', 'name', 'type']),
        );
    }

    public function barangays(City $city): JsonResponse
    {
        return response()->json(
            $city->barangays()->orderBy('name')->get(['id', 'name']),
        );
    }
}