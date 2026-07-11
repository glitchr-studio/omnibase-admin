<?php

namespace Base\Admin\Menu;

use Base\Admin\Config\Menu\MenuItem;
use Base\Admin\Config\MenuItem as MenuItemFactory;
use Base\Admin\Controller\CrudControllerInterface;
use Base\Admin\Router\AdminRouteRegistry;
use Base\Admin\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Resolves menu items (URLs + selected state) and provides the default
 * menu — dashboard link plus one entry per registered CRUD controller —
 * shared by the dashboard and every CRUD page.
 */
class MenuBuilder
{
    public function __construct(
        protected readonly AdminRouteRegistry $registry,
        protected readonly AdminUrlGenerator $adminUrlGenerator,
        protected readonly UrlGeneratorInterface $urlGenerator,
        protected readonly RequestStack $requestStack,
        protected readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @return MenuItem[]
     */
    public function buildDefault(): array
    {
        $items = [MenuItemFactory::linkToDashboard($this->translator->trans('menu.dashboard', [], 'admin'), 'fa-solid fa-home')];

        foreach ($this->registry->getControllers() as $fqcn => $slug) {
            if (is_subclass_of($fqcn, CrudControllerInterface::class)) {
                $items[] = MenuItemFactory::linkToCrud($fqcn::getEntityFqcn(), ucfirst(str_replace('-', ' ', $slug)), $fqcn::getPreferredIcon());
            }
        }

        return $this->resolve($items);
    }

    /**
     * @param iterable<MenuItem> $items
     * @return MenuItem[]
     */
    public function resolve(iterable $items): array
    {
        $items = is_array($items) ? $items : iterator_to_array($items, false);
        foreach ($items as $item) {
            $this->resolveUrl($item);
        }
        $this->markSelected($items);

        return $items;
    }

    protected function resolveUrl(MenuItem $item): void
    {
        foreach ($item->getSubItems() as $subItem) {
            $this->resolveUrl($subItem);
        }

        $item->setLinkUrl(match ($item->getType()) {
            MenuItem::TYPE_CRUD => $this->generateCrudUrl($item),
            MenuItem::TYPE_ROUTE => $this->urlGenerator->generate($item->getRouteName(), $item->getRouteParameters()),
            MenuItem::TYPE_URL, MenuItem::TYPE_SUBMENU => $item->getUrl(),
            MenuItem::TYPE_DASHBOARD => $this->urlGenerator->generate('admin'),
            MenuItem::TYPE_LOGOUT => $this->urlGenerator->generate('app_logout'),
            default => null,
        });
    }

    protected function generateCrudUrl(MenuItem $item): ?string
    {
        foreach ($this->registry->getControllers() as $fqcn => $slug) {
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
     * The item whose URL is the longest prefix of the current path wins,
     * so /admin/article-tag/12/edit still highlights "Article tag" while
     * /admin only highlights the dashboard.
     *
     * @param MenuItem[] $items
     */
    protected function markSelected(array $items): void
    {
        $currentPath = $this->requestStack->getCurrentRequest()?->getPathInfo();
        if (null === $currentPath) {
            return;
        }

        $best = null;
        $bestLength = -1;

        $visit = function (array $items) use (&$visit, &$best, &$bestLength, $currentPath) {
            foreach ($items as $item) {
                $url = $item->getLinkUrl();
                if (null !== $url) {
                    $path = parse_url($url, PHP_URL_PATH) ?? $url;
                    $isPrefix = $currentPath === $path || str_starts_with($currentPath, rtrim($path, '/') . '/');
                    if ($isPrefix && \strlen($path) > $bestLength) {
                        $best = $item;
                        $bestLength = \strlen($path);
                    }
                }
                $visit($item->getSubItems());
            }
        };
        $visit($items);

        $best?->setSelected(true);
    }
}
