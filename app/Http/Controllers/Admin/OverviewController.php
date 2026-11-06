<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\Region;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Super admin dashboard: national summary of registered members.
 * "Registered" = approved + pending (rejected registrations are left out).
 */
class OverviewController extends Controller
{
    public function __invoke(): Response
    {
        $registered = Member::query()->whereIn('status', ['approved', 'pending']);

        // Totals per region in one query
        $perRegion = (clone $registered)
            ->selectRaw("region_id,
                count(*) as total,
                sum(case when status = 'approved' then 1 else 0 end) as approved,
                sum(case when status = 'pending' then 1 else 0 end) as pending,
                count(distinct ric_organization) as organizations")
            ->groupBy('region_id')
            ->get()
            ->keyBy('region_id');

        $regions = Region::query()
            ->orderBy('sort_order')
            ->withCount(['admins as admins_count' => fn ($q) => $q->where('is_active', true)])
            ->get(['id', 'code', 'name', 'label'])
            ->map(fn (Region $r) => [
                'id' => $r->id,
                'name' => $r->name,
                'label' => $r->label,
                'total' => (int) ($perRegion[$r->id]->total ?? 0),
                'approved' => (int) ($perRegion[$r->id]->approved ?? 0),
                'pending' => (int) ($perRegion[$r->id]->pending ?? 0),
                'organizations' => (int) ($perRegion[$r->id]->organizations ?? 0),
                'admins' => (int) $r->admins_count,
            ]);

        // Region with the most RIC organizations (ties: more members wins)
        $mostActive = $regions
            ->filter(fn ($r) => $r['organizations'] > 0)
            ->sortBy([['organizations', 'desc'], ['total', 'desc']])
            ->first();

        // Top 10 RIC organizations by number of registered members
        $topOrganizations = (clone $registered)
            ->whereNotNull('ric_organization')
            ->where('ric_organization', '!=', '')
            ->selectRaw('region_id, ric_organization, count(*) as members')
            ->groupBy('region_id', 'ric_organization')
            ->orderByDesc('members')
            ->limit(10)
            ->with('region:id,name')
            ->get()
            ->map(fn (Member $m) => [
                'name' => $m->ric_organization,
                'region' => $m->region?->name,
                'members' => (int) $m->members,
            ]);

        return Inertia::render('admin/Overview', [
            'stats' => [
                'registered' => (int) $regions->sum('total'),
                'approved' => (int) $regions->sum('approved'),
                'pending' => (int) $regions->sum('pending'),
                'organizations' => (int) $regions->sum('organizations'),
                'regionalAdmins' => User::where('role', Role::RegionalAdmin)->where('is_active', true)->count(),
                'regionsWithoutAdmin' => $regions->where('admins', 0)->count(),
            ],
            'regions' => $regions->values(),
            'mostActive' => $mostActive,
            'topOrganizations' => $topOrganizations,
        ]);
    }
}
