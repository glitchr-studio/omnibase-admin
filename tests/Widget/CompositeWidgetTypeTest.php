<?php

namespace Tests\Base\Admin\Widget;

use Base\Admin\Config\MenuItem;
use Base\Admin\Widget\CompositeWidgetType;
use PHPUnit\Framework\TestCase;

class CompositeWidgetTypeTest extends TestCase
{
    private CompositeWidgetType $type;

    protected function setUp(): void
    {
        $this->type = new CompositeWidgetType();
    }

    public function testPaneSizeIsCarriedThroughForABlockTypePane(): void
    {
        $widget = MenuItem::block('composite', 'Overview', null, null, ['panes' => [
            ['type' => 'analytics_card', 'label' => 'A', 'size' => 1],
            ['type' => 'analytics_card', 'label' => 'B', 'size' => 2],
        ]]);

        $vars = $this->type->getTemplateVars($widget);

        $this->assertCount(2, $vars['panes']);
        $this->assertSame(1, $vars['panes'][0]->getSize());
        $this->assertSame(2, $vars['panes'][1]->getSize());
    }

    public function testPaneSizeIsCarriedThroughForAGroupSubItemsPane(): void
    {
        $widget = MenuItem::block('composite', 'Overview', null, null, ['panes' => [
            ['label' => 'Links', 'size' => 3, 'subItems' => [
                ['label' => 'A', 'icon' => null, 'url' => '/a'],
            ]],
        ]]);

        $vars = $this->type->getTemplateVars($widget);

        $this->assertCount(1, $vars['panes']);
        $this->assertSame(3, $vars['panes'][0]->getSize());
    }

    /**
     * Every composite persisted before pane sizing existed has no 'size'
     * key in its stored params.panes[] at all - must default to 1, which
     * is what every pane already behaved as under the old fixed
     * grid-template-columns: repeat(2, 1fr) CSS (see composite.html.twig).
     */
    public function testMissingSizeDefaultsToOneForBackwardCompatibility(): void
    {
        $widget = MenuItem::block('composite', 'Overview', null, null, ['panes' => [
            ['type' => 'analytics_card', 'label' => 'A'],
        ]]);

        $vars = $this->type->getTemplateVars($widget);

        $this->assertSame(1, $vars['panes'][0]->getSize());
    }

    public function testNonIntegerSizeIsIgnoredInFavorOfTheDefault(): void
    {
        $widget = MenuItem::block('composite', 'Overview', null, null, ['panes' => [
            ['type' => 'analytics_card', 'label' => 'A', 'size' => 'not-a-number'],
        ]]);

        $vars = $this->type->getTemplateVars($widget);

        $this->assertSame(1, $vars['panes'][0]->getSize());
    }
}
