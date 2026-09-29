<?php

namespace Base\Admin\Widget;

use Base\Admin\Config\Menu\MenuItem;
use Base\Admin\Router\AdminRouteRegistry;
use Base\Enum\ThreadState;
use Base\Service\Analytics;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
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
 *
 * Counts one of two different things, chosen per instance:
 *
 *   MODE_VIEWS   page views of the picked entity, over $days - the
 *                original behaviour.
 *   MODE_RECORDS how many records exist, optionally narrowed to a parent
 *                thread and to a set of states ("articles in Europe that
 *                are still drafts"). Nothing to do with traffic; it reads
 *                the table, not the analytics rollups.
 *
 * The two modes deliberately use SEPARATE parameters for their pickers
 * (entityClass/entityId against countClass/scopeId) rather than
 * reinterpreting one pair. Switching modes back and forth then cannot
 * silently repoint the other mode's selection at something that means
 * something else there, and a widget configured both ways keeps both
 * answers.
 */
final class CounterWidgetType implements PaletteDashboardWidgetTypeInterface
{
    public const ALL_INSTANCES = EntityViewsWidgetType::ALL_INSTANCES;

    public const MODE_VIEWS = 'views';
    public const MODE_RECORDS = 'records';

    /**
     * The states worth counting, in the order they are offered.
     *
     * DELETE is left out on purpose - a soft-deleted record is not
     * something a dashboard tile should invite you to tally - and so is
     * PASSWORD, which is a publication mechanism rather than a stage of
     * work anyone counts.
     */
    private const STATES = [
        ThreadState::PUBLISH,
        ThreadState::DRAFT,
        ThreadState::FUTURE,
        ThreadState::SECRET,
        ThreadState::ARCHIVE,
    ];

    public function __construct(
        private readonly Analytics $analytics,
        private readonly LinkableEntityRegistry $entities,
        private readonly TranslatorInterface $translator,
        private readonly EntityManagerInterface $entityManager,
        private readonly AdminRouteRegistry $routes,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly ?\Base\Admin\Security\CrudAccessChecker $crudAccessChecker = null,
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

    /**
     * @param array<string, mixed>  $params
     * @param array<string, string> $classes
     *
     * @return array<string, mixed>
     */
    private function recordVars(array $params, array $classes, string $mode): array
    {
        $countClass = \is_string($params['countClass'] ?? null) ? $params['countClass'] : null;
        if (null !== $countClass && !isset($classes[$countClass])) {
            $countClass = null;
        }

        // The "Dans" narrowing: a parent thread, or nothing for site-wide.
        // Only meaningful for a Thread, which is the only thing here with a
        // parent to be inside of.
        $scopeId = \is_int($params['scopeId'] ?? null) ? $params['scopeId'] : null;
        $scopeClass = \is_string($params['scopeClass'] ?? null) ? $params['scopeClass'] : null;
        if (null === $scopeClass || !isset($classes[$scopeClass])) {
            $scopeClass = null;
            $scopeId = null;
        }
        $scope = (null !== $scopeClass && null !== $scopeId) ? $this->entities->find($scopeClass, $scopeId) : null;

        $states = \array_values(\array_intersect(
            \is_array($params['states'] ?? null) ? $params['states'] : self::STATES,
            self::STATES,
        ));

        $total = null;
        $link = null;
        if (null !== $countClass) {
            $total = $this->countRecords($countClass, $scope, $states);
            $link = $this->crudLink($countClass, $scope, $states);
        }

        return [
            'classes' => $classes,
            'mode' => $mode,
            'entityClass' => null,
            'entity' => null,
            'allInstances' => false,
            'countClass' => $countClass,
            'scopeClass' => $scopeClass,
            'scopeId' => $scopeId,
            'states' => $states,
            'stateLabels' => $this->stateLabels(),
            'link' => $link,
            'total' => $total,
            // No trend: this is a live headcount of rows, and there is no
            // stored history of what the count WAS to compare it against.
            // A fabricated percentage next to an exact number would be the
            // one part of this tile you could not trust.
            'change' => null,
            'sources' => [],
            'sourceLabels' => [],
        ];
    }

    /**
     * @param list<string> $states
     */
    private function countRecords(string $class, ?object $scope, array $states): int
    {
        $metadata = $this->entityManager->getClassMetadata($class);

        $qb = $this->entityManager->createQueryBuilder()
            ->select('COUNT(e.id)')
            ->from($class, 'e');

        // COUNT in the database, never count(findBy(...)) - hydrating every
        // article to learn how many there are is the exact trap this project
        // has already been bitten by elsewhere.
        if (null !== $scope && $metadata->hasAssociation('parent')) {
            $qb->andWhere('e.parent = :scope')->setParameter('scope', $scope);
        }

        if ([] !== $states && $metadata->hasField('state')) {
            $qb->andWhere('e.state IN (:states)')->setParameter('states', $states);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * The CRUD index this number is an answer about, filtered the same way.
     *
     * Returns null rather than a bare index URL when the count cannot be
     * expressed as filters the list will honour - a link that quietly drops
     * the narrowing lands you on a page whose total contradicts the number
     * you clicked, which is worse than not being a link at all.
     *
     * @param list<string> $states
     */
    private function crudLink(string $class, ?object $scope, array $states): ?string
    {
        $controller = $this->routes->getControllerForEntity($class);
        if (null === $controller) {
            return null;
        }

        // the number stays, the link to a list this user may not open goes
        if (null !== $this->crudAccessChecker && !$this->crudAccessChecker->isGranted($controller)) {
            return null;
        }

        $filters = [];
        $metadata = $this->entityManager->getClassMetadata($class);

        if (null !== $scope && $metadata->hasAssociation('parent') && \method_exists($scope, 'getId')) {
            $filters['parent'] = $scope->getId();
        }

        // One state filters exactly; several would need an OR the list's own
        // single-choice filter cannot express, so the link stays unfiltered
        // on state and the tile does not pretend otherwise.
        if (1 === \count($states) && $metadata->hasField('state')) {
            $filters['state'] = $states[0];
        }

        try {
            return $this->urlGenerator->generate(
                $this->routes->getRouteName($controller, 'index'),
                [] !== $filters ? ['filters' => $filters] : [],
            );
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, string>
     */
    private function stateLabels(): array
    {
        $labels = [];
        foreach (self::STATES as $state) {
            $labels[$state] = $this->translator->trans('counter.state.' . \strtolower(\str_replace('STATE_', '', $state)), [], 'admin');
        }

        return $labels;
    }

    public function getTemplateVars(MenuItem $widget): array
    {
        $params = $widget->getParams();
        $days = \is_int($params['days'] ?? null) ? $params['days'] : 14;

        $classes = $this->entities->getPickableClasses();
        $mode = self::MODE_RECORDS === ($params['mode'] ?? null) ? self::MODE_RECORDS : self::MODE_VIEWS;

        if (self::MODE_RECORDS === $mode) {
            return $this->recordVars($params, $classes, $mode);
        }

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
            'mode' => $mode,
            'countClass' => null,
            'scopeClass' => null,
            'scopeId' => null,
            'states' => [],
            'stateLabels' => $this->stateLabels(),
            'link' => null,
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
