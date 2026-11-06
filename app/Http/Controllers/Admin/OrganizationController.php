<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Regional admin: RIC organizations / clubs of their own region.
 */
class OrganizationController extends Controller
{
    public function index(Request $request): Response
    {
        $region = $request->user()->region;
        abort_unless($region, 403, 'Your account is not assigned to a region.');

        $search = trim((string) $request->query('search', ''));

        // Registered members per organization name in this region
        $memberCounts = Member::query()
            ->where('region_id', $region->id)
            ->whereIn('status', ['approved', 'pending'])
            ->whereNotNull('ric_organization')
            ->selectRaw('lower(trim(ric_organization)) as org, count(*) as total')
            ->groupBy('org')
            ->pluck('total', 'org');

        $organizations = Organization::query()
            ->where('region_id', $region->id)
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('address', 'like', "%{$search}%")
                ->orWhereHas('city', fn ($c) => $c->where('name', 'like', "%{$search}%"))
                ->orWhereHas('barangay', fn ($b) => $b->where('name', 'like', "%{$search}%"))
                ->orWhere('specializations', 'like', "%{$search}%")))
            ->with(['updater:id,name', 'province:id,name', 'city:id,name', 'barangay:id,name'])
            ->orderBy('name')
            ->get()
            ->map(fn (Organization $o) => [
                'id' => $o->id,
                'name' => $o->name,
                'address' => $o->address,
                'full_address' => $o->fullAddress(),
                'province_id' => $o->province_id,
                'city_id' => $o->city_id,
                'barangay_id' => $o->barangay_id,
                'description' => $o->description,
                'specializations' => $o->specializations ?? [],
                'members' => (int) ($memberCounts[mb_strtolower(trim($o->name))] ?? 0),
                'updated' => $o->updated_at?->format('M j, Y'),
                'updated_by' => $o->updater?->name,
            ]);

        return Inertia::render('admin/organizations/Index', [
            'region' => ['id' => $region->id, 'name' => $region->name, 'label' => $region->label],
            'organizations' => $organizations,
            'total' => Organization::where('region_id', $region->id)->count(),
            'search' => $search,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $regionId = $request->user()->region_id;
        abort_unless($regionId, 403);

        $data = $this->validated($request, $regionId);

        Organization::create($data + [
            'region_id' => $regionId,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => "\"{$data['name']}\" was added."]);

        return back();
    }

    public function update(Request $request, Organization $organization): RedirectResponse
    {
        $this->authorizeRegion($request, $organization);

        $data = $this->validated($request, $organization->region_id, $organization);

        DB::transaction(function () use ($organization, $data, $request) {
            $oldName = $organization->name;
            $organization->update($data + ['updated_by' => $request->user()->id]);

            // Renamed: keep the members who wrote the old name linked to it
            if ($organization->wasChanged('name')) {
                Member::query()
                    ->where('region_id', $organization->region_id)
                    ->where('ric_organization', $oldName)
                    ->update(['ric_organization' => $organization->name]);
            }
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => "\"{$organization->name}\" was updated."]);

        return back();
    }

    public function destroy(Request $request, Organization $organization): RedirectResponse
    {
        $this->authorizeRegion($request, $organization);

        $organization->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => "\"{$organization->name}\" was deleted."]);

        return back();
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, int $regionId, ?Organization $organization = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('organizations', 'name')->where('region_id', $regionId)->ignore($organization)],
            'province_id' => ['required', 'integer', Rule::exists('provinces', 'id')->where('region_id', $regionId)],
            'city_id' => ['required', 'integer', Rule::exists('cities', 'id')->where('province_id', $request->input('province_id', 0))],
            'barangay_id' => ['required', 'integer', Rule::exists('barangays', 'id')->where('city_id', $request->input('city_id', 0))],
            'address' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'specializations' => ['nullable', 'array', 'max:30'],
            'specializations.*' => ['nullable', 'string', 'max:100'],
        ], [
            'name.unique' => 'This organization is already listed in your region.',
        ], [
            'name' => 'organization name',
            'province_id' => 'province',
            'city_id' => 'city / municipality',
            'barangay_id' => 'barangay',
            'address' => 'street / sitio / purok',
            'specializations.*' => 'field of specialization',
        ]);

        // Clean the list: trim, drop blanks and repeats
        $data['specializations'] = collect($data['specializations'] ?? [])
            ->map(fn ($s) => trim((string) $s))
            ->filter()
            ->unique(fn ($s) => mb_strtolower($s))
            ->values()
            ->all();

        $data['name'] = trim($data['name']);

        return $data;
    }

    private function authorizeRegion(Request $request, Organization $organization): void
    {
        abort_unless((int) $organization->region_id === (int) $request->user()->region_id, 403);
    }
}