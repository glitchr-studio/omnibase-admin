<?php

namespace Base\Admin\Router;

use Base\Routing\AdminUrlGeneratorInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Fluent builder for admin URLs on top of the generated per-controller
 * routes (admin_crud_<slug>_<action>); no runtime dispatch, no query-string
 * controller FQCNs.
 */
class AdminUrlGenerator implements AdminUrlGeneratorInterface
{
    protected ?string $controllerFqcn = null;
    protected ?string $action = null;
    protected mixed $entityId = null;
    protected array $routeParameters = [];

    public function __construct(
        protected readonly UrlGeneratorInterface $urlGenerator,
        protected readonly AdminRouteRegistry $routes,
    ) {
    }

    public function setController(string $controllerFqcn): static
    {
        $clone = $this->cloneIfBuilt();
        $clone->controllerFqcn = $controllerFqcn;

        return $clone;
    }

    public function setAction(string $action): static
    {
        $clone = $this->cloneIfBuilt();
        $clone->action = $action;

        return $clone;
    }

    public function setEntityId(mixed $entityId): static
    {
        $clone = $this->cloneIfBuilt();
        $clone->entityId = $entityId;

        return $clone;
    }

    public function set(string $paramName, mixed $paramValue): static
    {
        $clone = $this->cloneIfBuilt();
        $clone->routeParameters[$paramName] = $paramValue;

        return $clone;
    }

    public function unset(string $paramName): static
    {
        $clone = $this->cloneIfBuilt();
        unset($clone->routeParameters[$paramName]);

        return $clone;
    }

    public function unsetAll(): static
    {
        $clone = $this->cloneIfBuilt();
        $clone->controllerFqcn = null;
        $clone->action = null;
        $clone->entityId = null;
        $clone->routeParameters = [];

        return $clone;
    }

    public function generateUrl(int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH): string
    {
        $routeName = $this->routes->getRouteName($this->controllerFqcn, $this->action ?? 'index');

        $parameters = $this->routeParameters;
        if (null !== $this->entityId) {
            $parameters['entityId'] = $this->entityId;
        }

        return $this->urlGenerator->generate($routeName, $parameters, $referenceType);
    }

    /**
     * Each generator starts from the injected service; the first mutation
     * clones so a partially-built URL never leaks between call sites.
     */
    protected bool $built = false;

    protected function cloneIfBuilt(): static
    {
        if ($this->built) {
            return $this;
        }

        $clone = clone $this;
        $clone->built = true;

        return $clone;
    }
}
