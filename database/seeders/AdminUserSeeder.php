<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    /**
     * Seed the initial administrator account from environment configuration.
     */
    public function run(): void
    {
        $email = config('admin.email');
        $password = config('admin.password');

        if (blank($email) || blank($password)) {
            $this->command?->warn('ADMIN_EMAIL and ADMIN_PASSWORD must be set; skipping administrator seeding.');

            return;
        }

        $user = User::query()->firstOrNew(['email' => $email]);
        $user->name = config('admin.name');
        $user->password = $password;
        $user->is_admin = true;
        $user->email_verified_at ??= now();
        $user->save();
    }
}
