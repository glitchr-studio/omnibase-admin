<?php

namespace Base\Admin\Layout;

/**
 * The two customizable regions of the admin - each gets its own persisted
 * layout config, stored under its own SettingBag path (see LayoutStore).
 */
class LayoutScope
{
    public const SIDEBAR = 'sidebar';
    public const DASHBOARD = 'dashboard';

    public static function all(): array
    {
        return [self::SIDEBAR, self::DASHBOARD];
    }

    public static function isValid(string $scope): bool
    {
        return \in_array($scope, self::all(), true);
    }
}
