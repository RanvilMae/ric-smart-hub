<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RegistrationStatusController extends Controller
{
    public function __invoke(Request $request): Response|RedirectResponse
    {
        $user = $request->user();

        if ($user->isApproved()) {
            return redirect('/');
        }

        $member = $user->member()->with('region:id,name,label')->first();

        return Inertia::render('auth/RegistrationStatus', [
            'memberCode' => $member?->member_code,
            'status' => $member?->status,
            'region' => $member?->region ? "{$member->region->name} – {$member->region->label}" : null,
            'submittedAt' => $member?->created_at?->toFormattedDateString(),
            'rejectionReason' => $member?->rejection_reason,
        ]);
    }
}