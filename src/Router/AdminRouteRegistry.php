<?php

namespace Base\Admin\Router;

/**
 * Maps CRUD controller FQCNs to their generated route names and URL slugs.
 * Fed by the compiler pass with every service tagged base.admin.crud_controller.
 */
class AdminRouteRegistry
{
    public const ROUTE_PREFIX = 'admin_crud_';

    /** @var array<string, string> controller FQCN => slug */
    protected array $controllers = [];

    protected ?string $dashboardControllerFqcn = null;

    /**
     * @param string[] $controllerFqcns
     * @param string[] $dashboardControllerFqcns every concrete (non-abstract)
     *                 class tagged base.admin.dashboard_controller - normally
     *                 just Base\Admin\Controller\DashboardController (the
     *                 package's zero-config default) plus, optionally, one
     *                 app-defined dashboard controller
     */
    public function __construct(array $controllerFqcns = [], array $dashboardControllerFqcns = [], protected readonly string $urlPrefix = '/admin')
    {
        // cross-bundle override: when an App\ controller and a bundle
        // controller resolve to the same slug (same entity or same entity
        // inheritance chain), the App\ one wins - the bundle controller is
        // a default the application is free to replace
        $bySlug = [];
        foreach ($controllerFqcns as $fqcn) {
            $slug = static::slugify($fqcn);
            $current = $bySlug[$slug] ?? null;
            if (null === $current || (!str_starts_with($current, 'App\\') && str_starts_with($fqcn, 'App\\'))) {
                $bySlug[$slug] = $fqcn;
            }
        }

        foreach ($bySlug as $slug => $fqcn) {
            $this->controllers[$fqcn] = $slug;
        }

        // same App\-wins preference, but there is only ever one "slot" (the
        // dashboard), not one per entity
        foreach ($dashboardControllerFqcns as $fqcn) {
            if (null === $this->dashboardControllerFqcn || (!str_starts_with($this->dashboardControllerFqcn, 'App\\') && str_starts_with($fqcn, 'App\\'))) {
                $this->dashboardControllerFqcn = $fqcn;
            }
        }
    }

    /**
     * The dashboard controller the admin should actually mount at
     * getUrlPrefix() - an App\ override if one is registered, otherwise the
     * package's own zero-config default. Null only if the app has somehow
     * disabled dashboard controller autoconfiguration entirely.
     */
    public function getDashboardControllerFqcn(): ?string
    {
        return $this->dashboardControllerFqcn;
    }

    /**
     * @return array<string, string> controller FQCN => slug
     */
    public function getControllers(): array
    {
        return $this->controllers;
    }

    public function getUrlPrefix(): string
    {
        return $this->urlPrefix;
    }

    public function getSlug(string $controllerFqcn): string
    {
        $slug = $this->controllers[$controllerFqcn] ?? null;
        if (null === $slug) {
            throw new \InvalidArgumentException(sprintf('"%s" is not a registered admin CRUD controller (tag it with base.admin.crud_controller).', $controllerFqcn));
        }

        return $slug;
    }

    public function getRouteName(string $controllerFqcn, string $action = 'index'): string
    {
        return self::ROUTE_PREFIX . str_replace('-', '_', $this->getSlug($controllerFqcn)) . '_' . $action;
    }

    /**
     * "App\Controller\Admin\Crud\Layout\WidgetCrudController" => "layout-widget".
     * The namespace segments after "Crud\" keep the slug unique across
     * App/Base variants of the same entity tree.
     */
    public static function slugify(string $controllerFqcn): string
    {
        $name = preg_replace('/CrudController$/', '', $controllerFqcn);
        $pos = strpos($name, 'Crud\\');
        $name = false !== $pos ? substr($name, $pos + 5) : substr($name, (int) strrpos($name, '\\') + 1);

        return strtolower(preg_replace('/(?<=[a-z0-9])([A-Z])/', '-$1', str_replace('\\', '-', $name)));
    }
}
