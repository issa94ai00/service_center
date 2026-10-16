<?php

namespace App\Console\Commands;

use App\Models\Central\Admin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TenancyAdmin extends Command
{
    protected $signature = 'tenancy:admin
        {email : Platform admin email}
        {--name=مدير المنصة}
        {--password= : New password (generated when omitted)}';

    protected $description = 'Create a platform admin for admin.<central domain>, or reset their password';

    public function handle(): int
    {
        $password = $this->option('password') ?: Str::password(16, symbols: false);

        $admin = Admin::query()->firstOrNew(['email' => $this->argument('email')]);
        $created = ! $admin->exists;
        $admin->name = $admin->name ?: $this->option('name');
        $admin->password = Hash::make($password);
        $admin->save();

        $this->info(($created ? 'Created' : 'Reset password for')." {$admin->email}");
        if (! $this->option('password')) {
            $this->warn("Password: {$password}");
        }

        return self::SUCCESS;
    }
}
