<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;

/**
 * One shop on the platform. Lives in the central database; the shop's own data
 * is in the database named by `database`.
 */
class Tenant extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'slug', 'name', 'database', 'status', 'source', 'signup_ip', 'plan', 'expires_at',
        'owner_name', 'owner_email', 'contact_phone', 'notes',
        'ssl_status', 'ssl_message',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'date',
        ];
    }

    public function getConnectionName(): ?string
    {
        return config('tenancy.central_connection');
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->endOfDay()->isPast();
    }

    /** Whether the shop's users may sign in and work right now. */
    public function isAccessible(): bool
    {
        return $this->status === self::STATUS_ACTIVE && ! $this->isExpired();
    }

    public function host(): string
    {
        return $this->slug.'.'.config('tenancy.central_domain');
    }

    public function url(): string
    {
        return 'https://'.$this->host();
    }
}
