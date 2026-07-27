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
final class AnalyticsCardWidgetType implements DashboardWidgetTypeInterface
{
    public function __construct(
        private readonly Analytics $analytics,
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

    public function getTemplateVars(MenuItem $widget): array
    {
        $days = $widget->getParams()['days'] ?? 14;

        return [
            'series' => $this->analytics->dailyBreakdown($days),
            'change' => $this->analytics->weekOverWeekChange(),
        ];
    }
}
