<?php

namespace Tests\Base\Admin\Layout;

use Base\Admin\Config\MenuItem;
use Base\Admin\Config\Menu\MenuItem as MenuItemModel;
use Base\Admin\Layout\LayoutArranger;
use Base\Admin\Layout\LayoutConfig;
use PHPUnit\Framework\TestCase;

class LayoutArrangerTest extends TestCase
{
    private LayoutArranger $arranger;

    protected function setUp(): void
    {
        $this->arranger = new LayoutArranger();
    }

    public function testReordersItemsAccordingToStoredKeyOrder(): void
    {
        $a = MenuItem::linkToUrl('A', null, '/a')->setKey('a');
        $b = MenuItem::linkToUrl('B', null, '/b')->setKey('b');

        $config = LayoutConfig::fromArray([
            'items' => [
                ['key' => 'b', 'visible' => true],
                ['key' => 'a', 'visible' => true],
            ],
        ]);

        $result = $this->arranger->apply([$a, $b], $config);

        $this->assertSame(['b', 'a'], array_map(fn ($i) => $i->getKey(), $result));
    }

    public function testHidesAnItemMarkedNotVisibleWithoutRemovingIt(): void
    {
        $a = MenuItem::linkToUrl('A', null, '/a')->setKey('a');

        $config = LayoutConfig::fromArray(['items' => [['key' => 'a', 'visible' => false]]]);
        $result = $this->arranger->apply([$a], $config);

        $this->assertCount(1, $result);
        $this->assertTrue($result[0]->isHidden());
    }

    public function testDropsAStaleStoredKeyWithNoMatchingCodeItem(): void
    {
        $a = MenuItem::linkToUrl('A', null, '/a')->setKey('a');

        $config = LayoutConfig::fromArray(['items' => [
            ['key' => 'removed-crud', 'visible' => true],
            ['key' => 'a', 'visible' => true],
        ]]);
        $result = $this->arranger->apply([$a], $config);

        $this->assertSame(['a'], array_map(fn ($i) => $i->getKey(), $result));
    }

    public function testAppendsANewCodeItemAbsentFromTheStoredConfig(): void
    {
        $a = MenuItem::linkToUrl('A', null, '/a')->setKey('a');
        $b = MenuItem::linkToUrl('B', null, '/b')->setKey('b');

        $config = LayoutConfig::fromArray(['items' => [['key' => 'a', 'visible' => true]]]);
        $result = $this->arranger->apply([$a, $b], $config);

        $this->assertSame(['a', 'b'], array_map(fn ($i) => $i->getKey(), $result));
        $this->assertFalse($result[1]->isHidden());
    }

    public function testAppliesRecursivelyToSubItems(): void
    {
        $child1 = MenuItem::linkToUrl('C1', null, '/c1')->setKey('c1');
        $child2 = MenuItem::linkToUrl('C2', null, '/c2')->setKey('c2');
        $section = MenuItem::section('S')->setKey('s')->setSubItems([$child1, $child2]);

        $config = LayoutConfig::fromArray(['items' => [
            ['key' => 's', 'visible' => true, 'children' => [
                ['key' => 'c2', 'visible' => true],
                ['key' => 'c1', 'visible' => false],
            ]],
        ]]);
        $result = $this->arranger->apply([$section], $config);

        $subKeys = array_map(fn ($i) => $i->getKey(), $result[0]->getSubItems());
        $this->assertSame(['c2', 'c1'], $subKeys);
        $this->assertTrue($result[0]->getSubItems()[1]->isHidden());
    }

    public function testCaptureThenApplyRoundTripsToTheSameOrder(): void
    {
        $a = MenuItem::linkToUrl('A', null, '/a')->setKey('a');
        $b = MenuItem::linkToUrl('B', null, '/b')->setKey('b');

        $captured = $this->arranger->capture([$a, $b]);
        $result = $this->arranger->apply([$a, $b], $captured);

        $this->assertSame(['a', 'b'], array_map(fn ($i) => $i->getKey(), $result));
    }

    public function testAppliesAStoredSizeAndClampsItToTheValidRange(): void
    {
        $a = MenuItem::linkToUrl('A', null, '/a')->setKey('a')->setSize(1);

        $config = LayoutConfig::fromArray(['items' => [['key' => 'a', 'visible' => true, 'size' => 99]]]);
        $result = $this->arranger->apply([$a], $config);

        $this->assertSame(3, $result[0]->getSize());
    }

    public function testCaptureThenApplyRoundTripsTheCodeDefinedSize(): void
    {
        $a = MenuItem::linkToUrl('A', null, '/a')->setKey('a')->setSize(3);

        $captured = $this->arranger->capture([$a]);
        $result = $this->arranger->apply([$a], $captured);

        $this->assertSame(3, $result[0]->getSize());
    }

    public function testSynthesizesAnAdHocWidgetFromAStoredEntryWithNoCodeMatch(): void
    {
        $config = LayoutConfig::fromArray(['items' => [
            ['key' => 'adhoc.1', 'visible' => true, 'size' => 2, 'blockName' => 'analytics_card', 'label' => 'Second card', 'icon' => 'fa-solid fa-chart-line', 'params' => ['days' => 30]],
        ]]);

        $result = $this->arranger->apply([], $config);

        $this->assertCount(1, $result);
        $this->assertSame(MenuItemModel::TYPE_BLOCK, $result[0]->getType());
        $this->assertTrue($result[0]->isAdHoc());
        $this->assertSame('analytics_card', $result[0]->getBlockName());
        $this->assertSame('Second card', $result[0]->getLabel());
        $this->assertSame(['days' => 30], $result[0]->getParams());
        $this->assertSame(2, $result[0]->getSize());
        $this->assertFalse($result[0]->isHidden());
    }

    public function testAStoredEntryWithNoBlockNameAndNoCodeMatchIsStillDroppedAsStale(): void
    {
        $config = LayoutConfig::fromArray(['items' => [
            ['key' => 'removed-crud', 'visible' => true],
        ]]);

        $result = $this->arranger->apply([], $config);

        $this->assertSame([], $result);
    }

    public function testAnAdHocWidgetOmittedFromTheNextSaveIsNeverSynthesizedAgain(): void
    {
        // Simulates "remove": the superadmin's next Done click simply
        // doesn't include this key anymore - no separate delete path exists
        // or is needed anywhere in LayoutArranger.
        $config = LayoutConfig::fromArray(['items' => []]);

        $result = $this->arranger->apply([], $config);

        $this->assertSame([], $result);
    }
}
