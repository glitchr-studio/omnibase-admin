<?php

namespace Tests\Base\Admin\Menu;

use Base\Admin\Config\MenuItem;
use Base\Admin\Layout\LayoutArranger;
use Base\Admin\Layout\LayoutStore;
use Base\Admin\Menu\MenuBuilder;
use Base\Admin\Router\AdminRouteRegistry;
use Base\Admin\Router\AdminUrlGenerator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class MenuBuilderTest extends TestCase
{
    private MenuBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new MenuBuilder(
            $this->createStub(AdminRouteRegistry::class),
            $this->createStub(AdminUrlGenerator::class),
            $this->createStub(UrlGeneratorInterface::class),
            $this->createStub(RequestStack::class),
            $this->createStub(TranslatorInterface::class),
            $this->createStub(AuthorizationCheckerInterface::class),
            $this->createStub(LayoutStore::class),
            $this->createStub(LayoutArranger::class),
        );
    }

    public function testNestsItemsFollowingASectionUnderIt(): void
    {
        $section = MenuItem::section('Système')->setKey('s');
        $a = MenuItem::linkToUrl('API keys', null, '/a')->setKey('a');
        $b = MenuItem::linkToUrl('Settings', null, '/b')->setKey('b');

        $result = $this->builder->groupIntoSections([$section, $a, $b]);

        $this->assertSame([$section], $result);
        $this->assertSame(['a', 'b'], array_map(fn ($i) => $i->getKey(), $section->getSubItems()));
    }

    public function testKeepsItemsBeforeTheFirstSectionTopLevel(): void
    {
        $dashboard = MenuItem::linkToUrl('Dashboard', null, '/')->setKey('d');
        $section = MenuItem::section('Système')->setKey('s');
        $a = MenuItem::linkToUrl('API keys', null, '/a')->setKey('a');

        $result = $this->builder->groupIntoSections([$dashboard, $section, $a]);

        $this->assertSame([$dashboard, $section], $result);
        $this->assertSame(['a'], array_map(fn ($i) => $i->getKey(), $section->getSubItems()));
    }

    public function testStartsANewGroupOnEachSuccessiveSection(): void
    {
        $s1 = MenuItem::section('Système')->setKey('s1');
        $a = MenuItem::linkToUrl('API keys', null, '/a')->setKey('a');
        $s2 = MenuItem::section('Rôles')->setKey('s2');
        $b = MenuItem::linkToUrl('Admins', null, '/b')->setKey('b');

        $result = $this->builder->groupIntoSections([$s1, $a, $s2, $b]);

        $this->assertSame([$s1, $s2], $result);
        $this->assertSame(['a'], array_map(fn ($i) => $i->getKey(), $s1->getSubItems()));
        $this->assertSame(['b'], array_map(fn ($i) => $i->getKey(), $s2->getSubItems()));
    }

    public function testLeavesABlockTypeItemTopLevelEvenInsideASection(): void
    {
        $section = MenuItem::section('Système')->setKey('s');
        $block = MenuItem::block('analytics_widget')->setKey('core.analytics_widget');

        $result = $this->builder->groupIntoSections([$section, $block]);

        $this->assertSame([$section, $block], $result);
        $this->assertSame([], $section->getSubItems());
    }
}
