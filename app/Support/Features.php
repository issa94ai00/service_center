<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\User;

/**
 * The feature switches in Settings (feature_repairs, feature_technicians, ...).
 */
class Features
{
    /**
     * Whether $feature is on for $user. Master bypasses every switch, as everywhere else.
     */
    public static function enabled(string $feature, ?User $user = null): bool
    {
        if ($user?->isMaster()) {
            return true;
        }

        return self::on($feature);
    }

    /**
     * Whether the switch itself is on, with no Master bypass. For summary pages (dashboard,
     * reports) that should drop a switched-off module for everyone, Master included.
     */
    public static function on(string $feature): bool
    {
        return filter_var(Setting::get("feature_{$feature}", '1'), FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Whether $user may see a module: they hold $permission and its feature is on.
     */
    public static function allows(?User $user, string $feature, string $permission): bool
    {
        return $user !== null && $user->hasPermission($permission) && self::enabled($feature, $user);
    }
}
