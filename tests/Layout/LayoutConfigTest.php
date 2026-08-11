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

        // 'height', 'background', and 'deleted' are newer additions and
        // always present - an old stored config predating them simply
        // degrades to null/true/false ("auto" / "keep the card chrome" /
        // "not deleted"), same as any other missing field already does.
        $this->assertSame([
            'key' => 'a',
            'visible' => true,
            'size' => 2,
            'height' => null,
            'background' => true,
            'deleted' => false,
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
            'height' => null,
            'background' => true,
            'deleted' => false,
            'children' => [],
            'blockName' => 'analytics_card',
            'label' => 'Second card',
            'icon' => 'fa-solid fa-chart-line',
            'params' => ['days' => 30],
        ], $config->getItems()[0]);
    }

    /**
     * A code-defined (non-ad-hoc) item's title, edited in place - no
     * blockName/icon/params, just the new label. This is what makes a
     * plain link-list card's own title editable (Blog/Photos/Destinations/
     * ...), not just analytics_card's/entity_views' - see
     * LayoutArranger::applyLevel()'s own matching half.
     */
    public function testLabelAloneWithNoBlockNameRoundTrips(): void
    {
        $config = LayoutConfig::fromArray(['items' => [
            ['key' => 'auto.1', 'visible' => true, 'label' => 'Renamed section'],
        ]]);

        $this->assertSame([
            'key' => 'auto.1',
            'visible' => true,
            'size' => 1,
            'height' => null,
            'background' => true,
            'deleted' => false,
            'children' => [],
            'label' => 'Renamed section',
        ], $config->getItems()[0]);
    }

    public function testEmptyLabelWithNoBlockNameIsOmittedNotStoredAsBlank(): void
    {
        $config = LayoutConfig::fromArray(['items' => [
            ['key' => 'auto.1', 'visible' => true, 'label' => ''],
        ]]);

        $this->assertArrayNotHasKey('label', $config->getItems()[0]);
    }

    public function testAnItemWithoutBlockNameStaysTheSevenKeyShape(): void
    {
        $config = LayoutConfig::fromArray(['items' => [['key' => 'a', 'visible' => true]]]);

        $this->assertSame(['key', 'visible', 'size', 'height', 'background', 'deleted', 'children'], array_keys($config->getItems()[0]));
    }

    public function testNonStringBlockNameIsIgnored(): void
    {
        $config = LayoutConfig::fromArray(['items' => [['key' => 'a', 'visible' => true, 'blockName' => 42]]]);

        $this->assertSame(['key', 'visible', 'size', 'height', 'background', 'deleted', 'children'], array_keys($config->getItems()[0]));
    }

    public function testDeletedFlagRoundTrips(): void
    {
        $config = LayoutConfig::fromArray(['items' => [
            ['key' => 'a', 'visible' => true, 'deleted' => true],
        ]]);

        $this->assertTrue($config->getItems()[0]['deleted']);
    }

    public function testMissingDeletedDefaultsToFalse(): void
    {
        $config = LayoutConfig::fromArray(['items' => [['key' => 'a', 'visible' => true]]]);

        $this->assertFalse($config->getItems()[0]['deleted']);
    }

    public function testRestoreItemClearsTheDeletedFlagForItsKey(): void
    {
        $config = LayoutConfig::fromArray(['items' => [
            ['key' => 'a', 'visible' => true, 'deleted' => true],
            ['key' => 'b', 'visible' => true, 'deleted' => true],
        ]]);

        $config->restoreItem('a');

        $this->assertFalse($config->getItems()[0]['deleted']);
        $this->assertTrue($config->getItems()[1]['deleted'], 'restoring one key must not touch another');
    }

    public function testRestoreItemIsANoOpForAnUnknownKey(): void
    {
        $config = LayoutConfig::fromArray(['items' => [
            ['key' => 'a', 'visible' => true, 'deleted' => true],
        ]]);

        $config->restoreItem('does-not-exist');

        $this->assertTrue($config->getItems()[0]['deleted']);
    }

    public function testHeightRoundTripsWhenProvided(): void
    {
        $config = LayoutConfig::fromArray(['items' => [
            ['key' => 'a', 'visible' => true, 'height' => 350],
        ]]);

        $this->assertSame(350, $config->getItems()[0]['height']);
    }

    public function testHeightIsClampedToTheValidRange(): void
    {
        $config = LayoutConfig::fromArray(['items' => [
            ['key' => 'a', 'visible' => true, 'height' => 1],
            ['key' => 'b', 'visible' => true, 'height' => 99999],
        ]]);

        $this->assertSame(80, $config->getItems()[0]['height']);
        $this->assertSame(2000, $config->getItems()[1]['height']);
    }

    public function testNonIntHeightDegradesToNull(): void
    {
        $config = LayoutConfig::fromArray(['items' => [
            ['key' => 'a', 'visible' => true, 'height' => 'tall'],
        ]]);

        $this->assertNull($config->getItems()[0]['height']);
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
        $deep = ['a' => ['b' => ['c' => ['d' => ['e' => ['f' => 'too deep']]]]]];
        $config = LayoutConfig::fromArray(['items' => [
            ['key' => 'a', 'visible' => true, 'blockName' => 'analytics_card', 'params' => $deep],
        ]]);

        // Same depthRemaining<0 recursion pattern as sanitizeItems() -
        // MAX_PARAMS_DEPTH=4 allows keys 5 levels deep (a/b/c/d/e - needed
        // so a merged composite widget's params.panes[n].subItems[n].url
        // shape, itself 3 array levels under the top-level params array,
        // survives sanitization), the 6th level (f) is what gets dropped
        // to [].
        $this->assertSame(['a' => ['b' => ['c' => ['d' => ['e' => []]]]]], $config->getItems()[0]['params']);
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

    public function testColumnsDefaultsToFiveWhenMissing(): void
    {
        $config = LayoutConfig::fromArray(['items' => []]);

        $this->assertSame(5, $config->getColumns());
    }

    public function testColumnsIsClampedToTheValidRange(): void
    {
        $this->assertSame(2, LayoutConfig::fromArray(['columns' => 0])->getColumns());
        $this->assertSame(2, LayoutConfig::fromArray(['columns' => -5])->getColumns());
        $this->assertSame(10, LayoutConfig::fromArray(['columns' => 999])->getColumns());
        $this->assertSame(3, LayoutConfig::fromArray(['columns' => 3])->getColumns());
    }

    public function testNonIntColumnsDegradesToDefault(): void
    {
        $this->assertSame(5, LayoutConfig::fromArray(['columns' => 'ten'])->getColumns());
        $this->assertSame(5, LayoutConfig::fromArray(['columns' => null])->getColumns());
    }

    public function testItemSizeIsClampedToTheConfiguredColumnsNotAFixedThree(): void
    {
        // A widget-size of 5 used to be silently capped to 3 (the old
        // hardcoded MAX_SIZE) - now it's capped to whatever the SAME
        // config's own columns value is, and can legitimately survive
        // above 3 when columns allows it.
        $config = LayoutConfig::fromArray([
            'columns' => 10,
            'items' => [['key' => 'a', 'visible' => true, 'size' => 5, 'children' => []]],
        ]);
        $this->assertSame(5, $config->getItems()[0]['size']);

        // A narrower configured column count clamps size down to match,
        // even below the old fixed ceiling of 3.
        $config = LayoutConfig::fromArray([
            'columns' => 2,
            'items' => [['key' => 'a', 'visible' => true, 'size' => 3, 'children' => []]],
        ]);
        $this->assertSame(2, $config->getItems()[0]['size']);
    }

    public function testToArrayIncludesColumns(): void
    {
        $config = LayoutConfig::fromArray(['columns' => 7, 'items' => []]);

        $this->assertSame(['version' => 1, 'columns' => 7, 'items' => []], $config->toArray());
    }

    public function testIntlMapSurvivesSanitizationPerLocale(): void
    {
        $config = LayoutConfig::fromArray(['items' => [
            ['key' => 'k', 'intl' => [
                'en-GB' => ['label' => 'API keys', 'description' => 'Desc'],
                'de-DE' => ['label' => 'API-Schlüssel'],
            ]],
        ]]);

        $this->assertSame([
            'en-GB' => ['label' => 'API keys', 'description' => 'Desc'],
            'de-DE' => ['label' => 'API-Schlüssel'],
        ], $config->getItems()[0]['intl']);
    }

    public function testIntlDropsEmptyValuesMalformedLocalesAndEmptyMaps(): void
    {
        $config = LayoutConfig::fromArray(['items' => [
            // '' values = clear-to-reset per locale, bad locale keys and
            // non-array values are hostile/garbage input - all dropped,
            // and a locale (or the whole map) left empty vanishes.
            ['key' => 'a', 'intl' => [
                'en-GB' => ['label' => '', 'description' => ''],
                'EN_GB' => ['label' => 'bad locale key'],
                'de-DE' => 'not an array',
                5 => ['label' => 'numeric locale'],
            ]],
            ['key' => 'b', 'intl' => 'not an array'],
        ]]);

        $this->assertArrayNotHasKey('intl', $config->getItems()[0]);
        $this->assertArrayNotHasKey('intl', $config->getItems()[1]);
    }
}
