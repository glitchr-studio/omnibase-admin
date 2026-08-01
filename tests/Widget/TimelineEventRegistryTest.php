<?php

namespace Tests\Base\Admin\Widget;

use Base\Admin\Widget\TimelineEventRegistry;
use Base\Service\TimelineEventProviderInterface;
use PHPUnit\Framework\TestCase;

class TimelineEventRegistryTest extends TestCase
{
    private function series(): array
    {
        return [
            ['date' => '2026-01-01'],
            ['date' => '2026-01-02'],
        ];
    }

    private function provider(array $events): TimelineEventProviderInterface
    {
        return new class ($events) implements TimelineEventProviderInterface {
            public function __construct(private readonly array $events)
            {
            }

            public function getTimelineEvents(\DateTimeImmutable $since, ?\DateTimeImmutable $until = null): array
            {
                return $this->events;
            }
        };
    }

    public function testUrlPassesThroughWhenProvided(): void
    {
        $registry = new TimelineEventRegistry([
            $this->provider([['date' => '2026-01-01', 'title' => 'Article published', 'url' => '/blog/my-article']]),
        ]);

        $events = $registry->getFormattedEvents($this->series(), ['01/01', '02/01'], 'd/m');

        $this->assertCount(1, $events);
        $this->assertSame('/blog/my-article', $events[0]['url']);
    }

    public function testUrlDefaultsToNullWhenNotProvided(): void
    {
        $registry = new TimelineEventRegistry([
            $this->provider([['date' => '2026-01-01', 'title' => 'A deploy']]),
        ]);

        $events = $registry->getFormattedEvents($this->series(), ['01/01', '02/01'], 'd/m');

        $this->assertCount(1, $events);
        $this->assertNull($events[0]['url']);
    }

    public function testEventsOutsideTheSeriesWindowAreDropped(): void
    {
        $registry = new TimelineEventRegistry([
            $this->provider([['date' => '2026-06-15', 'title' => 'Too far in the future']]),
        ]);

        $events = $registry->getFormattedEvents($this->series(), ['01/01', '02/01'], 'd/m');

        $this->assertSame([], $events);
    }
}
