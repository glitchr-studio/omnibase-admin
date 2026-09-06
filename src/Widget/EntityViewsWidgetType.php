<?php

namespace Base\Admin\Widget;

use Base\Admin\Config\Menu\MenuItem;
use Base\Service\Analytics;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Generalizes what used to be App\Admin\Widget\ArticleViewsWidgetType
 * (Article-only) into a widget that can show traffic for ANY
 * LinkableInterface entity this app has - discovered via
 * LinkableEntityRegistry, not hardcoded to Article or any other single
 * class. Lives in base-bundle-admin (not app-level) for exactly that
 * reason: nothing in here references Article, Destination, User, or any
 * other app-specific class by name - the package stays entity-agnostic,
 * same standing rule as every other built-in widget type here.
 *
 * Same underlying Base\Service\Analytics page-view rollup as the
 * site-wide Trafic widget, just scoped to one entity's own __toLink()
 * path (or, with the "all instances of this class" option, every
 * instance's own path summed together).
 */
final class EntityViewsWidgetType implements PaletteDashboardWidgetTypeInterface
{
    private const SOURCE_KEYS = AnalyticsSeriesPalette::SOURCE_KEYS;

    /**
     * params.entityId's "every instance of entityClass, summed" sentinel -
     * a string (never a real id, which is always an int) so is_int()
     * below still cleanly tells this apart from "nothing picked yet"
     * (null) with no separate flag to keep in sync.
     */
    public const ALL_INSTANCES = 'all';

    public function __construct(
        private readonly Analytics $analytics,
        private readonly LinkableEntityRegistry $entities,
        private readonly TranslatorInterface $translator,
        private readonly TimelineEventRegistry $timelineEvents,
    ) {
    }

    public static function getName(): string
    {
        return 'entity_views';
    }

    public function getTemplate(): string
    {
        return '@Admin/widget/entity_views.html.twig';
    }

    public function getDefaultLabel(): string
    {
        return 'dashboard.entity_views_title';
    }

    public function getDefaultIcon(): ?string
    {
        return 'fa-solid fa-chart-simple';
    }

    public function getTemplateVars(MenuItem $widget): array
    {
        $params = $widget->getParams();
        $days = \is_int($params['days'] ?? null) ? $params['days'] : 14;

        $classes = $this->entities->getPickableClasses();

        // A class from a previously-saved instance that no longer exists
        // (renamed/removed entity) is silently treated as "nothing picked
        // yet" rather than fatally erroring - same degrade-gracefully
        // posture ArticleViewsWidgetType had for a deleted article.
        $class = \is_string($params['entityClass'] ?? null) ? $params['entityClass'] : null;
        if (null !== $class && !isset($classes[$class])) {
            $class = null;
        }

        $entityId = $params['entityId'] ?? null;
        $allInstances = null !== $class && self::ALL_INSTANCES === $entityId;
        $entity = (null !== $class && \is_int($entityId)) ? $this->entities->find($class, $entityId) : null;

        if ($allInstances) {
            // Every published instance's own __toLink() - there's no
            // single stable path PREFIX shared by every instance of a
            // class in this app's routing (see Analytics::dailyBreakdown()'s
            // own docblock), so "all" means this exact set, not a LIKE
            // pattern.
            $paths = $this->entities->allPaths($class);
            $path = [] !== $paths ? $paths : null;
        } else {
            $path = $entity?->__toLink();
        }

        // Neither a specific entity nor "all" picked yet (a freshly added,
        // unconfigured instance) - an empty series rather than a query
        // with nothing to scope to, so the template can render its own
        // "pick something" prompt instead of silently showing site-wide
        // traffic under this widget's own header.
        $series = null !== $path ? $this->analytics->dailyBreakdown($days, $path) : [];

        $dateFormat = 'd/m';
        $labels = \array_map(fn (array $day) => (new \DateTimeImmutable($day['date']))->format($dateFormat), $series);

        // Same 3 source slots (Human/Bot/AI) the site-wide traffic widget
        // uses, same colors - Visitors/Users are deliberately excluded
        // (see Analytics::dailyBreakdown()'s own docblock: they're not
        // answerable per-path with the current data model).
        // Which source lines this instance plots, same per-instance param
        // (and same legend-click persistence) the site-wide traffic card
        // has - this widget used to hardcode all three unconditionally,
        // which is why its own header total counted crawlers with no way
        // to say otherwise. Defaults to human only here: unlike the
        // site-wide card there are no visitor/user lines to keep, so
        // DEFAULT_VISIBLE's non-source half has nothing to contribute.
        $visibleSeries = \array_values(\array_intersect(
            $params['series'] ?? AnalyticsSeriesPalette::DEFAULT_VISIBLE,
            self::SOURCE_KEYS,
        ));
        if ([] === $visibleSeries) {
            $visibleSeries = \array_values(\array_intersect(AnalyticsSeriesPalette::DEFAULT_VISIBLE, self::SOURCE_KEYS));
        }

        $palette = \array_intersect_key(
            \array_map(
                fn (array $entry) => \array_merge($entry, ['label' => $this->translator->trans($entry['label'], [], 'admin')]),
                AnalyticsSeriesPalette::SERIES,
            ),
            \array_flip(self::SOURCE_KEYS),
        );

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
            'series' => $series,
            'palette' => $palette,
            'visibleSeries' => $visibleSeries,
            // The header total sums exactly these, so it matches the lines
            // the legend is showing rather than Analytics' bot-inclusive
            // combined column (see AnalyticsSeriesPalette::DEFAULT_VISIBLE).
            'total' => \array_sum(\array_map(
                fn (array $day) => \array_sum(\array_intersect_key($day, \array_flip($visibleSeries))),
                $series,
            )),
            'events' => (null !== $path) ? $this->timelineEvents->getFormattedEvents($series, $labels, $dateFormat) : [],
        ];
    }
}
