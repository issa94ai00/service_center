<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards against privilege escalation when roles, permissions and users change.
 *
 * The rule throughout: nobody can act on a user or role holding more than they hold
 * themselves, nor hand out a permission they don't have. Master is above every rule,
 * and only Master can create, touch or remove another Master. At least one active
 * Master must always remain, so the system can't be locked out of role management.
 */
class AccessControlService
{
    public const MASTER = 'master';

    // ------------------------------------------------------------ Questions

    public function isMasterRole(Role $role): bool
    {
        return $role->slug === self::MASTER;
    }

    /**
     * Whether $actor may hand out (or take away) $role.
     */
    public function canGrantRole(User $actor, Role $role): bool
    {
        if ($actor->isMaster()) {
            return true;
        }

        if ($this->isMasterRole($role)) {
            return false;
        }

        return $this->rolePermissionSlugs($role)->diff($actor->permissionSlugs())->isEmpty();
    }

    /**
     * Whether $actor may edit, (de)activate, reset the password of, or delete $target.
     */
    public function canManageUser(User $actor, User $target): bool
    {
        if ($actor->isMaster()) {
            return true;
        }

        if ($target->isMaster()) {
            return false;
        }

        return $target->permissionSlugs()->diff($actor->permissionSlugs())->isEmpty();
    }

    // ------------------------------------------------------------ Assertions

    public function assertCanManageUser(User $actor, User $target): void
    {
        if (! $this->canManageUser($actor, $target)) {
            abort(Response::HTTP_FORBIDDEN, $target->isMaster()
                ? 'لا يمكن تعديل حساب المدير العام (Master) إلا من قِبل مدير عام.'
                : 'لا يمكنك تعديل مستخدم يملك صلاحيات أعلى من صلاحياتك.');
        }
    }

    public function assertCanManageRole(User $actor, Role $role): void
    {
        if (! $this->canGrantRole($actor, $role)) {
            abort(Response::HTTP_FORBIDDEN, $this->isMasterRole($role)
                ? 'دور المدير العام (Master) لا يُدار إلا من قِبل مدير عام.'
                : 'لا يمكنك تعديل دور يتضمن صلاحيات لا تملكها.');
        }
    }

    /**
     * Every role added or removed between $currentIds and $newIds must be one $actor can grant.
     *
     * @param  array<int>  $currentIds
     * @param  array<int>  $newIds
     */
    public function assertCanChangeRoles(User $actor, array $currentIds, array $newIds, string $field = 'role_ids'): void
    {
        $changed = array_values(array_unique(array_merge(
            array_diff($newIds, $currentIds),
            array_diff($currentIds, $newIds),
        )));

        $blocked = Role::query()->with('permissions')->whereIn('id', $changed)->get()
            ->reject(fn (Role $role) => $this->canGrantRole($actor, $role));

        if ($blocked->isNotEmpty()) {
            throw ValidationException::withMessages([
                $field => 'لا يمكنك إسناد أو سحب الأدوار التالية لأنها تتضمن صلاحيات لا تملكها: '
                    . $blocked->pluck('name')->implode('، '),
            ]);
        }
    }

    /**
     * A non-Master may only give a role permissions they hold themselves.
     *
     * @param  array<int>  $permissionIds
     */
    public function assertCanGrantPermissions(User $actor, array $permissionIds): void
    {
        if ($actor->isMaster()) {
            return;
        }

        $missing = Permission::query()->whereIn('id', $permissionIds)->get()
            ->reject(fn (Permission $permission) => $actor->permissionSlugs()->contains($permission->slug));

        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages([
                'permission_ids' => 'لا يمكنك منح صلاحيات لا تملكها: ' . $missing->pluck('name')->implode('، '),
            ]);
        }
    }

    /**
     * Throws if, after the change, no active user would hold the Master role.
     *
     * @param  callable(User): bool  $stillActiveMaster  given a current active Master, whether they remain one
     */
    public function assertKeepsAnActiveMaster(callable $stillActiveMaster, string $field = 'role_ids'): void
    {
        $remaining = $this->activeMasters()->filter($stillActiveMaster);

        if ($remaining->isEmpty()) {
            throw ValidationException::withMessages([
                $field => 'يجب أن يبقى مدير عام (Master) واحد نشط على الأقل في النظام.',
            ]);
        }
    }

    // ------------------------------------------------------------ Helpers

    /**
     * @return Collection<int, User>
     */
    public function activeMasters(): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn ($q) => $q->where('slug', self::MASTER))
            ->get();
    }

    /**
     * @return Collection<int, string>
     */
    private function rolePermissionSlugs(Role $role): Collection
    {
        return $role->loadMissing('permissions')->permissions->pluck('slug');
    }
}
