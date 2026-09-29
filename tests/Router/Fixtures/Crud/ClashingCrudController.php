<?php

namespace Tests\Base\Admin\Router\Fixtures\Crud;

use Base\Admin\Attribute\AdminAction;
use Base\Admin\Controller\AbstractCrudController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tries to re-declare a built-in action through #[AdminAction].
 */
class ClashingCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return \SplObjectStorage::class;
    }

    #[AdminAction('/{entityId}/toggle', methods: ['PATCH'])]
    public function toggle(Request $request, string $entityId): Response
    {
        return parent::toggle($request, $entityId);
    }
}
