<?php

namespace App\Concerns;

use Illuminate\Validation\Rule;

/**
 * Validation of the RIC member profile (same fields as the registration form).
 */
trait MemberProfileRules
{
    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    protected function memberProfileRules(array $input): array
    {
        $o = config('ric');
        $services = (array) ($input['ati_services'] ?? []);

        return [
            'last_name' => ['required', 'string', 'max:100'],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'suffix' => ['nullable', Rule::in($o['suffixes'])],
            'sex' => ['required', Rule::in($o['sexes'])],
            'civil_status' => ['required', Rule::in($o['civil_statuses'])],
            'birth_date' => ['required', 'date', 'before:-10 years', 'after:1900-01-01'],
            'education_attainment' => ['required', Rule::in($o['education_levels'])],
            'degree' => ['nullable', 'string', 'max:255'],
            'region_id' => ['required', 'integer', Rule::exists('regions', 'id')],
            'province_id' => ['required', 'integer', Rule::exists('provinces', 'id')->where('region_id', $input['region_id'] ?? 0)],
            'city_id' => ['required', 'integer', Rule::exists('cities', 'id')->where('province_id', $input['province_id'] ?? 0)],
            'barangay_id' => ['required', 'integer', Rule::exists('barangays', 'id')->where('city_id', $input['city_id'] ?? 0)],
            'district' => ['required', Rule::in($o['districts'])],
            'municipality_class' => ['nullable', Rule::in($o['municipality_classes'])],
            'zip_code' => ['nullable', 'digits:4'],
            'member_type' => ['required', Rule::in($o['member_types'])],
            'occupation' => ['required', 'string', 'max:255'],
            'contact_number' => ['required', 'regex:/^09\d{9}$/'],
            'alternate_email' => ['nullable', 'email', 'max:255'],
            'organization_address' => ['required', 'string', 'max:255'],
            'ric_organization' => ['required', 'string', 'max:255'],
            'specialization' => ['required', Rule::in($o['specializations'])],
            'hvcdp_commodity' => ['nullable', 'required_if:specialization,HVCDP', Rule::in($o['hvcdp_commodities'])],
            'ati_services' => ['nullable', 'array'],
            'ati_services.*' => [Rule::in($o['ati_services'])],
            'lsa_level' => [Rule::requiredIf(in_array('LSA', $services, true)), 'nullable', Rule::in($o['lsa_levels'])],
            'lsa_type' => [Rule::requiredIf(in_array('LSA', $services, true)), 'nullable', Rule::in($o['lsa_types'])],
            'ati_scholarship' => [Rule::requiredIf(in_array('Scholarship', $services, true)), 'nullable', Rule::in($o['ati_scholarships'])],
            'ati_trainings' => ['nullable', 'array'],
            'ati_trainings.*' => ['string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    protected function memberProfileMessages(): array
    {
        return ['contact_number.regex' => 'Enter an 11-digit mobile number starting with 09, e.g. 09171234567.'];
    }

    /** @return array<string, string> */
    protected function memberProfileAttributes(): array
    {
        return [
            'region_id' => 'region',
            'province_id' => 'province',
            'city_id' => 'city / municipality',
            'barangay_id' => 'barangay',
            'education_attainment' => 'highest educational attainment',
            'ric_organization' => 'name of RIC organization',
            'hvcdp_commodity' => 'HVCDP commodity',
            'member_type' => 'type of member',
            'alternate_email' => 'email address 2',
        ];
    }

    /**
     * Clear answers that do not apply.
     *
     * @param  array<string, mixed>  $profile
     * @return array<string, mixed>
     */
    protected function cleanMemberProfile(array $profile): array
    {
        $services = (array) ($profile['ati_services'] ?? []);

        if (($profile['specialization'] ?? null) !== 'HVCDP') {
            $profile['hvcdp_commodity'] = null;
        }
        if (! in_array('LSA', $services, true)) {
            $profile['lsa_level'] = $profile['lsa_type'] = null;
        }
        if (! in_array('Scholarship', $services, true)) {
            $profile['ati_scholarship'] = null;
        }
        if (! in_array('Training', $services, true)) {
            $profile['ati_trainings'] = null;
        }

        return $profile;
    }
}
