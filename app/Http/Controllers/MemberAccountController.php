<?php

namespace App\Http\Controllers;

use App\Concerns\MemberProfileRules;
use App\Models\Member;
use App\Models\Region;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "My Account" for approved members (and regional admins, who are members too):
 * details, photo, digital ID. Admins without a member registration can register here.
 */
class MemberAccountController extends Controller
{
    use MemberProfileRules;

    public function show(Request $request): Response
    {
        $user = $request->user();
        $member = $user->member()->with(['region', 'province', 'city', 'barangay'])->first();

        // No registration yet, or registration still waiting / rejected
        if (! $member || $member->status !== 'approved') {
            return Inertia::render('account/NoMembership', [
                'status' => $member?->status,
                'memberCode' => $member?->member_code,
                'rejectionReason' => $member?->rejection_reason,
            ]);
        }

        return Inertia::render('account/Show', [
            'member' => [
                ...$member->only([
                    'member_code', 'status', 'last_name', 'first_name', 'middle_name', 'suffix', 'sex',
                    'civil_status', 'education_attainment', 'degree', 'district', 'municipality_class',
                    'zip_code', 'member_type', 'occupation', 'contact_number', 'alternate_email',
                    'organization_address', 'ric_organization', 'specialization', 'hvcdp_commodity',
                    'ati_services', 'lsa_level', 'lsa_type', 'ati_scholarship', 'ati_trainings',
                ]),
                'full_name' => $member->full_name,
                'email' => $user->email,
                'birth_date' => $member->birth_date?->format('F j, Y'),
                'region' => $member->region ? "{$member->region->name} – {$member->region->label}" : null,
                'region_name' => $member->region?->name,
                'province' => $member->province?->name,
                'city' => $member->city?->name,
                'barangay' => $member->barangay?->name,
                'photo_url' => $member->photo_path ? Storage::disk('public')->url($member->photo_path) : null,
                'member_since' => ($member->reviewed_at ?? $member->created_at)?->format('F Y'),
                'issued_on' => now()->format('M j, Y'),
            ],
            'payment' => \App\Models\MemberPayment::latestFor($member)?->toSummary(),
            'paymentConfirmed' => \App\Models\MemberPayment::hasConfirmed($member),
            'paymentMethods' => \App\Models\MemberPayment::METHODS,
        ]);
    }

    /** Registration form for a logged-in account that has no member registration yet. */
    public function createRegistration(Request $request): Response|RedirectResponse
    {
        if ($request->user()->member()->exists()) {
            return redirect('/my-account');
        }

        return Inertia::render('auth/Register', [
            'regions' => Region::orderBy('sort_order')->get(['id', 'name', 'label']),
            'options' => config('ric'),
            'existingAccount' => true,
            // Regional admins register in the region they manage (cannot be changed)
            'lockedRegionId' => $request->user()->isRegionalAdmin() ? $request->user()->region_id : null,
            // Shown read-only in the "Login account" section
            'account' => [
                'name' => $request->user()->name,
                'email' => $request->user()->email,
                'role' => $request->user()->role->label(),
            ],
        ]);
    }

    public function storeRegistration(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->member()->exists()) {
            return redirect('/my-account');
        }

        $validated = $request->validate(
            [
                ...$this->memberProfileRules($request->all()),
                // Optional: set a new password for this account
                'password' => ['nullable', 'string', Password::default(), 'confirmed'],
            ],
            $this->memberProfileMessages(),
            $this->memberProfileAttributes(),
        );

        $newPassword = $validated['password'] ?? null;
        unset($validated['password']);

        // A regional admin registers as a member of the region they manage
        if ($user->isRegionalAdmin() && (int) $validated['region_id'] !== (int) $user->region_id) {
            throw ValidationException::withMessages([
                'region_id' => 'As a regional admin, please register in the region you manage.',
            ]);
        }

        $member = new Member($this->cleanMemberProfile($validated));
        $member->user_id = $user->id;
        // Admins are already trusted by the super admin: their membership is approved automatically
        $autoApproved = $user->isRegionalAdmin() || $user->isSuperAdmin();
        $member->status = $autoApproved ? 'approved' : 'pending';
        if ($autoApproved) {
            $member->reviewed_at = now();
        }
        $member->save();

        if ($newPassword) {
            $user->forceFill(['password' => $newPassword])->save();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => $autoApproved
            ? "Membership approved. Your member ID is {$member->member_code}."
            : "Registration submitted. Your member ID is {$member->member_code}."]);

        return redirect('/my-account');
    }

    public function updatePhoto(Request $request): RedirectResponse
    {
        $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ], [
            'photo.max' => 'The photo must not be larger than 2 MB.',
        ]);

        $member = $request->user()->member()->firstOrFail();

        $old = $member->photo_path;
        $member->photo_path = $request->file('photo')->store('member-photos', 'public');
        $member->save();

        if ($old) {
            Storage::disk('public')->delete($old);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Photo updated.']);

        return back();
    }
}
