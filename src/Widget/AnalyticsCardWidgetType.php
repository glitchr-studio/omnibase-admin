<?php

namespace Base\Admin\Widget;

use Base\Admin\Config\Menu\MenuItem;
use Base\Service\Analytics;

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
        $days = $widget->getParams()['days'] ?? 14;
        $series = $this->analytics->dailyBreakdown($days);

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
            'change' => $this->analytics->weekOverWeekChange(),
            'events' => $this->timelineEvents->getFormattedEvents($series, $labels, $dateFormat),
        ];
    }
}
