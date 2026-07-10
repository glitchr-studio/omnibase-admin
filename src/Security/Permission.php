<?php

namespace Base\Admin\Security;

/**
 * Admin permission attributes. Constant names and string values are kept
 * identical to the historical EA_* set so existing isGranted() call sites
 * and security configuration keep working after the switch.
 */
class Permission
{
    public const EA_ACCESS_ENTITY = 'EA_ACCESS_ENTITY';
    public const EA_EXECUTE_ACTION = 'EA_EXECUTE_ACTION';
    public const EA_VIEW_MENU_ITEM = 'EA_VIEW_MENU_ITEM';
    public const EA_VIEW_FIELD = 'EA_VIEW_FIELD';
    public const EA_EXIT_IMPERSONATION = 'EA_EXIT_IMPERSONATION';

    public static function exists(?string $permissionName): bool
    {
        if (null === $permissionName) {
            return false;
        }

        return \defined('self::' . $permissionName);
    }
}
