<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * JSON list of RIC organizations for the registration form:
 * the organizations of the chosen region, or of the chosen city / municipality.
 */
class OrganizationLookupController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'region' => ['required', 'integer'],
            'city' => ['nullable', 'integer'],
        ]);

        $organizations = Organization::query()
            ->where('region_id', $filters['region'])
            ->when($filters['city'] ?? null, fn ($q, $city) => $q->where('city_id', $city))
            ->with(['province:id,name', 'city:id,name', 'barangay:id,name'])
            ->orderBy('name')
            ->get()
            ->map(fn (Organization $o) => [
                'id' => $o->id,
                'name' => $o->name,
                'address' => $o->fullAddress(),
            ]);

        return response()->json($organizations);
    }
}