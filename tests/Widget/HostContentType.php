<?php

namespace Tests\Base\Admin\Widget;

use Base\Admin\Config\MenuItem;
use Base\Admin\Widget\CounterWidgetType;
use Base\Admin\Widget\LinkableEntityRegistry;
use Base\Entity\Thread;

/**
 * A content type of whatever application runs these widget tests - glitchr's
 * Article, the omnibase harness's forum Topic or forge Software - rather
 * than one application's class written into the tests: they ran only in
 * glitchr, and failed in every other host for want of App\Entity\Article.
 */
trait HostContentType
{
    /**
     * The first Thread subtype the picker offers; with $listed, the first one
     * the back office also has a list of (records mode links to it).
     *
     * @return class-string<Thread>
     */
    private function contentType(bool $listed = false): string
    {
        $container = static::getContainer();
        $counter = $listed ? $container->get(CounterWidgetType::class) : null;

        foreach (\array_keys($container->get(LinkableEntityRegistry::class)->getPickableClasses()) as $class) {
            if (!\is_subclass_of($class, Thread::class)) {
                continue;
            }

            if ($counter && null === $counter->getTemplateVars(MenuItem::block('counter', null, null, null, [
                'mode' => CounterWidgetType::MODE_RECORDS,
                'countClass' => $class,
            ]))['link']) {
                continue;
            }

            return $class;
        }

        self::markTestSkipped($listed
            ? 'No content type with a back-office list in this application.'
            : 'No content type (a Thread subtype) in this application.');
    }
}
