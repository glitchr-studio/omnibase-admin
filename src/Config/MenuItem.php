<?php

namespace Base\Admin\Config;

use Base\Admin\Config\Menu\MenuItem as Item;
use Symfony\Contracts\Translation\TranslatableInterface;

/**
 * Static factory facade: same call sites as before
 * (MenuItem::linkToRoute(...), MenuItem::section(...), ...), now producing
 * a single MenuItem model instead of eight wrapper classes.
 */
class MenuItem
{
    public static function linkToCrud(string $entityFqcn, TranslatableInterface|string|null $label = null, ?string $icon = null): Item
    {
        return (new Item(Item::TYPE_CRUD, $label, $icon))
            ->setEntityFqcn($entityFqcn)
            ->setCrudAction('index');
    }

    public static function linkToDashboard(TranslatableInterface|string $label, ?string $icon = null): Item
    {
        return new Item(Item::TYPE_DASHBOARD, $label, $icon);
    }

    public static function linkToExitImpersonation(TranslatableInterface|string $label, ?string $icon = null): Item
    {
        return new Item(Item::TYPE_EXIT_IMPERSONATION, $label, $icon);
    }

    public static function linkToLogout(TranslatableInterface|string $label, ?string $icon = null): Item
    {
        return new Item(Item::TYPE_LOGOUT, $label, $icon);
    }

    public static function linkToRoute(string $routeName, array $routeParameters = [], TranslatableInterface|string|null $label = null, ?string $icon = null): Item
    {
        return (new Item(Item::TYPE_ROUTE, $label, $icon))
            ->setRoute($routeName, $routeParameters);
    }

    public static function linkToUrl(TranslatableInterface|string $label, ?string $icon, string $url): Item
    {
        return (new Item(Item::TYPE_URL, $label, $icon))
            ->setUrl($url);
    }

    public static function section(TranslatableInterface|string|null $label = null, ?string $icon = null): Item
    {
        return new Item(Item::TYPE_SECTION, $label, $icon);
    }

    public static function subMenu(TranslatableInterface|string $label, ?string $icon = null, ?string $url = null): Item
    {
        return (new Item(Item::TYPE_SUBMENU, $label, $icon))
            ->setUrl($url);
    }

    /**
     * References a registered DashboardWidgetTypeInterface (by name) rather
     * than a link - used for widgets that aren't navigation, like the
     * analytics chart/stats. Auto-keyed under "core.<blockName>" so it's
     * orderable/hideable like everything else.
     *
     * $instanceKey lets the SAME widget type be used more than once with
     * independent hide/order/size state - e.g. two analytics_card
     * instances showing different windows via $params:
     *   MenuItem::block('analytics_card', 'dashboard.analytics_title', 'fa-solid fa-chart-line')
     *   MenuItem::block('analytics_card', 'Last 30 days', 'fa-solid fa-chart-line', '30d', ['days' => 30])
     * Left null, the key is byte-for-byte "core.<blockName>" exactly as
     * before - every existing call site is unaffected.
     */
    public static function block(
        string $blockName,
        TranslatableInterface|string|null $label = null,
        ?string $icon = null,
        ?string $instanceKey = null,
        array $params = [],
    ): Item {
        return (new Item(Item::TYPE_BLOCK, $label, $icon))
            ->setBlockName($blockName)
            ->setParams($params)
            ->setKey('core.' . $blockName . (null !== $instanceKey ? '.' . $instanceKey : ''));
    }
}
