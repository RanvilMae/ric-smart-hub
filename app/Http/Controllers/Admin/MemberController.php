<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\Region;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Member list and approval.
 * - Regional admin: sees and approves/disapproves ONLY members of their own region.
 * - Super admin: sees all regions (approves only the memberships of admins).
 */
class MemberController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['all', 'pending', 'approved', 'rejected'])],
            'region' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $status = $filters['status'] ?? 'all';

        $base = Member::query()
            ->when($user->isRegionalAdmin(), fn ($q) => $q->where('region_id', $user->region_id))
            ->when($user->isSuperAdmin() && ! empty($filters['region']), fn ($q) => $q->where('region_id', $filters['region']));

        // Numbers for the filter tabs
        $counts = (clone $base)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $members = (clone $base)
            ->with(['region:id,name', 'province:id,name', 'city:id,name', 'user:id,role'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($filters['search'] ?? null, function ($q, string $search) {
                $like = "%{$search}%";
                $q->where(fn ($q) => $q
                    ->where('last_name', 'like', $like)
                    ->orWhere('first_name', 'like', $like)
                    ->orWhere('member_code', 'like', $like)
                    ->orWhere('ric_organization', 'like', $like));
            })
            // Waiting for approval first, then newest
            ->orderByRaw("case when status = 'pending' then 0 else 1 end")
            ->latest()
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Member $m) => [
                'id' => $m->id,
                'member_code' => $m->member_code,
                'full_name' => $m->full_name,
                'status' => $m->status,
                'region' => $m->region?->name,
                'location' => collect([$m->city?->name, $m->province?->name])->filter()->implode(', '),
                'ric_organization' => $m->ric_organization,
                'member_type' => $m->member_type,
                'submitted' => $m->created_at?->toFormattedDateString(),
                'can_review' => $this->canReview($user, $m),
                'is_admin' => (bool) $m->user?->isRegionalAdmin(),
                // Super admin can make any approved member (with a login) a regional admin
                'can_promote' => $user->isSuperAdmin() && $m->status === 'approved' && $m->user && ! $m->user->isRegionalAdmin() && ! $m->user->isSuperAdmin(),
            ]);

        return Inertia::render('admin/members/Index', [
            'members' => $members,
            'counts' => [
                'all' => (int) $counts->sum(),
                'pending' => (int) ($counts['pending'] ?? 0),
                'approved' => (int) ($counts['approved'] ?? 0),
                'rejected' => (int) ($counts['rejected'] ?? 0),
            ],
            'filters' => [
                'status' => $status,
                'region' => $filters['region'] ?? null,
                'search' => $filters['search'] ?? '',
            ],
            'regions' => $user->isSuperAdmin() ? Region::orderBy('sort_order')->get(['id', 'name']) : null,
            'scope' => $user->isSuperAdmin() ? 'All regions' : $user->region?->name,
        ]);
    }

    public function show(Request $request, Member $member): Response
    {
        $this->authorizeView($request->user(), $member);

        $member->load(['region', 'province', 'city', 'barangay', 'user:id,email,role', 'reviewer:id,name']);

        return Inertia::render('admin/members/Show', [
            'member' => [
                ...$member->only([
                    'id', 'member_code', 'status', 'last_name', 'first_name', 'middle_name', 'suffix', 'sex',
                    'civil_status', 'education_attainment', 'degree', 'district', 'municipality_class',
                    'zip_code', 'member_type', 'occupation', 'contact_number', 'alternate_email',
                    'organization_address', 'ric_organization', 'specialization', 'hvcdp_commodity',
                    'ati_services', 'lsa_level', 'lsa_type', 'ati_scholarship', 'ati_trainings',
                    'rejection_reason',
                ]),
                'full_name' => $member->full_name,
                'email' => $member->user?->email,
                'birth_date' => $member->birth_date?->format('F j, Y'),
                'region' => $member->region ? "{$member->region->name} – {$member->region->label}" : null,
                'province' => $member->province?->name,
                'city' => $member->city?->name,
                'barangay' => $member->barangay?->name,
                'submitted' => $member->created_at?->toDayDateTimeString(),
                'reviewer' => $member->reviewer?->name,
                'reviewed_at' => $member->reviewed_at?->toDayDateTimeString(),
            ],
            'canReview' => $this->canReview($request->user(), $member),
        ]);
    }

    public function approve(Request $request, Member $member): RedirectResponse
    {
        abort_unless($this->canReview($request->user(), $member), 403, 'Only the regional admin of this region can approve members.');

        $member->forceFill([
            'status' => 'approved',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'rejection_reason' => null,
        ])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$member->full_name} approved."]);

        return back();
    }

    public function reject(Request $request, Member $member): RedirectResponse
    {
        abort_unless($this->canReview($request->user(), $member), 403, 'Only the regional admin of this region can disapprove members.');

        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);

        $member->forceFill([
            'status' => 'rejected',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'rejection_reason' => $data['reason'],
        ])->save();

        Inertia::flash('toast', ['type' => 'info', 'message' => "{$member->full_name} disapproved."]);

        return back();
    }

    /** Viewing: super admin sees everyone, a regional admin only their own region. */
    private function authorizeView(User $user, Member $member): void
    {
        abort_unless($user->isSuperAdmin() || $this->isOwnRegion($user, $member), 403);
    }

    /** Approving / disapproving. */
    private function canReview(User $user, Member $member): bool
    {
        // Memberships of admins are handled by the super admin (no one approves themselves)
        $memberIsAdmin = $member->user && ($member->user->isRegionalAdmin() || $member->user->isSuperAdmin());

        if ($memberIsAdmin) {
            return $user->isSuperAdmin();
        }

        return $this->isOwnRegion($user, $member);
    }

    private function isOwnRegion(User $user, Member $member): bool
    {
        return $user->isRegionalAdmin()
            && $user->is_active
            && (int) $member->region_id === (int) $user->region_id;
    }
}
