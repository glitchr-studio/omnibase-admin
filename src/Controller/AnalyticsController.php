<?php

namespace Base\Admin\Controller;

use Base\Admin\Widget\AnalyticsSeriesPalette;
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

        // "today" plots hour-by-hour (see Analytics::hourlyBreakdown()) -
        // a daily rollup only ever has ONE point for today, which read as
        // a flat, lineless chart (nothing to draw a line BETWEEN); every
        // other range stays the calendar-day series it always was.
        if ('today' === $range) {
            $series = $this->analytics->hourlyBreakdown();
            $dateFormat = 'H:00';
        } else {
            $series = $this->analytics->dailyBreakdown(self::RANGES[$range]);
            // Same date format as the range picker's own hint - a single day
            // ("today") is unambiguous either way, but a long "all time" series
            // spanning years needs the year to actually mean anything.
            $dateFormat = \count($series) > 366 ? 'M Y' : 'd/m';
        }

        $formattedLabels = \array_map(
            fn (array $day) => (new \DateTimeImmutable($day['date']))->format($dateFormat),
            $series,
        );

        // The stat row above the chart (page views/visitors/users totals +
        // period-over-period change) used to stay pinned to whatever window
        // it was first rendered with, contradicting the chart the instant a
        // different range got picked - see layout.html.twig's range-picker
        // handler for where these two land back in the DOM.
        $totals = [];
        foreach (['pageViews', 'uniqueVisitors', 'uniqueUsers'] as $key) {
            $totals[$key] = \array_sum(\array_column($series, $key));
        }

        return $this->json([
            'labels' => $formattedLabels,
            'datasets' => \array_map(
                fn (string $key, array $palette) => [
                    'key' => $key,
                    'label' => $this->translator->trans($palette['label'], [], 'admin'),
                    'data' => \array_map(fn (array $day) => $day[$key], $series),
                    'color' => $palette['color'],
                    'colorDark' => $palette['colorDark'],
                ],
                \array_keys(AnalyticsSeriesPalette::SERIES),
                \array_values(AnalyticsSeriesPalette::SERIES),
            ),
            'events' => $this->timelineEvents->getFormattedEvents($series, $formattedLabels, $dateFormat),
            'totals' => $totals,
            'change' => $this->analytics->periodOverPeriodChange(self::RANGES[$range]),
        ]);
    }
}
