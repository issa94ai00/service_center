<?php

namespace App\Models\Central;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * A platform administrator, signed in on admin.<central domain> through the
 * `admin` guard. Unrelated to the shops' own users.
 */
class Admin extends Authenticatable
{
    protected $fillable = ['name', 'email', 'password', 'last_login_at'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'last_login_at' => 'datetime',
        ];
    }

    public function getConnectionName(): ?string
    {
        return config('tenancy.central_connection');
    }
}
