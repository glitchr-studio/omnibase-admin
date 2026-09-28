<?php

namespace Base\Admin\Router;

use Symfony\Component\String\Inflector\EnglishInflector;

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
     * The registry of the running kernel, for the static lookups that have
     * no container at hand (AbstractCrudController::getCrudControllerFqcn(),
     * called from form types and Twig): the controllers it knows are the
     * ones that answer, App\ overrides included, whatever their namespace.
     */
    private static ?self $current = null;

    public static function current(): ?self
    {
        return self::$current;
    }

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
        self::$current = $this;

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

    /**
     * Slugs are hierarchical now ("articles/comments"), so "/" has to be
     * folded into the route name alongside "-" - otherwise the generated
     * name carries a slash and no longer round-trips through Symfony's
     * router or through path('admin_crud_..._index').
     */
    /** @var array<string, string>|null entity FQCN => controller FQCN */
    protected ?array $byEntity = null;

    /**
     * The registered CRUD controller that manages a given entity, or null if
     * nothing does.
     *
     * This is what lets a related entity rendered in a field become a link
     * to its own admin page without every field having to name a controller
     * by hand - the association/select templates auto-resolve through here
     * and only fall back to an explicit crudControllerFqcn option when the
     * mapping is ambiguous or absent.
     *
     * Walks the PARENT CHAIN, not just the exact class, for two reasons: a
     * Doctrine proxy's class is a generated subclass of the real entity, and
     * an inheritance tree (Thread -> Article/Comment/...) is often managed
     * by one controller registered against the base class. The exact class
     * always wins over an ancestor so a subclass with its own CRUD keeps it.
     */
    public function getControllerForEntity(string|object $entity): ?string
    {
        if (null === $this->byEntity) {
            $this->byEntity = [];
            foreach ($this->controllers as $fqcn => $slug) {
                if (!is_subclass_of($fqcn, \Base\Admin\Controller\CrudControllerInterface::class)) {
                    continue;
                }

                $entityFqcn = $fqcn::getEntityFqcn();
                // First registration wins: the App\-over-bundle preference
                // was already applied when $this->controllers was built.
                $this->byEntity[$entityFqcn] ??= $fqcn;
            }
        }

        $class = \is_object($entity) ? $entity::class : $entity;

        for ($candidate = $class; false !== $candidate; $candidate = get_parent_class($candidate)) {
            if (isset($this->byEntity[$candidate])) {
                return $this->byEntity[$candidate];
            }
        }

        return null;
    }

    public function getRouteName(string $controllerFqcn, string $action = 'index'): string
    {
        return self::ROUTE_PREFIX . str_replace(['-', '/'], '_', $this->getSlug($controllerFqcn)) . '_' . $action;
    }

    /**
     * Controller FQCN => a REST-ish, hierarchical, pluralised URL slug:
     *
     *   Crud\Article\ArticleCrudController      => "articles"
     *   Crud\Article\CommentCrudController      => "articles/comments"
     *   Crud\Article\CommentReplyCrudController => "articles/comment-replies"
     *   Crud\UserCrudController                 => "users"
     *
     * Previously this flattened the namespace with "-" and left it
     * singular ("article-article", "article-comment"), which read as an
     * internal class path rather than a URL. Each namespace segment after
     * "Crud\" is now kebab-cased and pluralised, then joined with "/" so
     * the grouping shows up as real nesting.
     *
     * The consecutive-duplicate collapse is what stops "Article\Article"
     * (a group whose own main entity repeats the group name) becoming
     * "articles/articles"; it is applied on the pluralised segments so
     * "Article\Article" and any future "Foo\Foo" behave the same way.
     *
     * NOTE: the multi-segment result means one entity's collection path is
     * a PREFIX of another's ("/admin/articles" vs "/admin/articles/comments"),
     * which the loader has to order deepest-first - see AdminRouteLoader::load().
     */
    public static function slugify(string $controllerFqcn): string
    {
        $name = preg_replace('/CrudController$/', '', $controllerFqcn);
        $pos = strpos($name, 'Crud\\');
        $name = false !== $pos ? substr($name, $pos + 5) : substr($name, (int) strrpos($name, '\\') + 1);

        $segments = [];
        foreach (explode('\\', $name) as $segment) {
            if ('' === $segment) {
                continue;
            }

            $kebab = strtolower(preg_replace('/(?<=[a-z0-9])([A-Z])/', '-$1', $segment));
            $plural = static::pluralize($kebab);

            // collapse "articles/articles" => "articles"
            if ($plural === end($segments)) {
                continue;
            }

            $segments[] = $plural;
        }

        return implode('/', $segments);
    }

    /**
     * Pluralises the LAST word of a kebab-cased segment ("comment-reply" =>
     * "comment-replies"), leaving the qualifier words alone. EnglishInflector
     * can return several candidates for an ambiguous word; the first is its
     * best guess and is what we want for a URL. A word it cannot inflect
     * (or one already plural, where it returns nothing usable) is left as-is.
     */
    protected static function pluralize(string $kebab): string
    {
        $parts = explode('-', $kebab);
        $last = array_pop($parts);

        $candidates = (new EnglishInflector())->pluralize($last);
        $plural = $candidates[0] ?? $last;

        $parts[] = $plural;

        return implode('-', $parts);
    }
}
