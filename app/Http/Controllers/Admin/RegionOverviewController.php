<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\City;
use App\Models\Member;
use App\Models\Province;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Regional admin dashboard: same summary as the national overview,
 * but only for the admin's own region (broken down per province).
 * "Registered" = approved + pending (rejected registrations are left out).
 */
class RegionOverviewController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $region = $request->user()->region;
        abort_unless($region, 403, 'Your account is not assigned to a region.');

        $registered = Member::query()
            ->where('region_id', $region->id)
            ->whereIn('status', ['approved', 'pending']);

        $countColumns = "count(*) as total,
            sum(case when status = 'approved' then 1 else 0 end) as approved,
            sum(case when status = 'pending' then 1 else 0 end) as pending,
            count(distinct nullif(ric_organization, '')) as organizations";

        // Totals per province in one query
        $perProvince = (clone $registered)
            ->selectRaw("province_id, $countColumns")
            ->groupBy('province_id')
            ->get()
            ->keyBy('province_id');

        $provinces = Province::query()
            ->where('region_id', $region->id)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Province $p) => [
                'id' => $p->id,
                'name' => $p->name,
                'total' => (int) ($perProvince[$p->id]->total ?? 0),
                'approved' => (int) ($perProvince[$p->id]->approved ?? 0),
                'pending' => (int) ($perProvince[$p->id]->pending ?? 0),
                'organizations' => (int) ($perProvince[$p->id]->organizations ?? 0),
            ]);

        // Members saved without a province (e.g. incomplete imported rows)
        if (($none = $perProvince[''] ?? null) && $none->total > 0) {
            $provinces->push([
                'id' => 0,
                'name' => 'No province given',
                'total' => (int) $none->total,
                'approved' => (int) $none->approved,
                'pending' => (int) $none->pending,
                'organizations' => (int) $none->organizations,
            ]);
        }

        // Overall totals for the region
        $totals = (clone $registered)->selectRaw($countColumns)->first();

        // Province with the most RIC organizations (ties: more members wins)
        $mostActive = $provinces
            ->filter(fn ($p) => $p['id'] && $p['organizations'] > 0)
            ->sortBy([['organizations', 'desc'], ['total', 'desc']])
            ->first();

        // Top 10 RIC organizations in the region
        $topOrganizations = (clone $registered)
            ->whereNotNull('ric_organization')
            ->where('ric_organization', '!=', '')
            ->selectRaw('ric_organization, min(city_id) as city_id, count(*) as members')
            ->groupBy('ric_organization')
            ->orderByDesc('members')
            ->limit(10)
            ->get();

        $cityNames = City::whereIn('id', $topOrganizations->pluck('city_id')->filter())->pluck('name', 'id');

        // Top 10 cities / municipalities in the region
        $topCities = (clone $registered)
            ->whereNotNull('city_id')
            ->selectRaw('city_id, count(*) as members, count(distinct nullif(ric_organization, \'\')) as organizations')
            ->groupBy('city_id')
            ->orderByDesc('members')
            ->limit(10)
            ->get();

        $cities = City::whereIn('id', $topCities->pluck('city_id'))->get(['id', 'name', 'province_id'])->keyBy('id');
        $provinceNames = $provinces->pluck('name', 'id');

        // Latest registrations waiting for this admin's approval
        $latestPending = Member::query()
            ->where('region_id', $region->id)
            ->where('status', 'pending')
            ->with(['city:id,name', 'province:id,name'])
            ->latest()
            ->limit(6)
            ->get()
            ->map(fn (Member $m) => [
                'id' => $m->id,
                'full_name' => $m->full_name,
                'location' => collect([$m->city?->name, $m->province?->name])->filter()->implode(', '),
                'ric_organization' => $m->ric_organization,
                'submitted' => $m->created_at?->diffForHumans(),
            ]);

        return Inertia::render('admin/RegionOverview', [
            'region' => [
                'name' => $region->name,
                'label' => $region->label,
            ],
            'stats' => [
                'registered' => (int) ($totals->total ?? 0),
                'approved' => (int) ($totals->approved ?? 0),
                'pending' => (int) ($totals->pending ?? 0),
                'organizations' => (int) ($totals->organizations ?? 0),
                'thisYear' => (clone $registered)->whereYear('created_at', now()->year)->count(),
            ],
            'provinces' => $provinces->values(),
            'mostActive' => $mostActive,
            'topOrganizations' => $topOrganizations->map(fn ($o) => [
                'name' => $o->ric_organization,
                'place' => $cityNames[$o->city_id] ?? null,
                'members' => (int) $o->members,
            ]),
            'topCities' => $topCities->map(fn ($c) => [
                'name' => $cities[$c->city_id]->name ?? '—',
                'province' => $provinceNames[$cities[$c->city_id]->province_id ?? 0] ?? null,
                'members' => (int) $c->members,
                'organizations' => (int) $c->organizations,
            ]),
            'latestPending' => $latestPending,
        ]);
    }
}
