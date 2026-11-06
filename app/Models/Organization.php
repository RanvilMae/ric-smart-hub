<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An RIC organization / club in a region.
 * Members are linked by name: members.ric_organization in the same region.
 */
class Organization extends Model
{
    protected $fillable = [
        'region_id', 'province_id', 'city_id', 'barangay_id', 'address',
        'name', 'description', 'specializations', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'specializations' => 'array',
        ];
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** e.g. "Purok 3, Brgy. San Isidro, Luna, La Union" */
    public function fullAddress(): string
    {
        return collect([
            $this->address,
            $this->barangay ? "Brgy. {$this->barangay->name}" : null,
            $this->city?->name,
            $this->province?->name,
        ])->filter()->implode(', ');
    }
}