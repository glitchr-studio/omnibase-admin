<?php

namespace Base\Admin\Widget;

use Base\Service\TimelineEventProviderInterface;

/**
 * Collects every tagged TimelineEventProviderInterface's events and formats
 * them for the analytics chart - the single place both AnalyticsController
 * (the range-picker's live fetch) and AnalyticsCardWidgetType (initial
 * paint) go for this, so both always return the identical shape.
 */
class TimelineEventRegistry
{
    /**
     * @param iterable<TimelineEventProviderInterface> $providers
     */
    public function __construct(
        private readonly iterable $providers,
    ) {
    }

    /**
     * $series/$labels/$dateFormat are the SAME values already computed for
     * the chart itself, so bounds and formatting stay in lockstep with
     * what's on screen. Chart.js's CategoryScale can only anchor an
     * annotation to a value that IS one of the chart's own x-axis category
     * ticks - an event whose formatted date matches none of $labels is
     * dropped here rather than rendered as a broken/invisible annotation.
     *
     * @param array<int, array{date: string, ...}> $series
     * @param string[] $labels
     *
     * @return array<int, array{label: string, title: string, description: ?string, color: ?string}>
     */
    public function getFormattedEvents(array $series, array $labels, string $dateFormat): array
    {
        if ([] === $series) {
            return [];
        }

        $since = new \DateTimeImmutable($series[0]['date']);
        $until = new \DateTimeImmutable($series[array_key_last($series)]['date']);
        $labelSet = array_flip($labels);

        $events = [];
        foreach ($this->providers as $provider) {
            foreach ($provider->getTimelineEvents($since, $until) as $event) {
                $label = (new \DateTimeImmutable($event['date']))->format($dateFormat);
                if (!isset($labelSet[$label])) {
                    continue;
                }

                $events[] = [
                    'label' => $label,
                    'title' => $event['title'],
                    'description' => $event['description'] ?? null,
                    'color' => $event['color'] ?? null,
                ];
            }
        }

        return $events;
    }
}
