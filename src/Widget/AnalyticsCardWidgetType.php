<?php

namespace Base\Admin\Widget;

use Base\Admin\Config\Menu\MenuItem;
use Base\Service\Analytics;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The built-in "Trafic" dashboard card - also the reference implementation
 * of DashboardWidgetTypeInterface (see AbstractDashboardController::
 * configureDashboardBlockItems(), which registers the default instance).
 * A second instance with different $params (e.g. ['days' => 30]) shows a
 * genuinely different initial window with zero Twig changes required.
 */
final class AnalyticsCardWidgetType implements PaletteDashboardWidgetTypeInterface
{
    public function __construct(
        private readonly Analytics $analytics,
        private readonly TimelineEventRegistry $timelineEvents,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public static function getName(): string
    {
        return 'analytics_card';
    }

    public function getTemplate(): string
    {
        return '@Admin/widget/analytics_card.html.twig';
    }

    public function getDefaultLabel(): string
    {
        return 'dashboard.analytics_title';
    }

    public function getDefaultIcon(): ?string
    {
        return 'fa-solid fa-chart-line';
    }

    /**
     * The period-over-period % that belongs beside a total summed from
     * $sources, or null when there isn't one.
     *
     * @param array<string, float|null> $change
     * @param list<string>              $sources
     */
    private function resolveChange(array $change, array $sources): ?float
    {
        if (1 === \count($sources)) {
            return $change[$sources[0]] ?? null;
        }

        // All three sources visible sums back to exactly the combined
        // column, which has its own precomputed %.
        if ([] !== \array_diff(AnalyticsSeriesPalette::SOURCE_KEYS, $sources)) {
            return null;
        }

        return $change['pageViews'] ?? null;
    }

    public function getTemplateVars(MenuItem $widget): array
    {
        $params = $widget->getParams();
        $days = $params['days'] ?? 14;
        $series = $this->analytics->dailyBreakdown($days);

        // Which lines this instance plots - an editable-per-instance param
        // (see the widget's own settings panel), not a fixed set: human-
        // scale and bot/AI-scale traffic flatten each other on one shared
        // axis, so letting an admin turn a series off is how THIS instance
        // stays readable, rather than the template forcing a one-size split
        // on every instance. Unknown/stale keys (a param saved before a
        // series was renamed or removed) are silently dropped rather than
        // sent to the chart, which never heard of them.
        //
        // The fallback is DEFAULT_VISIBLE (everything but bot and AI), not
        // every series: an untouched instance counted crawlers into its own
        // headline "Vues" number, which on both hosts is a large enough
        // share to change what the number means. See that constant.
        $visibleSeries = \array_values(\array_intersect(
            $params['series'] ?? AnalyticsSeriesPalette::DEFAULT_VISIBLE,
            \array_keys(AnalyticsSeriesPalette::SERIES),
        ));
        if ([] === $visibleSeries) {
            $visibleSeries = AnalyticsSeriesPalette::DEFAULT_VISIBLE;
        }

        // Same 'd/m' format the template's own canvas JSON uses for its
        // labels - kept in lockstep here (rather than importing
        // AnalyticsController's >366-days check) since this path never
        // sees an "all time" multi-year series at initial paint; the
        // picker's own live fetch is what handles that case, through
        // AnalyticsController::breakdown() instead.
        $visibleSources = \array_values(\array_intersect($visibleSeries, AnalyticsSeriesPalette::SOURCE_KEYS));
        $change = $this->analytics->periodOverPeriodChange($days);

        $dateFormat = 'd/m';
        $labels = \array_map(fn (array $day) => (new \DateTimeImmutable($day['date']))->format($dateFormat), $series);

        return [
            'series' => $series,
            // Matches the SAME window the chart itself is showing (was a
            // hardcoded week-over-week regardless of $days, wrong even on
            // this very first render whenever $days != 7 - see the range
            // picker's own live-refetch in layout.html.twig for how this
            // stays in sync after the initial load too).
            'change' => $change,
            'retention' => $this->analytics->retention($days),
            'events' => $this->timelineEvents->getFormattedEvents($series, $labels, $dateFormat),
            'palette' => \array_map(
                fn (array $entry) => \array_merge($entry, ['label' => $this->translator->trans($entry['label'], [], 'admin')]),
                AnalyticsSeriesPalette::SERIES,
            ),
            'visibleSeries' => $visibleSeries,
            // Which of the visible lines feed the "Vues" total, in the
            // order the template sums them. Handed over already resolved
            // rather than intersected again in Twig, because the SAME set
            // has to drive both the number and its % badge below, and
            // deriving it twice is how the two would drift.
            'visibleSources' => $visibleSources,
            // The badge next to that total, matched to what is actually
            // being counted: the whole point of this change is that the
            // number excludes bots, so pairing it with the combined
            // period-over-period % would state a trend for traffic the
            // number does not include.
            //
            // Null when the visible sources are some other combination
            // than "one of them" or "all of them" - Analytics computes a %
            // per source and one for the combined column, but not for an
            // arbitrary subset, and no badge is the honest answer there
            // rather than the nearest available number in a badge that
            // looks exact. The client-side legend handler already takes
            // the same position (it recomputes the total on toggle and
            // deliberately leaves the % alone).
            'pageViewsChange' => $this->resolveChange($change, $visibleSources),
        ];
    }
}
