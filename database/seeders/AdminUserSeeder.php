<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Creates the first Super Administrator from NDC_ADMIN_* environment values.
 * If no password is configured a random one is generated and printed once.
 * The account must change its password at first login.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = strtolower((string) env('NDC_ADMIN_EMAIL', 'admin@example.invalid'));

        if (User::withTrashed()->where('email', $email)->exists()) {
            $this->command?->info("Administrator {$email} already exists - not modified.");

            return;
        }

        $password = (string) env('NDC_ADMIN_PASSWORD', '');
        $generated = false;
        if ($password === '') {
            $password = Str::password(16);
            $generated = true;
        }

        $user = User::query()->create([
            'name' => (string) env('NDC_ADMIN_NAME', 'System Administrator'),
            'email' => $email,
            'password' => $password,
            'status' => User::STATUS_ACTIVE,
            'must_change_password' => true,
        ]);
        $user->roles()->attach(Role::query()->where('slug', 'super-admin')->value('id'), ['assigned_at' => now()]);

        $this->command?->info("Super Administrator created: {$email}");
        if ($generated) {
            $this->command?->warn("Generated temporary password (shown once): {$password}");
        }
    }
}
