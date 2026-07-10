<?php

namespace Base\Admin\Config\Menu;

use Symfony\Contracts\Translation\TranslatableInterface;

/**
 * One menu item model for every kind of entry (crud, route, url, section,
 * submenu, dashboard, logout, exit-impersonation) — the kind is a plain
 * type string instead of eight near-identical classes wrapping one DTO.
 */
class MenuItem
{
    public const TYPE_CRUD = 'crud';
    public const TYPE_DASHBOARD = 'dashboard';
    public const TYPE_EXIT_IMPERSONATION = 'exit_impersonation';
    public const TYPE_LOGOUT = 'logout';
    public const TYPE_ROUTE = 'route';
    public const TYPE_URL = 'url';
    public const TYPE_SECTION = 'section';
    public const TYPE_SUBMENU = 'submenu';

    protected string $type;
    protected TranslatableInterface|string|null $label = null;
    protected ?string $icon = null;
    protected string $cssClass = '';
    protected ?string $permission = null;
    protected ?string $badge = null;
    protected ?string $linkTarget = null;

    protected ?string $entityFqcn = null;
    protected ?string $crudActionName = null;
    protected mixed $entityId = null;

    protected ?string $routeName = null;
    protected array $routeParameters = [];

    protected ?string $url = null;

    /** @var MenuItem[] */
    protected array $subItems = [];

    /** runtime state, resolved by the menu builder */
    protected ?string $linkUrl = null;
    protected bool $selected = false;
    protected bool $expanded = false;

    public function __construct(string $type, TranslatableInterface|string|null $label = null, ?string $icon = null)
    {
        $this->type = $type;
        $this->label = $label;
        $this->icon = $icon;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function isSection(): bool
    {
        return self::TYPE_SECTION === $this->type;
    }

    public function isSubMenu(): bool
    {
        return self::TYPE_SUBMENU === $this->type;
    }

    public function getLabel(): TranslatableInterface|string|null
    {
        return $this->label;
    }

    public function setLabel(TranslatableInterface|string|null $label): static
    {
        $this->label = $label;
        return $this;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function setIcon(?string $icon): static
    {
        $this->icon = $icon;
        return $this;
    }

    public function getCssClass(): string
    {
        return $this->cssClass;
    }

    public function setCssClass(string $cssClass): static
    {
        $this->cssClass = $cssClass;
        return $this;
    }

    public function getPermission(): ?string
    {
        return $this->permission;
    }

    public function setPermission(string $permission): static
    {
        $this->permission = $permission;
        return $this;
    }

    public function getBadge(): ?string
    {
        return $this->badge;
    }

    public function setBadge(mixed $badge): static
    {
        $this->badge = null === $badge ? null : (string) $badge;
        return $this;
    }

    public function getLinkTarget(): ?string
    {
        return $this->linkTarget;
    }

    public function setLinkTarget(string $linkTarget): static
    {
        $this->linkTarget = $linkTarget;
        return $this;
    }

    public function getEntityFqcn(): ?string
    {
        return $this->entityFqcn;
    }

    public function setEntityFqcn(?string $entityFqcn): static
    {
        $this->entityFqcn = $entityFqcn;
        return $this;
    }

    public function getCrudActionName(): ?string
    {
        return $this->crudActionName;
    }

    public function setCrudAction(?string $crudActionName): static
    {
        $this->crudActionName = $crudActionName;
        return $this;
    }

    public function getEntityId(): mixed
    {
        return $this->entityId;
    }

    public function setEntityId(mixed $entityId): static
    {
        $this->entityId = $entityId;
        return $this;
    }

    public function getRouteName(): ?string
    {
        return $this->routeName;
    }

    public function getRouteParameters(): array
    {
        return $this->routeParameters;
    }

    public function setRoute(string $routeName, array $routeParameters = []): static
    {
        $this->routeName = $routeName;
        $this->routeParameters = $routeParameters;
        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): static
    {
        $this->url = $url;
        return $this;
    }

    /**
     * @return MenuItem[]
     */
    public function getSubItems(): array
    {
        return $this->subItems;
    }

    /**
     * @param MenuItem[] $subItems
     */
    public function setSubItems(array $subItems): static
    {
        $this->subItems = $subItems;
        return $this;
    }

    public function hasSubItems(): bool
    {
        return self::TYPE_SUBMENU === $this->type && \count($this->subItems) > 0;
    }

    public function getLinkUrl(): ?string
    {
        return $this->linkUrl;
    }

    public function setLinkUrl(?string $linkUrl): static
    {
        $this->linkUrl = $linkUrl;
        return $this;
    }

    public function isSelected(): bool
    {
        return $this->selected;
    }

    public function setSelected(bool $selected): static
    {
        $this->selected = $selected;
        return $this;
    }

    public function isExpanded(): bool
    {
        return $this->expanded;
    }

    public function setExpanded(bool $expanded): static
    {
        $this->expanded = $expanded;
        return $this;
    }

    /**
     * Kept for source compatibility with the former builder/DTO split.
     */
    public function getAsDto(): static
    {
        return $this;
    }
}
