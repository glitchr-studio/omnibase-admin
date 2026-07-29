<?php

namespace Base\Admin\Widget;

/**
 * The palette-eligible subset of registered widget types - same shape as
 * DashboardWidgetTypeRegistry but indexes only PaletteDashboardWidgetTypeInterface
 * implementers, since not every widget type should be addable through the
 * "+ Add widget" UI (e.g. CompositeWidgetType).
 */
class PaletteWidgetTypeRegistry
{
    /** @var array<string, PaletteDashboardWidgetTypeInterface> */
    private array $types = [];

    /**
     * @param iterable<PaletteDashboardWidgetTypeInterface> $types
     */
    public function __construct(iterable $types)
    {
        foreach ($types as $type) {
            $this->types[$type::getName()] = $type;
        }
    }

    public function get(string $name): ?PaletteDashboardWidgetTypeInterface
    {
        return $this->types[$name] ?? null;
    }

    /**
     * @return PaletteDashboardWidgetTypeInterface[]
     */
    public function all(): array
    {
        return array_values($this->types);
    }
}
