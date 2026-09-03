<?php

namespace Tests\Base\Admin\Config;

use Base\Admin\Config\Action;
use Base\Admin\Config\Actions;
use PHPUnit\Framework\TestCase;

class ActionsTest extends TestCase
{
    public function testSystemDefaultsMirrorAnEntityEditPage(): void
    {
        $actions = Actions::new()->addSystemDefaults()->getAll(Actions::PAGE_SYSTEM);

        $this->assertSame(
            [Action::SAVE_AND_RETURN, Action::SAVE_AND_CONTINUE, Action::HOME],
            array_keys($actions)
        );
    }

    public function testBothSavesSubmitTheFormAndCarryTheirOwnSubmitAction(): void
    {
        $actions = Actions::new()->addSystemDefaults()->getAll(Actions::PAGE_SYSTEM);

        foreach ([Action::SAVE_AND_RETURN, Action::SAVE_AND_CONTINUE] as $name) {
            $this->assertSame('button', $actions[$name]->getHtmlElement(), $name);
            // The template reads these to name the submit button, which is
            // what SystemController::redirectAfterSubmit() then dispatches on.
            $this->assertSame(
                ['name' => 'submit_action', 'value' => $name, 'type' => 'submit'],
                $actions[$name]->getHtmlAttributes(),
                $name
            );
        }
    }

    public function testTheWayOutIsALinkToTheDashboard(): void
    {
        $home = Actions::new()->addSystemDefaults()->getAll(Actions::PAGE_SYSTEM)[Action::HOME];

        $this->assertSame('a', $home->getHtmlElement());
        $this->assertSame('admin', $home->getRouteName());
    }

    public function testTheSaveIsTheRowsOnlyKeyCap(): void
    {
        $actions = Actions::new()->addSystemDefaults()->getAll(Actions::PAGE_SYSTEM);

        // The templates single out the primary button by this class alone
        // (see page/_actions.html.twig's isMainSave) - exactly as the CRUD
        // form template does.
        $this->assertStringContainsString('action-saveAndReturn', $actions[Action::SAVE_AND_RETURN]->getCssClass());
        $this->assertStringNotContainsString('action-saveAndReturn', $actions[Action::SAVE_AND_CONTINUE]->getCssClass());
    }
}
