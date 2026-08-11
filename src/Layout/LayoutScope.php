<?php

namespace Base\Admin\Layout;

/**
 * The customizable regions of the admin - each gets its own persisted
 * layout config, stored under its own SettingBag path (see LayoutStore).
 */
class LayoutScope
{
    public const SIDEBAR = 'sidebar';
    public const DASHBOARD = 'dashboard';
    /**
     * Per-CRUD-page customization (title, description), keyed by the CRUD's
     * URL slug rather than by a sidebar menu item.
     *
     * It needs its own scope because the sidebar-backed mechanism cannot
     * reach these pages at all: an in-place title edit persists onto the
     * page's own MENU ITEM (data-menu-key), and most CRUD pages have no
     * menu item - the sidebar only lists Settings, API keys and a few
     * pre-filtered Users links, so on /admin/articles nothing is marked
     * selected, `_current` resolves to null and the whole editable-header
     * block is skipped. Keying on the slug gives every CRUD page a stable
     * identity to hang customization on whether or not it is in the menu.
     */
    public const CRUD = 'crud';

    public static function all(): array
    {
        return [self::SIDEBAR, self::DASHBOARD, self::CRUD];
    }

    public static function isValid(string $scope): bool
    {
        return \in_array($scope, self::all(), true);
    }
}
