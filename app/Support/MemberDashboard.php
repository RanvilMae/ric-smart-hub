<?php

namespace App\Support;

use App\Models\Member;
use App\Models\MemberPayment;

/**
 * Numbers for the member's dashboard: the national RIC database
 * and the member's own region. Counts approved members only.
 */
class MemberDashboard
{
    public static function props(Member $member): array
    {
        $member->loadMissing(['region:id,name,label', 'province:id,name', 'city:id,name']);

        $approved = fn () => Member::query()->where('status', 'approved');
        $orgs = 'count(distinct nullif(ric_organization, \'\')) as total';

        $payment = MemberPayment::latestFor($member);

        return [
            'member' => [
                'first_name' => $member->first_name,
                'member_code' => $member->member_code,
                'ric_organization' => $member->ric_organization,
                'region' => $member->region?->name,
                'region_label' => $member->region?->label,
                'province' => $member->province?->name,
                'city' => $member->city?->name,
                'photo' => (bool) $member->photo_path,
            ],
            'national' => [
                'members' => $approved()->count(),
                'organizations' => (int) $approved()->selectRaw($orgs)->first()?->total,
                'regions' => $approved()->distinct()->count('region_id'),
                'thisYear' => $approved()->whereYear('created_at', now()->year)->count(),
            ],
            'region' => [
                'members' => $approved()->where('region_id', $member->region_id)->count(),
                'organizations' => (int) $approved()->where('region_id', $member->region_id)->selectRaw($orgs)->first()?->total,
                'province' => $member->province_id
                    ? $approved()->where('province_id', $member->province_id)->count()
                    : null,
                'city' => $member->city_id
                    ? $approved()->where('city_id', $member->city_id)->count()
                    : null,
                'myOrganization' => $member->ric_organization
                    ? $approved()->where('region_id', $member->region_id)->where('ric_organization', $member->ric_organization)->count()
                    : null,
                'thisYear' => $approved()->where('region_id', $member->region_id)->whereYear('created_at', now()->year)->count(),
            ],
            'payment' => [
                'status' => $payment?->status,
                'confirmed' => MemberPayment::hasConfirmed($member),
            ],
        ];
    }
}