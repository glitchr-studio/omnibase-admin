<?php

namespace Tests\Base\Admin\Twig;

use Base\Admin\Config\Action;
use Base\Admin\Config\Actions;
use Base\Field\FieldValueResolver;
use Base\Admin\Router\AdminUrlGenerator;
use Base\Admin\Twig\AdminTwigExtension;
use PHPUnit\Framework\TestCase;

class AdminActionOrderTest extends TestCase
{
    private function extension(): AdminTwigExtension
    {
        // adminActionOrder() is pure - it touches none of the collaborators,
        // so they are mocked away rather than assembled for real.
        return new AdminTwigExtension(
            $this->createMock(AdminUrlGenerator::class),
            $this->createMock(FieldValueResolver::class),
        );
    }

    /** @return string[] the code-declared order of a system page's actions */
    private function names(): array
    {
        return array_keys(Actions::new()->addSystemDefaults()->getAll(Actions::PAGE_SYSTEM));
    }

    private function sorted(array $order): array
    {
        asort($order);

        return array_keys($order);
    }

    public function testNothingStoredKeepsTheCodeOrder(): void
    {
        $this->assertSame(
            [Action::SAVE_AND_RETURN => 0, Action::SAVE_AND_CONTINUE => 1, Action::HOME => 2],
            $this->extension()->adminActionOrder($this->names())
        );
    }

    public function testAFullStoredOrderIsAppliedInFull(): void
    {
        $order = $this->extension()->adminActionOrder($this->names(), [
            Action::HOME => 0,
            Action::SAVE_AND_CONTINUE => 1,
            Action::SAVE_AND_RETURN => 2,
        ]);

        $this->assertSame([Action::HOME, Action::SAVE_AND_CONTINUE, Action::SAVE_AND_RETURN], $this->sorted($order));
    }

    public function testASingleStoredActionCannotReshuffleTheRow(): void
    {
        // Every per-action customization is stored as a child, so re-iconing
        // or hiding ONE button leaves exactly one stored position - which used
        // to read as "this one is first, the rest are unranked" and visibly
        // moved it to the front (found live on Settings).
        $order = $this->extension()->adminActionOrder($this->names(), [Action::HOME => 0]);

        $this->assertSame([Action::SAVE_AND_RETURN, Action::SAVE_AND_CONTINUE, Action::HOME], $this->sorted($order));
    }

    public function testAnActionAddedInCodeLaterKeepsItsDeclaredSlot(): void
    {
        // 'home' is the newcomer: the stored order predates it and ranks only
        // the two saves, swapped. Those swap within their own slots (0/1) and
        // 'home' stays where the code put it (2), instead of being pushed
        // around by an order that never mentioned it.
        $order = $this->extension()->adminActionOrder($this->names(), [
            Action::SAVE_AND_CONTINUE => 0,
            Action::SAVE_AND_RETURN => 1,
        ]);

        $this->assertSame([Action::SAVE_AND_CONTINUE, Action::SAVE_AND_RETURN, Action::HOME], $this->sorted($order));
    }

    public function testAStoredActionThatNoLongerExistsIsIgnored(): void
    {
        $order = $this->extension()->adminActionOrder($this->names(), ['longGone' => 0, Action::HOME => 1]);

        $this->assertSame([Action::SAVE_AND_RETURN, Action::SAVE_AND_CONTINUE, Action::HOME], $this->sorted($order));
        $this->assertArrayNotHasKey('longGone', $order);
    }

    public function testActionObjectsAreAcceptedAsWellAsNames(): void
    {
        $actions = Actions::new()->addSystemDefaults()->getAll(Actions::PAGE_SYSTEM);

        $this->assertSame(
            $this->extension()->adminActionOrder($this->names()),
            $this->extension()->adminActionOrder($actions)
        );
    }
}
