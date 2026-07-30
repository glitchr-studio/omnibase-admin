<?php

namespace Base\Admin\Controller;

use Base\Admin\Widget\TimelineEventRegistry;
use Base\Service\Analytics;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Backs the dashboard analytics card's range picker (see dashboard.html.
 * twig's analytics_card block + admin-charts.js) - a live re-fetch instead
 * of a full page reload every time the picker changes. Read-only/GET, no
 * CSRF needed (matches the existing gm_show-style "just data" controllers
 * elsewhere in this app, not LayoutController's own POST+CSRF shape).
 */
class AnalyticsController extends AbstractController
{
    private const RANGES = [
        'today' => 1,
        '7d' => 7,
        '14d' => 14,
        '30d' => 30,
        'all' => null,
    ];

    public function __construct(
        private readonly Analytics $analytics,
        private readonly TranslatorInterface $translator,
        private readonly TimelineEventRegistry $timelineEvents,
    ) {
    }

    public function breakdown(string $range): JsonResponse
    {
        if (!\array_key_exists($range, self::RANGES)) {
            throw $this->createNotFoundException(\sprintf('Unknown analytics range "%s".', $range));
        }

        $series = $this->analytics->dailyBreakdown(self::RANGES[$range]);

        $labels = [
            'pageViewsHuman' => $this->translator->trans('analytics.label.page_views_human', [], 'admin'),
            'pageViewsBot' => $this->translator->trans('analytics.label.page_views_bot', [], 'admin'),
            'pageViewsAi' => $this->translator->trans('analytics.label.page_views_ai', [], 'admin'),
            'uniqueVisitors' => $this->translator->trans('analytics.label.unique_visitors', [], 'admin'),
            'uniqueUsers' => $this->translator->trans('analytics.label.unique_users', [], 'admin'),
        ];
        $colors = [
            'pageViewsHuman' => '#2563eb',
            'pageViewsBot' => '#f59e0b',
            'pageViewsAi' => '#8b5cf6',
            'uniqueVisitors' => '#16a34a',
            'uniqueUsers' => '#dc2626',
        ];

        // Same date format as the range picker's own hint - a single day
        // ("today") is unambiguous either way, but a long "all time" series
        // spanning years needs the year to actually mean anything.
        $dateFormat = \count($series) > 366 ? 'M Y' : 'd/m';

        $formattedLabels = \array_map(
            fn (array $day) => (new \DateTimeImmutable($day['date']))->format($dateFormat),
            $series,
        );

        return $this->json([
            'labels' => $formattedLabels,
            'datasets' => \array_map(
                fn (string $key) => [
                    'label' => $labels[$key],
                    'data' => \array_map(fn (array $day) => $day[$key], $series),
                    'color' => $colors[$key],
                ],
                ['pageViewsHuman', 'pageViewsBot', 'pageViewsAi', 'uniqueVisitors', 'uniqueUsers'],
            ),
            'events' => $this->timelineEvents->getFormattedEvents($series, $formattedLabels, $dateFormat),
        ]);
    }
}
