<?php

namespace Base\Admin\Twig;

use Base\Admin\Config\Action;
use Base\Field\FieldValueResolver;
use Base\Admin\Router\AdminUrlGenerator;
use Base\Form\Common\NativeEnum;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AdminTwigExtension extends AbstractExtension
{
    public function __construct(
        protected readonly AdminUrlGenerator $adminUrlGenerator,
        protected readonly FieldValueResolver $fieldValueResolver,
        protected readonly ?\Base\Admin\Router\AdminRouteRegistry $routeRegistry = null,
        protected readonly ?\Base\Admin\Context\AdminContext $adminContext = null,
        protected readonly ?\Base\Admin\Security\CrudAccessChecker $crudAccessChecker = null,
        protected readonly ?\Symfony\Component\Routing\Generator\UrlGeneratorInterface $router = null,
        protected readonly ?\Closure $menuBuilder = null, // MenuBuilder, built when a page first asks (a service closure)
        protected readonly ?\Symfony\Bundle\SecurityBundle\Security $security = null,
    ) {
    }

    /**
     * The back office's context with its menus, for `@Admin/layout.html.twig`:
     * the sidebar (the dashboard's whole menu, MenuBuilder::buildDefault())
     * and the account menu, built here when the page's controller did not.
     *
     * A screen of an application or a bundle that extends the layout from a
     * plain controller - routed outside the CRUD loader, rendering without
     * `admin_context` - had an empty sidebar; the CRUD and system pages, and
     * the screens that seed the context themselves, are unchanged.
     */
    public function adminContext(): ?\Base\Admin\Context\AdminContext
    {
        if (null === $this->adminContext || null === $this->menuBuilder) {
            return $this->adminContext;
        }

        /** @var \Base\Admin\Menu\MenuBuilder $menuBuilder */
        $menuBuilder = ($this->menuBuilder)();
        // The error page extends the layout too: a menu that cannot be built
        // leaves the sidebar empty, it does not take the error page down.
        try {
            if ([] === $this->adminContext->getMainMenu()) {
                $this->adminContext->setMainMenu($menuBuilder->buildDefault());
            }
            if ([] === $this->adminContext->getUserMenu() && null !== ($user = $this->security?->getUser())) {
                $this->adminContext->setUserMenu($menuBuilder->buildUserMenuDefault($user));
            }
        } catch (\Throwable) {
        }

        return $this->adminContext;
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
    /**
     * Accepts a class-string OR an entity instance. Templates iterating a
     * collection hold objects, not class names, and the string-only signature
     * turned that into a TypeError (a 500 on every index rendering an
     * icon chip) rather than a missing icon. Sibling helpers here already take
     * `mixed $entity`, so this now matches them.
     */
    public function adminEntityIcon(object|string|null $entityFqcn): ?string
    {
        if (\is_object($entityFqcn)) {
            // An INSTANCE gets its own icon first. __iconizeStatic() is the
            // class-level fallback, so resolving straight to it rendered the
            // generic "tag" glyph for every Tag instead of the per-record icon
            // each one actually defines - production shows those, beta showed
            // one identical icon down the whole column.
            if (method_exists($entityFqcn, '__iconize')) {
                $own = $entityFqcn->__iconize();
                $own = \is_array($own) ? ($own[0] ?? null) : $own;

                if (\is_string($own) && '' !== $own) {
                    return $own;
                }
            }

            $entityFqcn = $entityFqcn::class;
        }

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
            // The RAW storage path of an entity's image, for templates that
            // then size it themselves: admin_entity_image(photo)|thumbnail(96, 96).
            new TwigFunction('admin_entity_image', $this->fieldValueResolver->entityImage(...)),
            new TwigFunction('admin_entity_crud', $this->adminEntityCrud(...)),
            new TwigFunction('admin_crud_granted', $this->adminCrudGranted(...)),
            new TwigFunction('admin_action_post', $this->adminActionPost(...)),
            new TwigFunction('admin_action_token_id', \Base\Admin\Attribute\AdminAction::tokenId(...)),
            new TwigFunction('admin_entity_id', $this->fieldValueResolver->entityIdentifier(...)),
            new TwigFunction('admin_menu_current', $this->adminMenuCurrent(...)),
            new TwigFunction('admin_context', $this->adminContext(...)),
            new TwigFunction('admin_entity_icon', $this->adminEntityIcon(...)),
            new TwigFunction('admin_action_order', $this->adminActionOrder(...)),
            new TwigFunction('admin_enum_label', $this->adminEnumLabel(...), ['needs_environment' => true]),
            new TwigFunction('admin_field_choices', $this->adminFieldChoices(...)),
        ];
    }

    /**
     * The choices a field brings itself - SelectField::setChoices(['Label'
     * => 'value']), Symfony's shape, groups nested - turned round for a list
     * or a detail cell: what the record stores => its label, which the
     * template translates as the form does ('@agenda.role.soloist'). The
     * cell printed the stored value with a capital ("Other") where the
     * form's select said "Autre".
     *
     * Choices made by a closure are the form's alone: an empty map.
     *
     * @return array<string, string>
     */
    public function adminFieldChoices(mixed $field): array
    {
        $choices = \is_object($field) && method_exists($field, 'getFormTypeOption') ? $field->getFormTypeOption('choices') : null;
        if (!\is_array($choices)) {
            return [];
        }

        $labels = [];
        $walk = static function (array $choices) use (&$walk, &$labels): void {
            foreach ($choices as $label => $stored) {
                if (\is_array($stored)) {
                    $walk($stored);
                    continue;
                }
                if ($stored instanceof \UnitEnum) {
                    $stored = NativeEnum::id($stored);
                }
                if (\is_scalar($stored) && \is_string($label) && '' !== $label) {
                    $labels[(string) $stored] ??= $label;
                }
            }
        };
        $walk($choices);

        return $labels;
    }

    /**
     * The words a list or a detail page prints for a PHP enum's case: the
     * ones its select shows in the form (Base\Form\Common\NativeEnum::label():
     * what the enum says itself when it is translatable, else its key in the
     * "enums" domain, else its name made readable). The cell printed the
     * stored value with a capital - "Pending" beside a form saying "En
     * attente".
     *
     * $value is a case, or what a column stores of one when $enumClass names
     * the enum. Null for anything else: the template keeps its own label
     * (a field's choices, an omnibase EnumType through trans_enum).
     */
    public function adminEnumLabel(Environment $twig, mixed $value, ?string $enumClass = null): ?string
    {
        if (!$value instanceof \UnitEnum) {
            $value = NativeEnum::is($enumClass) ? NativeEnum::of($enumClass, $value) : null;
        }
        if (null === $value) {
            return null;
        }

        // The translator the templates' |trans uses, whatever the host
        // application wires behind it.
        $translator = $twig->hasExtension(TranslationExtension::class) ? $twig->getExtension(TranslationExtension::class)->getTranslator() : null;

        return NativeEnum::label($value, $translator);
    }

    /**
     * The final left-to-right order of a page's action buttons: the order
     * declared in code, with the superadmin's stored order applied on top.
     *
     * A PERMUTATION OF THE SLOTS the stored actions already occupy, not a
     * "stored first, unstored last" sort. Stored children are how EVERY
     * per-action customization is persisted (icon, visibility, order alike -
     * see LayoutController::quickSave()), so a page where the superadmin has
     * only ever re-iconed ONE button has exactly one child stored, and a
     * naive sort read that as "this button is first, everything else is
     * unranked" - re-iconing or hiding a single button visibly reshuffled
     * the whole row (found live: hiding "Enregistrer et continuer" on
     * Settings moved it to the front). Restricting the permutation to the
     * slots those actions already hold means a partial record can only
     * reorder the buttons it actually mentions, and an action added in code
     * later keeps its declared position instead of being pushed to the end.
     *
     * @param iterable<Action|string> $actions   the page's actions, in code order
     * @param array<string, int>      $stored    action name => stored position
     *
     * @return array<string, int> action name => final position
     */
    public function adminActionOrder(iterable $actions, array $stored = []): array
    {
        $names = [];
        foreach ($actions as $action) {
            $names[] = \is_string($action) ? $action : $action->getName();
        }

        $order = [];
        $slots = [];
        $ranked = [];

        foreach ($names as $slot => $name) {
            $order[$name] = $slot;

            if (\array_key_exists($name, $stored) && \is_int($stored[$name])) {
                $slots[] = $slot;
                $ranked[$name] = $stored[$name];
            }
        }

        // asort keeps the association while sorting by the stored position,
        // so the ranked names come out in the order the superadmin dragged
        // them into - which is then poured back into their own slots.
        asort($ranked);

        foreach (array_keys($ranked) as $i => $name) {
            $order[$name] = $slots[$i];
        }

        return $order;
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
     * Whether the current user may open this CRUD (its entity permission,
     * see CrudAccessChecker) - the field templates link a related record
     * only then, and print it as plain text otherwise. admin_entity_crud()
     * itself stays a pure lookup: which CRUD manages a class decides how
     * the value is displayed, not only whether it links.
     */
    public function adminCrudGranted(?string $controllerFqcn): bool
    {
        return null !== $controllerFqcn && ($this->crudAccessChecker?->isGranted($controllerFqcn) ?? true);
    }

    /**
     * Whether the action runs an #[AdminAction] route that takes no GET: the
     * templates render it as a form posting its CSRF token
     * (@Admin/crud/_action_form.html.twig) rather than as a link the route
     * would refuse. False for every built-in action and for any action
     * pointing at a URL or a route of its own.
     */
    public function adminActionPost(Action $action, string $controllerFqcn): bool
    {
        $crudAction = $action->getCrudActionName();
        if (null === $crudAction || null !== $action->getUrl() || null !== $action->getRouteName() || null !== $action->getLinkUrl()) {
            return false;
        }

        $adminAction = \Base\Admin\Attribute\AdminAction::of($controllerFqcn, $crudAction);

        return null !== $adminAction && !$adminAction->acceptsGet();
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

        // linkToRoute(): a route of its own, outside the CRUD (a bundle's
        // action controller), its parameters possibly made from the row.
        // Without this the link went to the CRUD's action of the same name.
        if (null !== $action->getRouteName() && null !== $this->router) {
            $parameters = $action->getRouteParameters();

            return $this->router->generate($action->getRouteName(), \is_callable($parameters) ? $parameters($entity) : (array) $parameters);
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
