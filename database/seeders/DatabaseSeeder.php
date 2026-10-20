<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Production-safe seed: permissions, default roles and reference lists only.
 * No operational data is created. Create the first administrator with
 *   php artisan ndc:create-admin
 * Sample data for demonstrations and training lives in DemoDataSeeder and is
 * never run automatically.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            ReferenceDataSeeder::class,
        ]);
    }
}
