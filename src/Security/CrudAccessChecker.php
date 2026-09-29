<?php

namespace Base\Admin\Security;

use Base\Admin\Controller\AbstractCrudController;
use Psr\Container\ContainerInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Whether the current user may open a CRUD, asked from outside it: the menu,
 * the dashboard's quick access, the links other screens draw to its records.
 * Same answer AbstractCrudController::denyAccessUnlessGrantedToRun() gives
 * once a request reaches the controller - the entity permission its
 * configureCrud() declares (Crud::setEntityPermission()) - so declaring it
 * there once is enough: no menu item has to repeat the role.
 *
 * The controllers sit behind a service locator (AdminRoutePass): only the
 * ones actually asked about are built, and each answer is kept until the
 * end of the request (kernel.reset), the menu being built on every page.
 */
class CrudAccessChecker implements ResetInterface
{
    /** @var array<string, ?string> controller FQCN => entity permission */
    protected array $permissions = [];

    /** @var array<string, bool> controller FQCN => granted */
    protected array $granted = [];

    public function __construct(
        protected readonly ContainerInterface $controllers,
        protected readonly AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    /**
     * Null for a controller that declares none, or that this locator does
     * not know (nothing to enforce then, same as the controller itself).
     */
    public function getEntityPermission(string $controllerFqcn): ?string
    {
        if (!\array_key_exists($controllerFqcn, $this->permissions)) {
            $controller = $this->controllers->has($controllerFqcn) ? $this->controllers->get($controllerFqcn) : null;
            $this->permissions[$controllerFqcn] = $controller instanceof AbstractCrudController ? $controller->getEntityPermission() : null;
        }

        return $this->permissions[$controllerFqcn];
    }

    public function isGranted(string $controllerFqcn): bool
    {
        if (!\array_key_exists($controllerFqcn, $this->granted)) {
            $permission = $this->getEntityPermission($controllerFqcn);
            $this->granted[$controllerFqcn] = null === $permission || '' === $permission || $this->authorizationChecker->isGranted($permission);
        }

        return $this->granted[$controllerFqcn];
    }

    public function reset(): void
    {
        $this->permissions = [];
        $this->granted = [];
    }
}
