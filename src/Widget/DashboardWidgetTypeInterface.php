<?php

namespace Base\Admin\Widget;

use Base\Admin\Config\Menu\MenuItem;

/**
 * Self-registering dashboard widget type (mirrors CrudControllerInterface's
 * own auto-tagging pattern - see AdminBundle::build()). Any base-bundle,
 * base-bundle-admin, or app service implementing this becomes usable via
 * MenuItem::block(self::getName(), ...) without touching dashboard.html.twig.
 */
interface DashboardWidgetTypeInterface
{
    /** Matches the blockName passed to MenuItem::block(). */
    public static function getName(): string;

    /** @Twig template path, e.g. '@Admin/widget/analytics_card.html.twig' */
    public function getTemplate(): string;

    /**
     * @return array<string, mixed> vars for the template - merged with
     *         'widget' (the MenuItem instance itself) by the caller
     */
    public function getTemplateVars(MenuItem $widget): array;
}
