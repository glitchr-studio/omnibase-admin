<?php

namespace Tests\Base\Admin\Widget;

use App\Entity\Article\Article;
use Base\Admin\Config\MenuItem;
use Base\Admin\Widget\CounterWidgetType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Runs under the host app's PHPUnit (`make tests glitchr`, KERNEL_CLASS=
 * App\Kernel) - same convention as EntityViewsWidgetTypeTest, since this
 * widget's whole point is discovering real app entities too.
 */
class CounterWidgetTypeTest extends KernelTestCase
{
    private CounterWidgetType $type;

    protected function setUp(): void
    {
        if (!\class_exists('App\\Kernel')) {
            self::markTestSkipped('Requires the host application kernel (run via `make tests glitchr`).');
        }

        self::bootKernel();
        $this->type = static::getContainer()->get(CounterWidgetType::class);
    }

    public function testNothingPickedYetReturnsNullTotalAndChange(): void
    {
        $vars = $this->type->getTemplateVars(MenuItem::block('counter'));

        $this->assertNull($vars['total']);
        $this->assertNull($vars['change']);
        $this->assertNull($vars['entity']);
        $this->assertFalse($vars['allInstances']);
    }

    public function testClassesListIsExposedForThePicker(): void
    {
        $vars = $this->type->getTemplateVars(MenuItem::block('counter'));

        $this->assertArrayHasKey(Article::class, $vars['classes']);
    }

    public function testAStaleUnknownEntityClassDegradesToNothingPickedRatherThanErroring(): void
    {
        $widget = MenuItem::block('counter', null, null, null, [
            'entityClass' => 'App\\Entity\\ThisClassNoLongerExists',
            'entityId' => 1,
        ]);

        $vars = $this->type->getTemplateVars($widget);

        $this->assertNull($vars['entityClass']);
        $this->assertNull($vars['total']);
    }

    public function testPickingARealEntityReturnsANumericTotal(): void
    {
        $registry = static::getContainer()->get(\Base\Admin\Widget\LinkableEntityRegistry::class);
        $any = $registry->findInstances(Article::class, 1);
        if ([] === $any) {
            self::markTestSkipped('No Article rows to pick in this environment.');
        }

        $widget = MenuItem::block('counter', null, null, null, [
            'entityClass' => Article::class,
            'entityId' => $any[0]->getId(),
        ]);

        $vars = $this->type->getTemplateVars($widget);

        $this->assertNotNull($vars['entity']);
        $this->assertIsInt($vars['total']);
        $this->assertGreaterThanOrEqual(0, $vars['total']);
    }

    public function testAllInstancesModeSumsEveryPublishedInstanceOfTheClass(): void
    {
        // Same guard the sibling test above already carries. Without it this
        // asserted that published articles exist, which is true of a developer
        // database and false of a freshly created one - so the deploy gate,
        // which builds its schema from scratch and loads no fixtures, failed
        // here every run while the code under test was behaving correctly: with
        // no rows to sum, falling back to the empty-state total is the right
        // answer, not a regression.
        $registry = static::getContainer()->get(\Base\Admin\Widget\LinkableEntityRegistry::class);
        if ([] === $registry->findInstances(Article::class, 1)) {
            self::markTestSkipped('No Article rows in this environment; nothing to sum.');
        }

        $widget = MenuItem::block('counter', null, null, null, [
            'entityClass' => Article::class,
            'entityId' => CounterWidgetType::ALL_INSTANCES,
        ]);

        $vars = $this->type->getTemplateVars($widget);

        $this->assertTrue($vars['allInstances']);
        $this->assertNotNull($vars['total'], 'real published articles exist in this environment, so this must not fall back to the empty-state total');
    }
}
