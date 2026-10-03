<?php

namespace Tests\Base\Admin\Widget;

use Base\Admin\Config\MenuItem;
use Base\Admin\Widget\EntityViewsWidgetType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Runs under a host application's PHPUnit (KERNEL_CLASS=App\Kernel: an
 * application's own suite, or the omnibase harness's `demo test admin`) -
 * same convention as AnalyticsTest/LinkableEntityRegistryTest, since this
 * widget's whole point is discovering real app entities.
 */
class EntityViewsWidgetTypeTest extends KernelTestCase
{
    use HostContentType;

    private EntityViewsWidgetType $type;

    protected function setUp(): void
    {
        if (!\class_exists('App\\Kernel')) {
            self::markTestSkipped('Requires the host application kernel (run via `make tests glitchr`).');
        }

        self::bootKernel();
        $this->type = static::getContainer()->get(EntityViewsWidgetType::class);
    }

    public function testNothingPickedYetReturnsAnEmptySeriesAndNoEntity(): void
    {
        $widget = MenuItem::block('entity_views');

        $vars = $this->type->getTemplateVars($widget);

        $this->assertSame([], $vars['series']);
        $this->assertNull($vars['entity']);
        $this->assertFalse($vars['allInstances']);
    }

    public function testClassesListIsExposedForThePicker(): void
    {
        $vars = $this->type->getTemplateVars(MenuItem::block('entity_views'));

        $this->assertArrayHasKey($this->contentType(), $vars['classes']);
    }

    public function testAStaleUnknownEntityClassDegradesToNothingPickedRatherThanErroring(): void
    {
        $widget = MenuItem::block('entity_views', null, null, null, [
            'entityClass' => 'App\\Entity\\ThisClassNoLongerExists',
            'entityId' => 1,
        ]);

        $vars = $this->type->getTemplateVars($widget);

        $this->assertNull($vars['entityClass']);
        $this->assertNull($vars['entity']);
        $this->assertSame([], $vars['series']);
    }

    public function testPickingARealEntityReturnsASeriesScopedToItsOwnPath(): void
    {
        $registry = static::getContainer()->get(\Base\Admin\Widget\LinkableEntityRegistry::class);
        $type = $this->contentType();
        $any = $registry->findInstances($type, 1);
        if ([] === $any) {
            self::markTestSkipped('No content rows to pick in this environment.');
        }

        $widget = MenuItem::block('entity_views', null, null, null, [
            'entityClass' => $type,
            'entityId' => $any[0]->getId(),
        ]);

        $vars = $this->type->getTemplateVars($widget);

        $this->assertNotNull($vars['entity']);
        $this->assertSame($any[0]->getId(), $vars['entity']->getId());
        $this->assertCount(14, $vars['series'], 'default $days window, one row per day');
    }

    public function testAllInstancesModeSumsEveryPublishedInstanceOfTheClass(): void
    {
        // Same guard the sibling test above already carries. Without it this
        // asserted that published articles exist, which is true of a developer
        // database and false of a freshly created one - so the deploy gate,
        // which builds its schema from scratch and loads no fixtures, failed
        // here every run while the code under test was behaving correctly: with
        // no rows, falling back to the empty-state series is the right answer.
        $registry = static::getContainer()->get(\Base\Admin\Widget\LinkableEntityRegistry::class);
        $type = $this->contentType();
        if ([] === $registry->findInstances($type, 1)) {
            self::markTestSkipped('No content rows in this environment; nothing to aggregate.');
        }

        $widget = MenuItem::block('entity_views', null, null, null, [
            'entityClass' => $type,
            'entityId' => EntityViewsWidgetType::ALL_INSTANCES,
        ]);

        try {
            $vars = $this->type->getTemplateVars($widget);
        } catch (\Symfony\Component\Config\Exception\LoaderLoadException $e) {
            // Pre-existing test-kernel gap - see
            // LinkableEntityRegistryTest::testAllPathsForAThreadSubtypeOnlyReturnsRealNonNullLinks's
            // own comment.
            self::markTestSkipped('Route generation unavailable under this test kernel (pre-existing gap): ' . $e->getMessage());
        }

        $this->assertTrue($vars['allInstances']);
        $this->assertNotSame([], $vars['series'], 'real published articles exist in this environment, so this must not fall back to the empty-state series');
    }
}
