<?php

namespace Base\Admin\Controller;

use Base\Admin\Config\Menu\MenuItem;
use Base\Admin\Context\AdminContext;
use Base\Admin\Menu\MenuBuilder;
use Base\Admin\Router\AdminRouteRegistry;
use Base\Admin\Router\AdminUrlGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * The admin's mount point: renders the dashboard and owns the menu
 * configuration (before/main/after sections and the user menu).
 */
abstract class AbstractDashboardController extends AbstractController
{
    protected AdminContext $adminContext;
    protected AdminUrlGenerator $adminUrlGenerator;
    protected AdminRouteRegistry $routeRegistry;
    protected MenuBuilder $menuBuilder;

    #[Required]
    public function setAdminServices(
        AdminContext $adminContext,
        AdminUrlGenerator $adminUrlGenerator,
        AdminRouteRegistry $routeRegistry,
        MenuBuilder $menuBuilder,
    ): void {
        $this->adminContext = $adminContext;
        $this->adminUrlGenerator = $adminUrlGenerator;
        $this->routeRegistry = $routeRegistry;
        $this->menuBuilder = $menuBuilder;
    }

    public function index(): Response
    {
        $this->adminContext->setDashboardControllerFqcn(static::class);
        $this->adminContext->setMainMenu($this->resolveMenu());
        $this->adminContext->setUserMenu($this->menuBuilder->resolve($this->toArray($this->configureUserMenu())));

        return $this->render('@Admin/dashboard.html.twig', [
            'admin_context' => $this->adminContext,
            'quick_access' => $this->buildQuickAccess(),
        ]);
    }

    // -----------------------------------------------------------------
    // configuration hooks (same names as before)
    // -----------------------------------------------------------------

    /**
     * @return iterable<MenuItem>
     */
    public function configureMenuBeforeItems(): iterable
    {
        return [];
    }

    /**
     * @return iterable<MenuItem>
     */
    public function configureMenuItems(): iterable
    {
        return $this->menuBuilder->buildDefault();
    }

    /**
     * @return iterable<MenuItem>
     */
    public function configureMenuAfterItems(): iterable
    {
        return [];
    }

    /**
     * @return iterable<MenuItem>
     */
    public function configureUserMenu(): iterable
    {
        return $this->menuBuilder->buildUserMenuDefault($this->getUser());
    }

    // -----------------------------------------------------------------
    // menu / dashboard resolution
    // -----------------------------------------------------------------

    /**
     * @return MenuItem[]
     */
    protected function resolveMenu(): array
    {
        return $this->menuBuilder->resolve(array_merge(
            $this->toArray($this->configureMenuBeforeItems()),
            $this->toArray($this->configureMenuItems()),
            $this->toArray($this->configureMenuAfterItems()),
        ));
    }

    /**
     * One card per registered CRUD: label, icon and index URL.
     *
     * @return array<int, array{label: string, icon: ?string, url: string}>
     */
    protected function buildQuickAccess(): array
    {
        $cards = [];
        foreach ($this->routeRegistry->getControllers() as $fqcn => $slug) {
            if (!is_subclass_of($fqcn, CrudControllerInterface::class)) {
                continue;
            }

            $cards[] = [
                'label' => ucfirst(str_replace('-', ' ', $slug)),
                'icon' => $fqcn::getPreferredIcon(),
                'url' => $this->adminUrlGenerator->setController($fqcn)->setAction('index')->generateUrl(),
            ];
        }

        return $cards;
    }

    /**
     * @return MenuItem[]
     */
    private function toArray(iterable $items): array
    {
        return is_array($items) ? $items : iterator_to_array($items, false);
    }
}
