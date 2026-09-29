<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $password = env('ADMIN_INITIAL_PASSWORD')
            ?: substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789'), 0, 16);

        /** @var User $user */
        $user = User::query()->firstOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@damatech.sy')],
            [
                'name' => env('ADMIN_NAME', 'مدير النظام'),
                'password' => Hash::make($password),
                'is_active' => true,
                'locale' => 'ar',
            ]
        );

        $adminRole = Role::where('slug', 'admin')->first();

        if ($adminRole && ! $user->roles()->where('role_id', $adminRole->id)->exists()) {
            $user->roles()->attach($adminRole->id);
        }

        // When ADMIN_INITIAL_PASSWORD is absent a random one is generated above.
        // Printing it on creation is the only chance to learn it — otherwise the
        // account exists with a password nobody can recover.
        if ($user->wasRecentlyCreated) {
            $this->command?->info("Admin user created: {$user->email}");

            if (! env('ADMIN_INITIAL_PASSWORD')) {
                $this->command?->warn("Generated password: {$password}");
                $this->command?->warn('Store it now and change it after first sign-in — it is not recoverable.');
            }
        }
    }
}
