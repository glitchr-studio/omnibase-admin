<?php

namespace Base\Admin\Context;

use Base\Admin\Config\Actions;
use Base\Admin\Config\Crud;
use Base\Admin\Config\Menu\MenuItem;
use Symfony\Component\HttpFoundation\Request;

/**
 * Request-scoped state of the admin: which controller/action is running,
 * its Crud/Actions configuration and the resolved menus. Built by the
 * controllers themselves (no factory/reflection dance) and read by Twig
 * through the "admin" global.
 */
class AdminContext
{
    protected ?Request $request = null;

    protected ?string $dashboardControllerFqcn = null;
    protected ?string $crudControllerFqcn = null;

    protected ?Crud $crud = null;
    protected ?Actions $actions = null;

    /** @var MenuItem[] */
    protected array $mainMenu = [];
    /** @var MenuItem[] */
    protected array $userMenu = [];

    protected mixed $entity = null;
    protected iterable $entities = [];

    protected array $assets = [];

    public function getRequest(): ?Request
    {
        return $this->request;
    }

    public function setRequest(?Request $request): static
    {
        $this->request = $request;
        return $this;
    }

    public function getReferrer(): ?string
    {
        return $this->request?->query->get('referrer');
    }

    public function getDashboardControllerFqcn(): ?string
    {
        return $this->dashboardControllerFqcn;
    }

    public function setDashboardControllerFqcn(?string $fqcn): static
    {
        $this->dashboardControllerFqcn = $fqcn;
        return $this;
    }

    public function getCrudControllerFqcn(): ?string
    {
        return $this->crudControllerFqcn;
    }

    public function setCrudControllerFqcn(?string $fqcn): static
    {
        $this->crudControllerFqcn = $fqcn;
        return $this;
    }

    public function getCrud(): ?Crud
    {
        return $this->crud;
    }

    public function setCrud(?Crud $crud): static
    {
        $this->crud = $crud;
        return $this;
    }

    public function getActions(): ?Actions
    {
        return $this->actions;
    }

    public function setActions(?Actions $actions): static
    {
        $this->actions = $actions;
        return $this;
    }

    /**
     * @return MenuItem[]
     */
    public function getMainMenu(): array
    {
        return $this->mainMenu;
    }

    /**
     * @param MenuItem[] $mainMenu
     */
    public function setMainMenu(array $mainMenu): static
    {
        $this->mainMenu = $mainMenu;
        return $this;
    }

    /**
     * @return MenuItem[]
     */
    public function getUserMenu(): array
    {
        return $this->userMenu;
    }

    /**
     * @param MenuItem[] $userMenu
     */
    public function setUserMenu(array $userMenu): static
    {
        $this->userMenu = $userMenu;
        return $this;
    }

    public function getEntity(): mixed
    {
        return $this->entity;
    }

    public function setEntity(mixed $entity): static
    {
        $this->entity = $entity;
        return $this;
    }

    public function getEntities(): iterable
    {
        return $this->entities;
    }

    public function setEntities(iterable $entities): static
    {
        $this->entities = $entities;
        return $this;
    }

    public function getAssets(): array
    {
        return $this->assets;
    }

    public function setAssets(array $assets): static
    {
        $this->assets = $assets;
        return $this;
    }
}
