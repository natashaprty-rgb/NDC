<?php

namespace App\Console\Commands;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

#[Signature('ndc:create-admin {--name=} {--email=}')]
#[Description('Create a Super Administrator account (prompts for the password)')]
class CreateAdminCommand extends Command
{
    public function handle(): int
    {
        $this->callSilent('db:seed', ['--class' => RolesAndPermissionsSeeder::class, '--force' => true]);

        $name = $this->option('name') ?: text('Full name', required: true);
        $email = $this->option('email') ?: text('E-mail address', required: true);
        $secret = password('Password', required: true, hint: 'At least '.setting('password_min_length').' characters with upper/lower case, number and symbol.');

        $validator = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $secret],
            ['name' => ['required', 'max:255'], 'email' => ['required', 'email', 'unique:users,email'], 'password' => [Password::defaults()]],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::query()->create(['name' => $name, 'email' => $email, 'password' => $secret, 'status' => 'active']);
        $user->forceFill(['password_changed_at' => now(), 'email_verified_at' => now()])->save();
        $user->assignRole(config('ndc.super_admin_role'));

        $this->info("Super Administrator {$email} created.");

        return self::SUCCESS;
    }
}
