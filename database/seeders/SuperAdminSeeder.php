<?php

namespace Database\Seeders;

use App\Actions\Teams\CreateTeam;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class SuperAdminSeeder extends Seeder
{
    /**
     * Creates (or updates) the national super admin from SUPER_ADMIN_* in .env.
     */
    public function run(CreateTeam $createTeam): void
    {
        $email = env('SUPER_ADMIN_EMAIL');
        $password = env('SUPER_ADMIN_PASSWORD');

        if (! $email || ! $password) {
            throw new RuntimeException('Set SUPER_ADMIN_EMAIL and SUPER_ADMIN_PASSWORD in .env first.');
        }

        $user = User::firstOrNew(['email' => $email]);
        $user->name = env('SUPER_ADMIN_NAME', 'National Administrator');
        $user->password = $password;
        $user->role = Role::SuperAdmin;
        $user->region_id = null;
        $user->is_active = true;
        $user->email_verified_at ??= now();
        $user->save();

        // The starter kit's dashboard lives under a team URL, so every user needs a personal team.
        if (! $user->currentTeam) {
            $createTeam->handle($user, $user->name."'s Team", isPersonal: true);
        }

        $this->command->info("Super admin ready: {$user->email}");
    }
}