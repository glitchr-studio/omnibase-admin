<?php

namespace Tests\Base\Admin\Layout;

use Base\Admin\Layout\LayoutConfig;
use PHPUnit\Framework\TestCase;

class LayoutConfigTest extends TestCase
{
    public function testOldShapeConfigRoundTripsIdentically(): void
    {
        $config = LayoutConfig::fromArray([
            'version' => 1,
            'items' => [
                ['key' => 'a', 'visible' => true, 'size' => 2, 'children' => []],
            ],
        ]);

        $this->assertSame([
            'key' => 'a',
            'visible' => true,
            'size' => 2,
            'children' => [],
        ], $config->getItems()[0]);
    }

    public function testGarbageDataDegradesToAnEmptyConfigWithoutThrowing(): void
    {
        $config = LayoutConfig::fromArray('not an array');
        $this->assertSame([], $config->getItems());
        $this->assertSame(1, $config->getVersion());
    }

    public function testWellFormedAdHocEntryRoundTripsAllFourNewFields(): void
    {
        $config = LayoutConfig::fromArray(['items' => [
            ['key' => 'adhoc.1', 'visible' => true, 'size' => 1, 'blockName' => 'analytics_card', 'label' => 'Second card', 'icon' => 'fa-solid fa-chart-line', 'params' => ['days' => 30]],
        ]]);

        $this->assertSame([
            'key' => 'adhoc.1',
            'visible' => true,
            'size' => 1,
            'children' => [],
            'blockName' => 'analytics_card',
            'label' => 'Second card',
            'icon' => 'fa-solid fa-chart-line',
            'params' => ['days' => 30],
        ], $config->getItems()[0]);
    }

    public function testAnItemWithoutBlockNameStaysTheFourKeyShape(): void
    {
        $config = LayoutConfig::fromArray(['items' => [['key' => 'a', 'visible' => true]]]);

        $this->assertSame(['key', 'visible', 'size', 'children'], array_keys($config->getItems()[0]));
    }

    public function testNonStringBlockNameIsIgnored(): void
    {
        $config = LayoutConfig::fromArray(['items' => [['key' => 'a', 'visible' => true, 'blockName' => 42]]]);

        $this->assertSame(['key', 'visible', 'size', 'children'], array_keys($config->getItems()[0]));
    }

    public function testMissingLabelDefaultsToEmptyStringNotNull(): void
    {
        $config = LayoutConfig::fromArray(['items' => [
            ['key' => 'a', 'visible' => true, 'blockName' => 'analytics_card'],
        ]]);

        $this->assertSame('', $config->getItems()[0]['label']);
    }

    public function testNonArrayParamsDegradesToEmptyArray(): void
    {
        $config = LayoutConfig::fromArray(['items' => [
            ['key' => 'a', 'visible' => true, 'blockName' => 'analytics_card', 'params' => 'not an array'],
        ]]);

        $this->assertSame([], $config->getItems()[0]['params']);
    }

    public function testDeeplyNestedParamsAreCappedNotThrown(): void
    {
        $deep = ['a' => ['b' => ['c' => ['d' => ['e' => 'too deep']]]]];
        $config = LayoutConfig::fromArray(['items' => [
            ['key' => 'a', 'visible' => true, 'blockName' => 'analytics_card', 'params' => $deep],
        ]]);

        // Same depthRemaining<0 recursion pattern as sanitizeItems() -
        // MAX_PARAMS_DEPTH=3 allows keys 4 levels deep (a/b/c/d), the 5th
        // level (e) is what gets dropped to [].
        $this->assertSame(['a' => ['b' => ['c' => ['d' => []]]]], $config->getItems()[0]['params']);
    }

    public function testOversizedParamCountIsCapped(): void
    {
        $params = [];
        for ($i = 0; $i < 100; $i++) {
            $params['key' . $i] = $i;
        }

        $config = LayoutConfig::fromArray(['items' => [
            ['key' => 'a', 'visible' => true, 'blockName' => 'analytics_card', 'params' => $params],
        ]]);

        $this->assertLessThanOrEqual(50, \count($config->getItems()[0]['params']));
    }

    public function testOversizedLabelAndIconAreTruncated(): void
    {
        $config = LayoutConfig::fromArray(['items' => [
            ['key' => 'a', 'visible' => true, 'blockName' => str_repeat('x', 500), 'label' => str_repeat('y', 500), 'icon' => str_repeat('z', 500)],
        ]]);

        $item = $config->getItems()[0];
        $this->assertLessThanOrEqual(100, \mb_strlen($item['blockName']));
        $this->assertLessThanOrEqual(200, \mb_strlen($item['label']));
        $this->assertLessThanOrEqual(100, \mb_strlen($item['icon']));
    }
}
