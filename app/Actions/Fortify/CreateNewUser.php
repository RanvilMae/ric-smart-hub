<?php

namespace App\Actions\Fortify;

use App\Actions\Teams\CreateTeam;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\Member;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

/**
 * Registers an RIC member: creates the login account and the member
 * profile together. The profile starts as "pending" until the regional
 * admin approves it.
 */
class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    public function __construct(private CreateTeam $createTeam)
    {
        //
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function create(array $input): User
    {
        $o = config('ric');
        $services = (array) ($input['ati_services'] ?? []);

        $validated = Validator::make($input, [
            // Account
            'email' => $this->emailRules(),
            'password' => $this->passwordRules(),

            // Personal information
            'last_name' => ['required', 'string', 'max:100'],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'suffix' => ['nullable', Rule::in($o['suffixes'])],
            'sex' => ['required', Rule::in($o['sexes'])],
            'civil_status' => ['required', Rule::in($o['civil_statuses'])],
            'birth_date' => ['required', 'date', 'before:-10 years', 'after:1900-01-01'],
            'education_attainment' => ['required', Rule::in($o['education_levels'])],
            'degree' => ['nullable', 'string', 'max:255'],

            // Address: each level must belong to the one above it
            'region_id' => ['required', 'integer', Rule::exists('regions', 'id')],
            'province_id' => ['required', 'integer', Rule::exists('provinces', 'id')->where('region_id', $input['region_id'] ?? 0)],
            'city_id' => ['required', 'integer', Rule::exists('cities', 'id')->where('province_id', $input['province_id'] ?? 0)],
            'barangay_id' => ['required', 'integer', Rule::exists('barangays', 'id')->where('city_id', $input['city_id'] ?? 0)],
            'district' => ['required', Rule::in($o['districts'])],
            'municipality_class' => ['nullable', Rule::in($o['municipality_classes'])],
            'zip_code' => ['nullable', 'digits:4'],

            // Membership
            'member_type' => ['required', Rule::in($o['member_types'])],
            'occupation' => ['required', 'string', 'max:255'],
            'contact_number' => ['required', 'regex:/^09\d{9}$/'],
            'alternate_email' => ['nullable', 'email', 'max:255'],
            'organization_address' => ['required', 'string', 'max:255'],
            'ric_organization' => ['required', 'string', 'max:255'],

            // Specialization and ATI services
            'specialization' => ['required', Rule::in($o['specializations'])],
            'hvcdp_commodity' => ['nullable', 'required_if:specialization,HVCDP', Rule::in($o['hvcdp_commodities'])],
            'ati_services' => ['nullable', 'array'],
            'ati_services.*' => [Rule::in($o['ati_services'])],
            'lsa_level' => [Rule::requiredIf(in_array('LSA', $services, true)), 'nullable', Rule::in($o['lsa_levels'])],
            'lsa_type' => [Rule::requiredIf(in_array('LSA', $services, true)), 'nullable', Rule::in($o['lsa_types'])],
            'ati_scholarship' => [Rule::requiredIf(in_array('Scholarship', $services, true)), 'nullable', Rule::in($o['ati_scholarships'])],
            'ati_trainings' => ['nullable', 'array'],
            'ati_trainings.*' => ['string', 'max:255'],
        ], [
            'contact_number.regex' => 'Enter an 11-digit mobile number starting with 09, e.g. 09171234567.',
        ], [
            'region_id' => 'region',
            'province_id' => 'province',
            'city_id' => 'city / municipality',
            'barangay_id' => 'barangay',
            'education_attainment' => 'highest educational attainment',
            'ric_organization' => 'name of RIC organization',
            'hvcdp_commodity' => 'HVCDP commodity',
            'member_type' => 'type of member',
            'alternate_email' => 'email address 2',
        ])->validate();

        $profile = Arr::except($validated, ['email', 'password']);

        // Clear answers that do not apply
        if ($profile['specialization'] !== 'HVCDP') {
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

        return DB::transaction(function () use ($validated, $profile) {
            $user = User::create([
                'name' => trim("{$profile['first_name']} {$profile['last_name']}"),
                'email' => $validated['email'],
                'password' => $validated['password'],
            ]);

            $this->createTeam->handle($user, $user->name."'s Team", isPersonal: true);

            $member = new Member($profile);
            $member->user_id = $user->id;
            $member->status = 'pending';
            $member->save();

            return $user;
        });
    }
}