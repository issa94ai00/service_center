<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'is_active',
        'locale',
    ];

    /** @var array{0: \Illuminate\Database\Eloquent\Collection<int, Role>, 1: Collection<int, string>}|null */
    private ?array $permissionSlugCache = null;

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles');
    }

    public function permissions()
    {
        return $this->roles()->with('permissions');
    }

    public function hasRole(string $slug): bool
    {
        return $this->roles->contains('slug', $slug);
    }

    public function isMaster(): bool
    {
        return $this->hasRole('master');
    }

    public function hasPermission(string $permissionSlug): bool
    {
        if ($this->isMaster()) {
            return true;
        }

        return $this->permissionSlugs()->contains($permissionSlug);
    }

    /**
     * The slugs granted by the user's roles (Master holds every permission).
     *
     * Loaded in one query and cached against the loaded `roles` collection, so the
     * many checks in a request don't each walk the roles; a reload of `roles`
     * (e.g. after a sync) is picked up automatically.
     *
     * @return Collection<int, string>
     */
    public function permissionSlugs(): Collection
    {
        $this->loadMissing('roles.permissions');

        // Holding the collection itself (not its object id, which PHP may reuse) keys the cache.
        $roles = $this->roles;
        if ($this->permissionSlugCache && $this->permissionSlugCache[0] === $roles) {
            return $this->permissionSlugCache[1];
        }

        $slugs = $this->isMaster()
            ? Permission::query()->pluck('slug')
            : $this->roles->flatMap(fn (Role $role) => $role->permissions)->pluck('slug')->unique()->values();

        $this->permissionSlugCache = [$roles, $slugs];

        return $slugs;
    }
}
