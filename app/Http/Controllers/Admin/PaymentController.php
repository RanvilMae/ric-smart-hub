<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MemberPayment;
use App\Models\Region;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admins: review proofs of membership payment.
 * Regional admins see their own region; the super admin sees all regions.
 */
class PaymentController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['pending', 'confirmed', 'rejected', 'all'])],
            'region' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $status = $filters['status'] ?? 'pending';

        // Payments this admin may see
        $scoped = MemberPayment::query()
            ->whereHas('member', function ($q) use ($user, $filters) {
                if ($user->isSuperAdmin()) {
                    $q->when($filters['region'] ?? null, fn ($q, $region) => $q->where('region_id', $region));
                } else {
                    $q->where('region_id', $user->region_id);
                }
            });

        $counts = (clone $scoped)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $payments = (clone $scoped)
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($filters['search'] ?? null, function ($q, string $search) {
                $like = "%{$search}%";
                $q->where(fn ($q) => $q
                    ->where('reference_no', 'like', $like)
                    ->orWhereHas('member', fn ($m) => $m
                        ->where('last_name', 'like', $like)
                        ->orWhere('first_name', 'like', $like)
                        ->orWhere('member_code', 'like', $like)));
            })
            ->with(['member.user', 'member.region:id,name', 'member.city:id,name', 'reviewer:id,name'])
            ->orderByRaw("case when status = 'pending' then 0 else 1 end")
            ->latest('updated_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (MemberPayment $p) => [
                'id' => $p->id,
                'member_id' => $p->member_id,
                'member_name' => $p->member?->full_name,
                'member_code' => $p->member?->member_code,
                'location' => collect([$p->member?->city?->name, $p->member?->region?->name])->filter()->implode(', '),
                'method' => $p->method,
                'reference_no' => $p->reference_no,
                'amount' => $p->amount,
                'paid_on' => $p->paid_on?->format('M j, Y'),
                'notes' => $p->notes,
                'status' => $p->status,
                'proof_url' => $p->proofUrl(),
                'is_pdf' => $p->isPdf(),
                'submitted_at' => $p->updated_at?->diffForHumans(),
                'reviewer' => $p->reviewer?->name,
                'reviewed_at' => $p->reviewed_at?->format('M j, Y'),
                'rejection_reason' => $p->rejection_reason,
                'can_review' => $p->status === 'pending' && $p->canBeReviewedBy($user),
            ]);

        return Inertia::render('admin/payments/Index', [
            'payments' => $payments,
            'counts' => [
                'pending' => (int) ($counts['pending'] ?? 0),
                'confirmed' => (int) ($counts['confirmed'] ?? 0),
                'rejected' => (int) ($counts['rejected'] ?? 0),
            ],
            'filters' => [
                'status' => $status,
                'region' => $filters['region'] ?? null,
                'search' => $filters['search'] ?? '',
            ],
            'regions' => $user->isSuperAdmin() ? Region::orderBy('sort_order')->get(['id', 'name']) : null,
        ]);
    }

    public function confirm(Request $request, MemberPayment $payment): RedirectResponse
    {
        $payment->loadMissing('member.user');
        abort_unless($payment->status === 'pending' && $payment->canBeReviewedBy($request->user()), 403);

        $payment->update([
            'status' => 'confirmed',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'rejection_reason' => null,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Payment of {$payment->member->full_name} confirmed. Their digital ID is now available."]);

        return back();
    }

    public function reject(Request $request, MemberPayment $payment): RedirectResponse
    {
        $payment->loadMissing('member.user');
        abort_unless($payment->status === 'pending' && $payment->canBeReviewedBy($request->user()), 403);

        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        $payment->update([
            'status' => 'rejected',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'rejection_reason' => $data['reason'],
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Payment of {$payment->member->full_name} was not accepted. They can upload a new proof."]);

        return back();
    }
}