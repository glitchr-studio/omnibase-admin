<?php

namespace Base\Admin\Controller;

use Base\Admin\Config\Menu\MenuItem;
use Base\Admin\Config\MenuItem as MenuItemFactory;
use Base\Admin\Context\AdminContext;
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

    #[Required]
    public function setAdminServices(
        AdminContext $adminContext,
        AdminUrlGenerator $adminUrlGenerator,
        AdminRouteRegistry $routeRegistry,
    ): void {
        $this->adminContext = $adminContext;
        $this->adminUrlGenerator = $adminUrlGenerator;
        $this->routeRegistry = $routeRegistry;
    }

    public function index(): Response
    {
        $this->adminContext->setDashboardControllerFqcn(static::class);
        $this->adminContext->setMainMenu($this->resolveMenu());

        return $this->render('@Admin/dashboard.html.twig', [
            'admin_context' => $this->adminContext,
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
        foreach ($this->routeRegistry->getControllers() as $fqcn => $slug) {
            if (is_subclass_of($fqcn, CrudControllerInterface::class)) {
                yield MenuItemFactory::linkToCrud($fqcn::getEntityFqcn(), ucfirst(str_replace('-', ' ', $slug)));
            }
        }
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
        return [];
    }

    // -----------------------------------------------------------------
    // menu resolution
    // -----------------------------------------------------------------

    /**
     * @return MenuItem[]
     */
    protected function resolveMenu(): array
    {
        $items = array_merge(
            $this->toArray($this->configureMenuBeforeItems()),
            $this->toArray($this->configureMenuItems()),
            $this->toArray($this->configureMenuAfterItems()),
        );

        foreach ($items as $item) {
            $this->resolveMenuItemUrl($item);
        }

        return $items;
    }

    protected function resolveMenuItemUrl(MenuItem $item): void
    {
        foreach ($item->getSubItems() as $subItem) {
            $this->resolveMenuItemUrl($subItem);
        }

        $item->setLinkUrl(match ($item->getType()) {
            MenuItem::TYPE_CRUD => $this->generateCrudUrl($item),
            MenuItem::TYPE_ROUTE => $this->generateUrl($item->getRouteName(), $item->getRouteParameters()),
            MenuItem::TYPE_URL, MenuItem::TYPE_SUBMENU => $item->getUrl(),
            MenuItem::TYPE_DASHBOARD => $this->generateUrl('admin'),
            MenuItem::TYPE_LOGOUT => $this->generateUrl('app_logout'),
            default => null,
        });
    }

    protected function generateCrudUrl(MenuItem $item): ?string
    {
        foreach ($this->routeRegistry->getControllers() as $fqcn => $slug) {
            if (is_subclass_of($fqcn, CrudControllerInterface::class) && $fqcn::getEntityFqcn() === $item->getEntityFqcn()) {
                return $this->adminUrlGenerator
                    ->setController($fqcn)
                    ->setAction($item->getCrudActionName() ?? 'index')
                    ->generateUrl();
            }
        }

        return null;
    }

    /**
     * @return MenuItem[]
     */
    private function toArray(iterable $items): array
    {
        return is_array($items) ? $items : iterator_to_array($items, false);
    }
}
