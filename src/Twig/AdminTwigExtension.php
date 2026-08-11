<?php

namespace Base\Admin\Twig;

use Base\Admin\Config\Action;
use Base\Admin\Field\FieldValueResolver;
use Base\Admin\Router\AdminUrlGenerator;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AdminTwigExtension extends AbstractExtension
{
    public function __construct(
        protected readonly AdminUrlGenerator $adminUrlGenerator,
        protected readonly FieldValueResolver $fieldValueResolver,
        protected readonly ?\Base\Admin\Router\AdminRouteRegistry $routeRegistry = null,
        protected readonly ?\Base\Admin\Context\AdminContext $adminContext = null,
    ) {
    }

    /**
     * The menu item matching the page being rendered, or null.
     *
     * A function rather than a template variable because the templates that
     * need it are included with `only` - and because three of them would
     * otherwise each repeat the same two-level walk over mainMenu. Selection
     * itself is decided once, by MenuBuilder::markSelected() (longest
     * matching path prefix), so a detail or edit page resolves to the CRUD's
     * own item rather than to nothing.
     */
    public function adminMenuCurrent(): ?\Base\Admin\Config\Menu\MenuItem
    {
        foreach ($this->adminContext?->getMainMenu() ?? [] as $item) {
            if ($item->isSelected()) {
                return $item;
            }

            foreach ($item->getSubItems() as $subItem) {
                if ($subItem->isSelected()) {
                    return $subItem;
                }
            }
        }

        return null;
    }

    /**
     * An entity's own icon, from the app's IconizeInterface convention
     * (__iconizeStatic() returns a list, first entry wins).
     *
     * The header's fallback when the CRUD's menu item declares no icon of
     * its own, which is the common case for content CRUDs - the menu item
     * is generated, the entity is hand-written and usually already says
     * what it looks like (Article -> fa-newspaper).
     */
    public function adminEntityIcon(?string $entityFqcn): ?string
    {
        if (null === $entityFqcn || !class_exists($entityFqcn) || !method_exists($entityFqcn, '__iconizeStatic')) {
            return null;
        }

        $icons = $entityFqcn::__iconizeStatic();
        $icon = \is_array($icons) ? ($icons[0] ?? null) : $icons;

        return \is_string($icon) && '' !== $icon ? $icon : null;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('admin_url', $this->adminUrl(...)),
            new TwigFunction('admin_action_url', $this->adminActionUrl(...)),
            new TwigFunction('admin_display', $this->fieldValueResolver->formatValue(...)),
            // Display-mode aware variants of admin_display() - see
            // FieldValueResolver::entityLabel()/entityAvatar().
            new TwigFunction('admin_entity_label', $this->fieldValueResolver->entityLabel(...)),
            new TwigFunction('admin_entity_avatar', $this->fieldValueResolver->entityAvatar(...)),
            new TwigFunction('admin_entity_crud', $this->adminEntityCrud(...)),
            new TwigFunction('admin_entity_id', $this->fieldValueResolver->entityIdentifier(...)),
            new TwigFunction('admin_menu_current', $this->adminMenuCurrent(...)),
            new TwigFunction('admin_entity_icon', $this->adminEntityIcon(...)),
        ];
    }

    /**
     * The CRUD controller managing a related entity, so a field template can
     * link it to its own admin page without the field naming a controller.
     * Null when nothing manages that class - the templates then render plain
     * text, exactly as before.
     */
    public function adminEntityCrud(mixed $entity): ?string
    {
        if (!\is_object($entity) || null === $this->routeRegistry) {
            return null;
        }

        return $this->routeRegistry->getControllerForEntity($entity);
    }

    /**
     * An entity-row action's href: a per-entity callable url (set via
     * Action::linkToUrl(fn($entity) => ...), e.g. the "view on the live
     * site" action) takes priority, then a pre-set static linkUrl, and
     * only then the normal admin_url(controller, crudActionName) route -
     * this is the one place all three of those possibilities are resolved
     * together, since the index/detail templates only ever render one
     * href per row.
     */
    public function adminActionUrl(Action $action, object $entity, string $controllerFqcn): ?string
    {
        $url = $action->getUrl();
        if (\is_callable($url)) {
            return $url($entity);
        }
        if (\is_string($url) && '' !== $url) {
            return $url;
        }

        if (null !== $action->getLinkUrl()) {
            return $action->getLinkUrl();
        }

        $crudAction = $action->getCrudActionName() ?? $action->getName();

        // index takes no entity - passing one would only leak a stray
        // ?entityId= query param into an otherwise clean listing URL
        // Slug/uuid-first identifier, not the raw id - see
        // FieldValueResolver::entityIdentifier(). Every row action
        // (view/edit/delete/...) inherits readable URLs from here.
        return $this->adminUrl($controllerFqcn, $crudAction, 'index' === $crudAction ? null : $this->fieldValueResolver->entityIdentifier($entity));
    }

    public function adminUrl(string $controllerFqcn, string $action = 'index', mixed $entityId = null, array $parameters = []): string
    {
        $generator = $this->adminUrlGenerator
            ->setController($controllerFqcn)
            ->setAction($action);

        if (null !== $entityId) {
            $generator = $generator->setEntityId($entityId);
        }

        foreach ($parameters as $name => $value) {
            $generator = $generator->set($name, $value);
        }

        return $generator->generateUrl();
    }
}
