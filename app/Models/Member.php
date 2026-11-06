<?php

namespace App\Models;

use App\Support\MemberCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Member extends Model
{
    use SoftDeletes;

    // member_code, user_id and status are set in code, never from the form.
    protected $fillable = [
        'last_name', 'first_name', 'middle_name', 'suffix', 'sex', 'civil_status',
        'birth_date', 'education_attainment', 'degree',
        'region_id', 'province_id', 'city_id', 'barangay_id',
        'district', 'municipality_class', 'zip_code',
        'member_type', 'occupation', 'contact_number', 'alternate_email',
        'organization_address', 'ric_organization',
        'specialization', 'hvcdp_commodity',
        'ati_services', 'lsa_level', 'lsa_type', 'ati_scholarship', 'ati_trainings',
    ];

    protected static function booted(): void
    {
        // RIC-{region}-{year registered}-{000-000}, assigned once and never changed
        static::creating(function (Member $member) {
            if (! $member->member_code && $member->region_id) {
                $year = ($member->created_at ?? now())->year;
                $member->member_code = MemberCode::next((int) $member->region_id, (int) $year);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'reviewed_at' => 'datetime',
            'ati_services' => 'array',
            'ati_trainings' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function barangay(): BelongsTo
    {
        return $this->belongsTo(Barangay::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function getFullNameAttribute(): string
    {
        return collect([$this->first_name, $this->middle_name, $this->last_name, $this->suffix])
            ->filter()->implode(' ');
    }
}
