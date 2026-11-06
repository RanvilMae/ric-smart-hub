<?php

namespace App\Enums;

enum Role: string
{
    case SuperAdmin = 'super_admin';
    case RegionalAdmin = 'regional_admin';
    case Member = 'member';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::RegionalAdmin => 'Regional Admin',
            self::Member => 'Member',
        };
    }
}