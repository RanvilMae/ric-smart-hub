<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Region extends Model
{
    protected $fillable = ['code', 'psgc_code', 'name', 'label', 'sort_order'];

    public function provinces(): HasMany
    {
        return $this->hasMany(Province::class);
    }

    public function cities(): HasMany
    {
        return $this->hasMany(City::class);
    }

        public function members(): HasMany
    {
        return $this->hasMany(Member::class);
    }

    public function admins(): HasMany
    {
        return $this->hasMany(User::class)->where('role', 'regional_admin');
    }
}