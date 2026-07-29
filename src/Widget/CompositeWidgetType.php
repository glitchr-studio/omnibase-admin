<?php

namespace Base\Admin\Widget;

use Base\Admin\Config\Menu\MenuItem;

/**
 * "Dual-width card" fusion: one shared .card holding two (or more) other
 * registered widget types side by side, e.g.:
 *
 *     MenuItem::block('composite', 'Overview', null, null, ['panes' => [
 *         ['type' => 'analytics_card', 'label' => 'This week', 'params' => ['days' => 7]],
 *         ['type' => 'analytics_card', 'label' => 'This month', 'params' => ['days' => 30]],
 *     ]]);
 *
 * Deliberately implements DashboardWidgetTypeInterface ONLY (not
 * PaletteDashboardWidgetTypeInterface) - that's what excludes it from the
 * "+ Add widget" palette, no separate opt-out flag needed. Composing a
 * composite (which two types, which params) is a code-level/app-developer
 * decision in v1, not something a superadmin assembles at runtime through
 * the UI - the composite ITSELF is still fully hide/resize/reorder-able as
 * one unit through the existing customize UI either way.
 */
final class CompositeWidgetType implements DashboardWidgetTypeInterface
{
    public static function getName(): string
    {
        return 'composite';
    }

    public function getTemplate(): string
    {
        return '@Admin/widget/composite.html.twig';
    }

    public function getTemplateVars(MenuItem $widget): array
    {
        $panes = $widget->getParams()['panes'] ?? [];
        $paneWidgets = [];

        foreach ($panes as $pane) {
            $type = $pane['type'] ?? null;
            // Malformed, or a pane referencing 'composite' itself - the one
            // defensive guard needed against a misconfigured composite-
            // inside-composite recursing until PHP's own call-stack limit
            // takes down the whole dashboard with a fatal error.
            if (!\is_string($type) || '' === $type || self::getName() === $type) {
                continue;
            }

            $paneWidgets[] = (new MenuItem(MenuItem::TYPE_BLOCK, $pane['label'] ?? null, $pane['icon'] ?? null))
                ->setBlockName($type)
                ->setParams($pane['params'] ?? []);
        }

        return ['panes' => $paneWidgets];
    }
}
