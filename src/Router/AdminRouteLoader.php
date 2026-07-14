<?php

namespace Base\Admin\Router;

use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Generates the conventional CRUD routes for every registered controller:
 *
 *   {prefix}/{slug}            admin_crud_{slug}_index        GET
 *   {prefix}/{slug}/new        admin_crud_{slug}_new          GET|POST
 *   {prefix}/{slug}/{entityId} admin_crud_{slug}_detail       GET
 *   {prefix}/{slug}/{entityId}/edit    ..._edit               GET|POST
 *   {prefix}/{slug}/{entityId}/delete  ..._delete             POST
 *   {prefix}/{slug}/{entityId}/toggle  ..._toggle             PATCH
 *   {prefix}/{slug}/batch      admin_crud_{slug}_batch_delete POST
 *
 * Loaded with: $routes->import('.', 'base_admin') in the app's routing config.
 */
class AdminRouteLoader extends Loader
{
    public const TYPE = 'base_admin';

    public function __construct(protected readonly AdminRouteRegistry $registry)
    {
        parent::__construct();
    }

    public function supports(mixed $resource, ?string $type = null): bool
    {
        return self::TYPE === $type;
    }

    public function load(mixed $resource, ?string $type = null): RouteCollection
    {
        $routes = new RouteCollection();
        $prefix = rtrim($this->registry->getUrlPrefix(), '/');

        foreach ($this->registry->getControllers() as $fqcn => $slug) {
            $add = function (string $action, string $path, array $methods) use ($routes, $prefix, $slug, $fqcn) {
                $routes->add(
                    $this->registry->getRouteName($fqcn, $action),
                    new Route(
                        $prefix . '/' . $slug . $path,
                        ['_controller' => $fqcn . '::' . $action],
                        ['entityId' => '[^/]+'],
                        [],
                        '',
                        [],
                        $methods
                    )
                );
            };

            $add('index', '', ['GET']);
            $add('new', '/new', ['GET', 'POST']);
            $add('batchDelete', '/batch-delete', ['POST']);
            $add('detail', '/{entityId}', ['GET']);
            $add('edit', '/{entityId}/edit', ['GET', 'POST']);
            $add('delete', '/{entityId}/delete', ['POST']);
            $add('toggle', '/{entityId}/toggle', ['PATCH']);
        }

        return $routes;
    }
}
