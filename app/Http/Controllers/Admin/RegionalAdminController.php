<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Member;
use App\Models\Region;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Super admin: choose regional admins from APPROVED MEMBERS.
 * A regional admin stays a member (keeps member ID and digital ID)
 * and manages the region of their own membership.
 */
class RegionalAdminController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = $request->validate([
            'region' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $admins = User::query()
            ->where('role', Role::RegionalAdmin)
            ->with(['region:id,name,label', 'member:id,user_id,member_code,ric_organization'])
            ->orderBy('region_id')
            ->orderBy('name')
            ->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'member_code' => $u->member?->member_code,
                'ric_organization' => $u->member?->ric_organization,
                'region' => $u->region ? "{$u->region->name} – {$u->region->label}" : null,
                'is_active' => $u->is_active,
            ]);

        // Approved members with a login who are not admins yet
        $candidates = Member::query()
            ->where('status', 'approved')
            ->whereHas('user', fn ($q) => $q->where('role', Role::Member))
            ->with(['region:id,name', 'city:id,name', 'user:id,email'])
            ->when($filters['region'] ?? null, fn ($q, $region) => $q->where('region_id', $region))
            ->when($filters['search'] ?? null, function ($q, string $search) {
                $like = "%{$search}%";
                $q->where(fn ($q) => $q
                    ->where('last_name', 'like', $like)
                    ->orWhere('first_name', 'like', $like)
                    ->orWhere('member_code', 'like', $like)
                    ->orWhere('ric_organization', 'like', $like));
            })
            ->orderBy('last_name')
            ->limit(15)
            ->get()
            ->map(fn (Member $m) => [
                'id' => $m->id,
                'full_name' => $m->full_name,
                'member_code' => $m->member_code,
                'email' => $m->user?->email,
                'region' => $m->region?->name,
                'city' => $m->city?->name,
                'ric_organization' => $m->ric_organization,
            ]);

        return Inertia::render('admin/RegionalAdmins', [
            'admins' => $admins,
            'candidates' => $candidates,
            'filters' => ['region' => $filters['region'] ?? null, 'search' => $filters['search'] ?? ''],
            'regions' => Region::orderBy('sort_order')->get(['id', 'name', 'label']),
        ]);
    }

    /** Make an approved member the regional admin of their own region. */
    public function promote(Member $member): RedirectResponse
    {
        $user = $member->user;

        abort_unless($member->status === 'approved' && $user, 422, 'Only approved members with an account can become regional admins.');
        abort_unless($user->role === Role::Member, 422, 'This member is already an admin.');

        $user->forceFill([
            'role' => Role::RegionalAdmin,
            'region_id' => $member->region_id,
            'is_active' => true,
            'email_verified_at' => $user->email_verified_at ?? now(),
        ])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$member->full_name} is now a regional admin."]);

        return back();
    }

    /** Activate / deactivate an admin's access. */
    public function update(Request $request, User $admin): RedirectResponse
    {
        abort_unless($admin->role === Role::RegionalAdmin, 404);

        $data = $request->validate(['is_active' => ['required', 'boolean']]);

        $admin->forceFill($data)->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$admin->name} updated."]);

        return back();
    }

    /** Remove the admin role; the person stays an approved member. */
    public function demote(User $admin): RedirectResponse
    {
        abort_unless($admin->role === Role::RegionalAdmin, 404);

        $admin->forceFill([
            'role' => Role::Member,
            'region_id' => null,
            'is_active' => true,
        ])->save();

        Inertia::flash('toast', ['type' => 'info', 'message' => "{$admin->name} is no longer a regional admin."]);

        return back();
    }
}
