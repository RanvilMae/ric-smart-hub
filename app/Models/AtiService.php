<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * An ATI service a member can tick on the registration form
 * ("Which services from the Agricultural Training Institute have you availed?").
 * Managed by the super admin under Data management → ATI services.
 */
class AtiService extends Model
{
    protected $fillable = ['name', 'description', 'follow_up', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /** Training, Scholarship and LSA open extra questions, so their names stay fixed. */
    public function isLocked(): bool
    {
        return $this->follow_up !== null;
    }

    /** How many member records ticked this service. */
    public function membersCount(): int
    {
        return Member::query()->whereJsonContains('ati_services', $this->name)->count();
    }
}