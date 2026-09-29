<?php

namespace Tests\Base\Admin\Menu;

use Base\Admin\Config\MenuItem;
use Base\Admin\Layout\LayoutArranger;
use Base\Admin\Layout\LayoutConfig;
use Base\Admin\Layout\LayoutStore;
use Base\Admin\Menu\MenuBuilder;
use Base\Admin\Router\AdminRouteRegistry;
use Base\Admin\Router\AdminUrlGenerator;
use Base\Admin\Security\CrudAccessChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Tests\Base\Admin\Router\Fixtures\Crud\ReportCrudController;

require_once __DIR__ . '/../Router/Fixtures/Crud/ReportCrudController.php';

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

    public function testDeletedItemsFiltersPermissionDeniedItemsThenDelegatesToLayoutArranger(): void
    {
        // Same filterGranted() pass resolve() itself does - a
        // permission-gated item the current user can't see shouldn't show
        // up in the restore picker just because a superadmin deleted it.
        $granted = MenuItem::linkToUrl('A', null, '/a')->setKey('a');
        $denied = MenuItem::linkToUrl('B', null, '/b')->setKey('b')->setPermission('SOME_PERM');

        $authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authChecker->method('isGranted')->with('SOME_PERM')->willReturn(false);

        $config = LayoutConfig::new();
        $layoutStore = $this->createMock(LayoutStore::class);
        $layoutStore->expects($this->once())->method('get')->with('dashboard')->willReturn($config);

        $layoutArranger = $this->createMock(LayoutArranger::class);
        $layoutArranger->expects($this->once())
            ->method('deletedItems')
            ->with([$granted], $config)
            ->willReturn([$granted]);

        $builder = new MenuBuilder(
            $this->createStub(AdminRouteRegistry::class),
            $this->createStub(AdminUrlGenerator::class),
            $this->createStub(UrlGeneratorInterface::class),
            $this->createStub(RequestStack::class),
            $this->createStub(TranslatorInterface::class),
            $authChecker,
            $layoutStore,
            $layoutArranger,
        );

        $result = $builder->deletedItems([$granted, $denied], 'dashboard');

        $this->assertSame([$granted], $result);
    }

    /**
     * A builder whose one registered CRUD (ReportCrudController) declares
     * ROLE_CREATOR in its configureCrud(), for a user who has it or not.
     */
    private function builderForCrud(bool $granted, ?int $expectedChecks = null): MenuBuilder
    {
        $registry = $this->createStub(AdminRouteRegistry::class);
        $registry->method('getControllers')->willReturn([ReportCrudController::class => 'reports']);

        $urls = $this->createStub(AdminUrlGenerator::class);
        $urls->method('setController')->willReturnSelf();
        $urls->method('setAction')->willReturnSelf();
        $urls->method('generateUrl')->willReturn('/admin/reports');

        $authChecker = $this->createMock(AuthorizationCheckerInterface::class);
        $authChecker->expects(null === $expectedChecks ? $this->any() : $this->exactly($expectedChecks))
            ->method('isGranted')->with('ROLE_CREATOR')->willReturn($granted);

        $checker = new CrudAccessChecker(new ServiceLocator([ReportCrudController::class => fn () => new ReportCrudController()]), $authChecker);

        return new MenuBuilder(
            $registry,
            $urls,
            $this->createStub(UrlGeneratorInterface::class),
            $this->createStub(RequestStack::class),
            $this->createStub(TranslatorInterface::class),
            $authChecker,
            $this->createStub(LayoutStore::class),
            $this->createStub(LayoutArranger::class),
            null,
            [],
            $checker,
        );
    }

    public function testHidesACrudItemItsControllerWouldRefuse(): void
    {
        $section = MenuItem::section('Reports')->setSubItems([MenuItem::linkToCrud(\ArrayObject::class, 'Reports')]);
        $other = MenuItem::linkToUrl('Elsewhere', null, '/elsewhere');

        // the section left empty goes too, as for an item's own permission
        $this->assertSame([$other], $this->builderForCrud(false)->resolve([$section, $other]));
    }

    public function testKeepsACrudItemItsControllerWouldLetThrough(): void
    {
        $item = MenuItem::linkToCrud(\ArrayObject::class, 'Reports');

        $this->assertSame([$item], $this->builderForCrud(true)->resolve([$item]));
        $this->assertSame('/admin/reports', $item->getLinkUrl());
    }

    public function testAsksOnceAboutAControllerLinkedSeveralTimes(): void
    {
        $builder = $this->builderForCrud(true, 1);

        $builder->resolve([MenuItem::linkToCrud(\ArrayObject::class, 'Reports'), MenuItem::linkToCrud(\ArrayObject::class, 'New report')->setCrudAction('new')]);
        $this->assertTrue($builder->isCrudGranted(ReportCrudController::class));
    }
}
