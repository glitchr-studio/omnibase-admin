<?php

namespace Tests\Base\Admin\Router\Fixtures\Crud;

use Base\Admin\Attribute\AdminAction;
use Base\Admin\Config\Crud;
use Base\Admin\Controller\AbstractCrudController;
use Symfony\Component\HttpFoundation\Response;

/**
 * A CRUD with a permission of its own and actions of its own - the shape
 * of the marketplace screens (exchange rates and their "refresh" button).
 */
class ReportCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return \ArrayObject::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)->setEntityPermission('ROLE_CREATOR');
    }

    #[AdminAction('/refresh')]
    public function refresh(): Response
    {
        return $this->redirectToIndex();
    }

    #[AdminAction('/{entityId}/publish', methods: ['POST'])]
    public function publish(string $entityId): Response
    {
        return $this->redirectToIndex();
    }

    #[AdminAction(methods: ['GET'])]
    public function exportAll(): Response
    {
        return new Response();
    }

    public function notAnAction(): void
    {
    }
}
