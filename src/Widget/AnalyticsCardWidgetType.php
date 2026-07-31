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
        $visibleSeries = \array_values(\array_intersect(
            $params['series'] ?? \array_keys(AnalyticsSeriesPalette::SERIES),
            \array_keys(AnalyticsSeriesPalette::SERIES),
        ));
        if ([] === $visibleSeries) {
            $visibleSeries = \array_keys(AnalyticsSeriesPalette::SERIES);
        }

        // Same 'd/m' format the template's own canvas JSON uses for its
        // labels - kept in lockstep here (rather than importing
        // AnalyticsController's >366-days check) since this path never
        // sees an "all time" multi-year series at initial paint; the
        // picker's own live fetch is what handles that case, through
        // AnalyticsController::breakdown() instead.
        $dateFormat = 'd/m';
        $labels = \array_map(fn (array $day) => (new \DateTimeImmutable($day['date']))->format($dateFormat), $series);

        return [
            'series' => $series,
            // Matches the SAME window the chart itself is showing (was a
            // hardcoded week-over-week regardless of $days, wrong even on
            // this very first render whenever $days != 7 - see the range
            // picker's own live-refetch in layout.html.twig for how this
            // stays in sync after the initial load too).
            'change' => $this->analytics->periodOverPeriodChange($days),
            'events' => $this->timelineEvents->getFormattedEvents($series, $labels, $dateFormat),
            'palette' => \array_map(
                fn (array $entry) => \array_merge($entry, ['label' => $this->translator->trans($entry['label'], [], 'admin')]),
                AnalyticsSeriesPalette::SERIES,
            ),
            'visibleSeries' => $visibleSeries,
        ];
    }
}
