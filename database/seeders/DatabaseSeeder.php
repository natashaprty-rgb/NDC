<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Production-safe seed: roles/permissions, generic categories and the
     * first administrator. No organisational data is invented.
     * Demonstration data: php artisan db:seed --class=DemoDataSeeder (non-production only).
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            ReferenceDataSeeder::class,
            AdminUserSeeder::class,
        ]);
    }
}
