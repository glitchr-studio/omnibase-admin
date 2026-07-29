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
    /** References a registered DashboardWidgetTypeInterface by name - no URL. See Base\Admin\Widget\DashboardWidgetTypeRegistry. */
    public const TYPE_BLOCK = 'block';

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

    protected ?string $blockName = null;

    /**
     * Instance-specific config for a block-type widget (e.g. which metric/
     * window a given analytics_card instance shows) - passed straight
     * through to DashboardWidgetTypeInterface::getTemplateVars(). Not
     * persisted by LayoutConfig: this is code-defined content, same
     * category as label/icon, not superadmin-customizable state.
     */
    protected array $params = [];

    /** @var MenuItem[] */
    protected array $subItems = [];

    /** runtime state, resolved by the menu builder */
    protected ?string $linkUrl = null;
    protected bool $selected = false;
    protected bool $expanded = false;

    protected ?string $key = null;
    /** runtime state, set by the layout arranger - hidden items still render (greyed, in customize mode) so they can be un-hidden */
    protected bool $hidden = false;

    /**
     * True when this item has no code-defined counterpart - it exists
     * purely because a superadmin added it via the dashboard widget
     * palette. Set only by LayoutArranger's synthesis pass or
     * DashboardWidgetController's new-instance endpoint, never by app
     * code. Needed because blockName alone can't distinguish an ad-hoc
     * widget from a code-defined one (e.g. the built-in analytics_card
     * also has a non-null blockName).
     */
    protected bool $adHoc = false;

    /** Grid column span for dashboard widgets (1-3). Ignored by sidebar items. */
    protected int $size = 1;

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

    public function setPermission(?string $permission): static
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

    public function getBlockName(): ?string
    {
        return $this->blockName;
    }

    public function setBlockName(?string $blockName): static
    {
        $this->blockName = $blockName;
        return $this;
    }

    public function getParams(): array
    {
        return $this->params;
    }

    public function setParams(array $params): static
    {
        $this->params = $params;
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

    public function setKey(?string $key): static
    {
        $this->key = $key;
        return $this;
    }

    /**
     * Stable identity for the persisted layout config (see the customizable
     * dashboard/sidebar feature). An explicit key wins; otherwise a hash of
     * the item's structural identity - deliberately NOT positional
     * (inserting an item elsewhere mustn't renumber everyone else's stored
     * config) and NOT the raw translated string (translation keys are
     * locale-stable; routeParameters is included because e.g. a
     * per-role sidebar shortcut differs from its siblings only by a query
     * parameter).
     */
    public function getKey(): string
    {
        if (null !== $this->key) {
            return $this->key;
        }

        $label = $this->label;
        if ($label instanceof TranslatableInterface) {
            $label = method_exists($label, 'getMessage') ? $label->getMessage() : $label::class;
        }

        return 'auto.' . substr(sha1(implode('|', [
            $this->type,
            $this->entityFqcn ?? '',
            $this->crudActionName ?? '',
            $this->routeName ?? '',
            json_encode($this->routeParameters),
            $this->url ?? '',
            \is_string($label) ? $label : '',
        ])), 0, 12);
    }

    public function isHidden(): bool
    {
        return $this->hidden;
    }

    public function setHidden(bool $hidden): static
    {
        $this->hidden = $hidden;
        return $this;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function setSize(int $size): static
    {
        $this->size = max(1, min(3, $size));
        return $this;
    }

    public function isAdHoc(): bool
    {
        return $this->adHoc;
    }

    public function setAdHoc(bool $adHoc): static
    {
        $this->adHoc = $adHoc;
        return $this;
    }
}
