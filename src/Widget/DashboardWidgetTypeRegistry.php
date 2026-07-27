<?php

namespace Base\Admin\Widget;

/**
 * Indexes every tagged DashboardWidgetTypeInterface by name - the single
 * lookup point dashboard.html.twig's widget loop uses to dispatch a
 * block-type MenuItem to its renderer, replacing what used to be one
 * hardcoded {% if widget.blockName == 'analytics_card' %} in the template.
 */
class DashboardWidgetTypeRegistry
{
    /** @var array<string, DashboardWidgetTypeInterface> */
    private array $types = [];

    /**
     * @param iterable<DashboardWidgetTypeInterface> $types
     */
    public function __construct(iterable $types)
    {
        foreach ($types as $type) {
            $this->types[$type::getName()] = $type;
        }
    }

    public function get(string $name): ?DashboardWidgetTypeInterface
    {
        return $this->types[$name] ?? null;
    }
}
