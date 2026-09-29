<?php

namespace Base\Admin\Config;

use Symfony\Contracts\Translation\TranslatableInterface;

/**
 * One self-contained action definition: builder API and state in a single
 * class (no Config/Dto duplication). getAsDto() returns $this so code
 * written against the old two-class split keeps working.
 */
class Action
{
    // CRUD actions
    public const BATCH_DELETE = 'batchDelete';
    public const DELETE = 'delete';
    public const DETAIL = 'detail';
    public const EDIT = 'edit';
    public const INDEX = 'index';
    public const NEW = 'new';
    public const SAVE_AND_ADD_ANOTHER = 'saveAndAddAnother';
    public const SAVE_AND_CONTINUE = 'saveAndContinue';
    public const SAVE_AND_RETURN = 'saveAndReturn';

    // navigation actions
    /**
     * "Back to the dashboard" - the non-CRUD counterpart of INDEX: a system
     * page (settings, API keys) has no entity list to return to, but it does
     * have the same need for a way back out of the form it is showing.
     */
    public const HOME = 'home';
    public const GOTO_PREV = 'prev';
    public const GOTO_SEE = 'see';
    public const GOTO_NEXT = 'next';
    public const SEPARATOR = 'separator';
    public const GROUP = 'group';
    public const GOTO = 'goto';

    // types
    public const TYPE_ENTITY = 'entity';
    public const TYPE_GLOBAL = 'global';
    public const TYPE_BATCH = 'batch';

    protected string $type = self::TYPE_ENTITY;
    protected string $name;
    protected mixed $label = null;
    protected ?string $icon = null;
    protected string $cssClass = '';
    protected string $addedCssClass = '';
    protected string $htmlElement = 'a';
    protected array $htmlAttributes = [];
    protected ?string $templatePath = null;
    protected ?string $crudActionName = null;
    protected ?string $routeName = null;
    /** @var array|callable */
    protected mixed $routeParameters = [];
    /** @var string|callable|null resolved lazily, possibly per-entity */
    protected mixed $url = null;
    protected ?string $linkUrl = null;
    protected array $translationParameters = [];
    /** @var callable|null */
    protected mixed $displayCallable = null;
    protected ?string $permission = null;
    protected TranslatableInterface|string|null $confirmation = null;

    protected function __construct(string $name)
    {
        $this->name = $name;
    }

    public function __toString(): string
    {
        return $this->name;
    }

    public static function new(string $name, TranslatableInterface|string|callable|bool|null $label = null, ?string $icon = null): static
    {
        $action = new static($name);
        $action->label = $label ?? ucfirst($name);
        $action->icon = $icon;

        return $action;
    }

    public function createAsGlobalAction(): static
    {
        $this->type = self::TYPE_GLOBAL;
        return $this;
    }

    public function createAsBatchAction(): static
    {
        $this->type = self::TYPE_BATCH;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function isEntityAction(): bool
    {
        return self::TYPE_ENTITY === $this->type;
    }

    public function isGlobalAction(): bool
    {
        return self::TYPE_GLOBAL === $this->type;
    }

    public function isBatchAction(): bool
    {
        return self::TYPE_BATCH === $this->type;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLabel(): mixed
    {
        return $this->label;
    }

    public function setLabel(TranslatableInterface|string|callable|bool|null $label): static
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
        return trim($this->cssClass . ' ' . $this->addedCssClass);
    }

    public function setCssClass(string $cssClass): static
    {
        $this->cssClass = $cssClass;
        return $this;
    }

    public function addCssClass(string $cssClass): static
    {
        $this->addedCssClass = trim($this->addedCssClass . ' ' . $cssClass);
        return $this;
    }

    public function getHtmlElement(): string
    {
        return $this->htmlElement;
    }

    public function setHtmlElement(string $htmlElement): static
    {
        $this->htmlElement = $htmlElement;
        return $this;
    }

    public function getHtmlAttributes(): array
    {
        return $this->htmlAttributes;
    }

    public function setHtmlAttributes(array $attributes): static
    {
        $this->htmlAttributes = $attributes;
        return $this;
    }

    public function addHtmlAttributes(array $attributes): static
    {
        $this->htmlAttributes = array_merge($this->htmlAttributes, $attributes);
        return $this;
    }

    public function renderAsLink(bool $renderAsLink = true): static
    {
        if ($renderAsLink) {
            $this->htmlElement = 'a';
        }

        return $this;
    }

    public function renderAsButton(string $buttonType = 'submit'): static
    {
        $this->htmlElement = 'button';
        $this->htmlAttributes['type'] = $buttonType;

        return $this;
    }

    public function renderAsTooltip(): static
    {
        return $this->addHtmlAttributes(['tooltip' => true]);
    }

    public function targetBlank(): static
    {
        return $this->addHtmlAttributes(['target' => '_blank']);
    }

    public function displayAsSeparator(): static
    {
        $this->addHtmlAttributes(['class' => 'separator']);
        $this->setHtmlElement('button');

        return $this;
    }

    public function getTemplatePath(): ?string
    {
        return $this->templatePath;
    }

    public function setTemplatePath(string $templatePath): static
    {
        $this->templatePath = $templatePath;
        return $this;
    }

    public function getCrudActionName(): ?string
    {
        return $this->crudActionName;
    }

    public function linkToCrudAction(string $crudActionName): static
    {
        $this->crudActionName = $crudActionName;
        return $this;
    }

    public function getRouteName(): ?string
    {
        return $this->routeName;
    }

    public function getRouteParameters(): mixed
    {
        return $this->routeParameters;
    }

    public function linkToRoute(string $routeName, array|callable $routeParameters = []): static
    {
        $this->routeName = $routeName;
        $this->routeParameters = $routeParameters;

        return $this;
    }

    /**
     * @param string|callable $url a URL, or a callable receiving the entity instance (or null) and returning the URL
     */
    public function getUrl(): mixed
    {
        return $this->url;
    }

    public function linkToUrl(string|callable $url): static
    {
        $this->url = $url;
        return $this;
    }

    public function getLinkUrl(): ?string
    {
        return $this->linkUrl;
    }

    /**
     * Runtime-resolved URL (set by the controller/renderer once the target entity is known).
     */
    public function setLinkUrl(?string $linkUrl): static
    {
        $this->linkUrl = $linkUrl;
        return $this;
    }

    public function getTranslationParameters(): array
    {
        return $this->translationParameters;
    }

    public function setTranslationParameters(array $parameters): static
    {
        $this->translationParameters = $parameters;
        return $this;
    }

    public function getDisplayCallable(): ?callable
    {
        return $this->displayCallable;
    }

    public function displayIf(callable $callable): static
    {
        $this->displayCallable = $callable;
        return $this;
    }

    public function isDisplayed(mixed $entity = null): bool
    {
        return null === $this->displayCallable || (bool) \call_user_func($this->displayCallable, $entity);
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

    public function getConfirmation(): TranslatableInterface|string|null
    {
        return $this->confirmation;
    }

    /**
     * A question asked before the action runs (the layout's confirm
     * dialog, as for delete) - for an action rendered as a form, i.e. one
     * whose #[AdminAction] route takes no GET.
     */
    public function askConfirmation(TranslatableInterface|string|null $question): static
    {
        $this->confirmation = $question;
        return $this;
    }

    /**
     * Kept for source compatibility with the former builder/DTO split:
     * the action IS its own DTO.
     */
    public function getAsDto(): static
    {
        return $this;
    }
}
