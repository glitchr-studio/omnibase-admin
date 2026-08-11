<?php

namespace Base\Admin\Widget;

use Base\Admin\Config\Menu\MenuItem;
use Base\Service\Analytics;

/**
 * The "Counter" palette entry: a single number + period-over-period trend
 * for one picked entity (or "all instances of a class", summed) - no
 * chart, unlike EntityViewsWidgetType ("Vues"), which this otherwise
 * mirrors closely (same LinkableEntityRegistry-driven class/instance
 * picker, same params shape). A genuinely separate widget type rather
 * than "Vues with the canvas hidden": the whole point is a compact, at-a-
 * glance KPI tile, and reusing the exact same getTemplateVars() shape
 * would compute/hand over a full daily series this template would never
 * use.
 */
final class CounterWidgetType implements PaletteDashboardWidgetTypeInterface
{
    public const ALL_INSTANCES = EntityViewsWidgetType::ALL_INSTANCES;

    public function __construct(
        private readonly Analytics $analytics,
        private readonly LinkableEntityRegistry $entities,
    ) {
    }

    public static function getName(): string
    {
        return 'counter';
    }

    public function getTemplate(): string
    {
        return '@Admin/widget/counter.html.twig';
    }

    public function getDefaultLabel(): string
    {
        return 'dashboard.counter_title';
    }

    public function getDefaultIcon(): ?string
    {
        return 'fa-solid fa-hashtag';
    }

    public function getTemplateVars(MenuItem $widget): array
    {
        $params = $widget->getParams();
        $days = \is_int($params['days'] ?? null) ? $params['days'] : 14;

        $classes = $this->entities->getPickableClasses();

        $class = \is_string($params['entityClass'] ?? null) ? $params['entityClass'] : null;
        if (null !== $class && !isset($classes[$class])) {
            $class = null;
        }

        $entityId = $params['entityId'] ?? null;
        $allInstances = null !== $class && self::ALL_INSTANCES === $entityId;
        $entity = (null !== $class && \is_int($entityId)) ? $this->entities->find($class, $entityId) : null;

        if ($allInstances) {
            $paths = $this->entities->allPaths($class);
            $path = [] !== $paths ? $paths : null;
        } else {
            $path = $entity?->__toLink();
        }

        $total = null;
        $change = null;
        if (null !== $path) {
            $series = $this->analytics->dailyBreakdown($days, $path);
            $total = \array_sum(\array_column($series, 'pageViews'));
            // Same $days*2-then-split trick Analytics::periodOverPeriodChange()
            // itself uses site-wide, done here directly since that method
            // has no $path parameter of its own (page-view totals are
            // path-scopable, but this app's period-over-period comparison
            // was only ever built for the site-wide case - see its own
            // docblock).
            $fullSeries = $this->analytics->dailyBreakdown($days * 2, $path);
            $previous = \array_sum(\array_column(\array_slice($fullSeries, 0, $days), 'pageViews'));
            $current = \array_sum(\array_column(\array_slice($fullSeries, $days, $days), 'pageViews'));
            $change = $previous > 0 ? \round((($current - $previous) / $previous) * 100, 1) : null;
        }

        return [
            'classes' => $classes,
            'entityClass' => $class,
            'entity' => $entity,
            'allInstances' => $allInstances,
            'instancesByClass' => \array_combine(
                \array_keys($classes),
                \array_map(fn (string $c) => $this->entities->findInstances($c), \array_keys($classes)),
            ),
            'total' => $total,
            'change' => $change,
        ];
    }
}
