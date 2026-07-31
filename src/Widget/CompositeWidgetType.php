<?php

namespace Base\Admin\Widget;

use Base\Admin\Config\Menu\MenuItem;
use Base\Admin\Config\MenuItem as MenuItemFacade;

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
 * "+ Add widget" palette, no separate opt-out flag needed. The composite
 * ITSELF is still fully hide/resize/reorder-able as one unit through the
 * existing customize UI either way.
 *
 * Two ways a pane gets into params.panes[]: a 'type' key (a code-defined
 * block widget, e.g. analytics_card, wired by an app developer via
 * configureWidgetItems() as shown above) or a 'subItems' key (a plain
 * link-list card, produced end-to-end by the superadmin dragging one
 * dashboard card onto another - see DashboardWidgetController::merge()).
 * Both shapes render through the same @Admin/widget/composite.html.twig.
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
            if (!\is_array($pane)) {
                continue;
            }

            $type = $pane['type'] ?? null;
            if (\is_string($type) && '' !== $type) {
                // A pane referencing 'composite' itself - the one
                // defensive guard needed against a misconfigured
                // composite-inside-composite recursing until PHP's own
                // call-stack limit takes down the whole dashboard with a
                // fatal error.
                if (self::getName() === $type) {
                    continue;
                }

                $paneWidgets[] = (new MenuItem(MenuItem::TYPE_BLOCK, $pane['label'] ?? null, $pane['icon'] ?? null))
                    ->setBlockName($type)
                    ->setParams(\is_array($pane['params'] ?? null) ? $pane['params'] : [])
                    // Reuses MenuItem's own $size (normally a dashboard
                    // grid-column span) as this pane's relative width
                    // WITHIN the composite instead - a different grid
                    // entirely (composite.html.twig's own
                    // grid-template-columns, not the dashboard's), but the
                    // same "how wide relative to its siblings" concept, so
                    // no second property needed on MenuItem for it. Absent
                    // 'size' (every composite persisted before this field
                    // existed) defaults to 1, matching what every pane
                    // already behaved as under the old fixed 1fr-each CSS.
                    ->setSize(\is_int($pane['size'] ?? null) ? $pane['size'] : 1);
                continue;
            }

            // No 'type' - a link-list ("widget-group") pane instead, the
            // shape the drag-to-merge feature produces when you drop one
            // card onto another (see DashboardWidgetController::merge()).
            // Built as a real TYPE_SECTION MenuItem with subItems so
            // composite.html.twig's group-pane branch can render it with
            // the EXACT same markup/twig-property access
            // (item.linkUrl/item.label/item.icon) as dashboard.html.twig's
            // own top-level group branch, rather than inventing a second
            // template shape for what is otherwise identical content.
            $subItems = \is_array($pane['subItems'] ?? null) ? $pane['subItems'] : [];
            $paneSubItems = [];
            foreach ($subItems as $subItem) {
                if (!\is_array($subItem) || !\is_string($subItem['label'] ?? null) || !\is_string($subItem['url'] ?? null) || '' === $subItem['url']) {
                    continue;
                }
                $icon = \is_string($subItem['icon'] ?? null) ? $subItem['icon'] : null;
                $paneSubItems[] = MenuItemFacade::linkToUrl($subItem['label'], $icon, $subItem['url'])->setLinkUrl($subItem['url']);
            }
            if ([] === $paneSubItems) {
                continue;
            }

            $paneWidgets[] = (new MenuItem(MenuItem::TYPE_SECTION, $pane['label'] ?? null, $pane['icon'] ?? null))
                ->setSubItems($paneSubItems)
                ->setSize(\is_int($pane['size'] ?? null) ? $pane['size'] : 1);
        }

        return ['panes' => $paneWidgets];
    }
}
