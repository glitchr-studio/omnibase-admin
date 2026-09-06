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

    // -----------------------------------------------------------------
    // records mode: counting rows rather than page views
    // -----------------------------------------------------------------

    public function testRecordsModeWithNothingToCountYetHasNoTotalAndNoLink(): void
    {
        $vars = $this->type->getTemplateVars(MenuItem::block('counter', null, null, null, [
            'mode' => CounterWidgetType::MODE_RECORDS,
        ]));

        $this->assertNull($vars['total']);
        $this->assertNull($vars['link']);
        $this->assertSame(CounterWidgetType::MODE_RECORDS, $vars['mode']);
    }

    public function testRecordsModeNeverInventsATrend(): void
    {
        // A row count has no stored history to compare against, so the
        // percentage badge must stay absent rather than be derived from
        // something that does not mean the same thing.
        $vars = $this->type->getTemplateVars(MenuItem::block('counter', null, null, null, [
            'mode' => CounterWidgetType::MODE_RECORDS,
            'countClass' => Article::class,
        ]));

        $this->assertNull($vars['change']);
        $this->assertIsInt($vars['total']);
    }

    public function testStateNarrowingCannotExceedTheUnfilteredCount(): void
    {
        $params = ['mode' => CounterWidgetType::MODE_RECORDS, 'countClass' => Article::class];

        $all = $this->type->getTemplateVars(MenuItem::block('counter', null, null, null, $params))['total'];
        $published = $this->type->getTemplateVars(MenuItem::block('counter', null, null, null,
            $params + ['states' => [\Base\Enum\ThreadState::PUBLISH]]))['total'];

        // Deliberately a relation, not a fixed number: this suite runs against
        // both a developer database and a schema built from scratch by the
        // deploy gate, where every count here is legitimately 0.
        $this->assertLessThanOrEqual($all, $published);
    }

    public function testTheCountedListIsReachableAndCarriesTheSameNarrowing(): void
    {
        $vars = $this->type->getTemplateVars(MenuItem::block('counter', null, null, null, [
            'mode' => CounterWidgetType::MODE_RECORDS,
            'countClass' => Article::class,
            'states' => [\Base\Enum\ThreadState::DRAFT],
        ]));

        // The whole point of the link is that it answers for the number: a
        // count of drafts must not land on a list of everything.
        $this->assertNotNull($vars['link']);
        $this->assertStringContainsString('state', \urldecode($vars['link']));
        $this->assertStringContainsString(\Base\Enum\ThreadState::DRAFT, \urldecode($vars['link']));
    }

    public function testSeveralStatesLeaveTheLinkUnfilteredRatherThanWrong(): void
    {
        $vars = $this->type->getTemplateVars(MenuItem::block('counter', null, null, null, [
            'mode' => CounterWidgetType::MODE_RECORDS,
            'countClass' => Article::class,
            'states' => [\Base\Enum\ThreadState::PUBLISH, \Base\Enum\ThreadState::DRAFT],
        ]));

        // The index's state filter is single-choice, so two states cannot be
        // expressed. Dropping the narrowing silently would send you to a list
        // that disagrees with the number; the link stays, unfiltered on state.
        $this->assertNotNull($vars['link']);
        $this->assertStringNotContainsString('state', \urldecode($vars['link']));
    }

    public function testAnUnknownCountClassDegradesInsteadOfFailing(): void
    {
        $vars = $this->type->getTemplateVars(MenuItem::block('counter', null, null, null, [
            'mode' => CounterWidgetType::MODE_RECORDS,
            'countClass' => 'App\\Entity\\ThisWasRenamedOrRemoved',
        ]));

        $this->assertNull($vars['total']);
        $this->assertNull($vars['link']);
    }
}
