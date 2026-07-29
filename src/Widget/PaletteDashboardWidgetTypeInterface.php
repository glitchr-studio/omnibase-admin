<?php

namespace Base\Admin\Widget;

/**
 * Marks a DashboardWidgetTypeInterface as eligible for the "+ Add widget"
 * palette. Purely additive - extending (not modifying) the base interface
 * means no existing implementer breaks, and it's also what EXCLUDES a type
 * from the palette with no separate opt-out flag: CompositeWidgetType
 * implements only DashboardWidgetTypeInterface, never this one.
 */
interface PaletteDashboardWidgetTypeInterface extends DashboardWidgetTypeInterface
{
    /** Translation key or literal label shown in the palette, and used as the default label for a freshly added instance. */
    public function getDefaultLabel(): string;

    public function getDefaultIcon(): ?string;
}
