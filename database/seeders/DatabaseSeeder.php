<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Note: do NOT add "use WithoutModelEvents;" here. The Team model
     * needs its events to generate team slugs.
     */
    public function run(): void
    {
        $this->call([
            PhilippineLocationSeeder::class,
            SuperAdminSeeder::class,
        ]);
    }
}