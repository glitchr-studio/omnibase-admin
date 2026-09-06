<?php

namespace Base\Admin\Widget;

use Base\Admin\Config\Menu\MenuItem;
use Base\Service\Analytics;
use Symfony\Contracts\Translation\TranslatorInterface;

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
        private readonly TranslatorInterface $translator,
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

        // Which traffic sources this counter counts. Human only unless the
        // admin says otherwise (see AnalyticsSeriesPalette::DEFAULT_VISIBLE
        // for why that is the default rather than the combined column this
        // used to sum): a KPI tile stating a number nearly double the real
        // audience, with no legend next to it to hint that crawlers were in
        // there, is the worst place on the dashboard for that total.
        //
        // This widget has no chart, so the settings panel's checkboxes are
        // its equivalent of the legend - same params.series key the two
        // chart widgets persist, so the concept reads identically wherever
        // it appears.
        $sources = \array_values(\array_intersect(
            $params['series'] ?? AnalyticsSeriesPalette::DEFAULT_VISIBLE,
            AnalyticsSeriesPalette::SOURCE_KEYS,
        ));
        if ([] === $sources) {
            $sources = \array_values(\array_intersect(
                AnalyticsSeriesPalette::DEFAULT_VISIBLE,
                AnalyticsSeriesPalette::SOURCE_KEYS,
            ));
        }

        $sumSources = fn (array $days): int => \array_sum(\array_map(
            fn (array $day) => \array_sum(\array_intersect_key($day, \array_flip($sources))),
            $days,
        ));

        $total = null;
        $change = null;
        if (null !== $path) {
            $series = $this->analytics->dailyBreakdown($days, $path);
            $total = $sumSources($series);
            // Same $days*2-then-split trick Analytics::periodOverPeriodChange()
            // itself uses site-wide, done here directly since that method
            // has no $path parameter of its own (page-view totals are
            // path-scopable, but this app's period-over-period comparison
            // was only ever built for the site-wide case - see its own
            // docblock).
            $fullSeries = $this->analytics->dailyBreakdown($days * 2, $path);
            $previous = $sumSources(\array_slice($fullSeries, 0, $days));
            $current = $sumSources(\array_slice($fullSeries, $days, $days));
            $change = $previous > 0 ? \round((($current - $previous) / $previous) * 100, 1) : null;
        }

        return [
            'classes' => $classes,
            'entityClass' => $class,
            'entity' => $entity,
            'allInstances' => $allInstances,
            // Only the SELECTED class's instances - the picker fetches any
            // other class's list from admin_dashboard_widget_instances when
            // the class select changes. This deliberately reverses the
            // earlier "ship every class inline, avoid a per-class AJAX round
            // trip" call: measured on beta, shipping them all cost 9.2s to
            // render one empty widget and a 106KB fragment, 42.5KB of it the
            // instances attribute alone - about a thousand entities hydrated
            // so a <select> could show one class at a time, growing with the
            // site. One round trip on an explicit class change is the
            // cheaper end of that trade by a wide margin.
            'instances' => null !== $class ? $this->entities->findInstances($class) : [],
            'total' => $total,
            'change' => $change,
            'sources' => $sources,
            'sourceLabels' => \array_map(
                fn (string $key) => $this->translator->trans(AnalyticsSeriesPalette::SERIES[$key]['label'], [], 'admin'),
                \array_combine(AnalyticsSeriesPalette::SOURCE_KEYS, AnalyticsSeriesPalette::SOURCE_KEYS),
            ),
        ];
    }
}
