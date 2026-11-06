<?php

namespace App\Http\Controllers;

use App\Models\MemberPayment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Members upload proof of their membership payment (My account → Payment).
 */
class MemberPaymentController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $member = $request->user()->member;
        abort_unless($member && $member->status === 'approved', 403);

        if (MemberPayment::hasConfirmed($member)) {
            Inertia::flash('toast', ['type' => 'info', 'message' => 'Your payment is already confirmed.']);

            return back();
        }

        $data = $request->validate([
            'method' => ['required', Rule::in(MemberPayment::METHODS)],
            'reference_no' => [Rule::requiredIf(! str_starts_with((string) $request->input('method'), 'Cash')), 'nullable', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'min:1', 'max:1000000'],
            'paid_on' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:255'],
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ], [
            'proof.mimes' => 'Upload a photo (JPG, PNG, WEBP) or a PDF of your receipt.',
            'proof.max' => 'The file must not be larger than 5 MB.',
            'reference_no.required' => 'Enter the reference or transaction number from your receipt.',
        ], [
            'method' => 'mode of payment',
            'paid_on' => 'date of payment',
            'proof' => 'proof of payment',
        ]);

        $file = $request->file('proof');
        $data['proof_path'] = $file->store("payments/{$member->id}", 'local');
        $data['proof_name'] = $file->getClientOriginalName();
        unset($data['proof']);

        // Still waiting for review: replace that upload instead of adding another
        $latest = MemberPayment::latestFor($member);

        if ($latest && $latest->status === 'pending') {
            Storage::disk('local')->delete($latest->proof_path);
            $latest->update($data);
        } else {
            MemberPayment::create($data + ['member_id' => $member->id, 'status' => 'pending']);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Proof of payment uploaded. Your regional admin will confirm it.']);

        return back();
    }

    /** Open the uploaded receipt (image or PDF) in the browser. */
    public function proof(Request $request, MemberPayment $payment): StreamedResponse
    {
        $payment->loadMissing('member');
        abort_unless($payment->canBeViewedBy($request->user()), 403);
        abort_unless(Storage::disk('local')->exists($payment->proof_path), 404);

        return Storage::disk('local')->response($payment->proof_path, $payment->proof_name);
    }
}