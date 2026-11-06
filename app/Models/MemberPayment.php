<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Proof of membership payment. The member's digital ID can be viewed
 * and printed once their latest payment is confirmed.
 */
class MemberPayment extends Model
{
    public const METHODS = [
        'GCash',
        'Maya',
        'Bank deposit / transfer',
        'Cash (paid to RIC officer)',
        'Other',
    ];

    protected $fillable = [
        'member_id', 'method', 'reference_no', 'amount', 'paid_on', 'notes',
        'proof_path', 'proof_name', 'status', 'reviewed_by', 'reviewed_at', 'rejection_reason',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_on' => 'date',
            'reviewed_at' => 'datetime',
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** The member's most recent payment upload. */
    public static function latestFor(Member $member): ?self
    {
        return static::where('member_id', $member->id)->latest('id')->first();
    }

    public static function hasConfirmed(Member $member): bool
    {
        return static::where('member_id', $member->id)->where('status', 'confirmed')->exists();
    }

    public function isPdf(): bool
    {
        return str_ends_with(strtolower($this->proof_path), '.pdf');
    }

    public function proofUrl(): string
    {
        return "/payments/{$this->id}/proof";
    }

    /**
     * Who may confirm or reject: the active regional admin of the member's region.
     * Payments of admins themselves are confirmed by the super admin.
     */
    public function canBeReviewedBy(User $user): bool
    {
        $owner = $this->member?->user;

        if ($owner && $owner->is($user)) {
            return false; // no one confirms their own payment
        }

        if ($owner && ($owner->isRegionalAdmin() || $owner->isSuperAdmin())) {
            return $user->isSuperAdmin();
        }

        return $user->isRegionalAdmin()
            && $user->is_active
            && (int) $this->member?->region_id === (int) $user->region_id;
    }

    /** Who may open the uploaded proof: the member, the super admin, and admins of the region. */
    public function canBeViewedBy(User $user): bool
    {
        return $user->isSuperAdmin()
            || (int) $this->member?->user_id === (int) $user->id
            || ($user->isRegionalAdmin() && (int) $this->member?->region_id === (int) $user->region_id);
    }

    /** Data for the member's own page. */
    public function toSummary(): array
    {
        return [
            'id' => $this->id,
            'method' => $this->method,
            'reference_no' => $this->reference_no,
            'amount' => $this->amount,
            'paid_on' => $this->paid_on?->format('M j, Y'),
            'paid_on_value' => $this->paid_on?->format('Y-m-d'),
            'notes' => $this->notes,
            'status' => $this->status,
            'proof_url' => $this->proofUrl(),
            'proof_name' => $this->proof_name,
            'is_pdf' => $this->isPdf(),
            'submitted_at' => $this->created_at?->format('M j, Y g:i A'),
            'reviewed_at' => $this->reviewed_at?->format('M j, Y'),
            'rejection_reason' => $this->rejection_reason,
        ];
    }
}