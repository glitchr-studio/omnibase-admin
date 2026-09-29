<?php

namespace Base\Admin\Attribute;

/**
 * Registers a public method of a CRUD controller as one more route of that
 * CRUD, next to index/new/edit/...: AdminRouteLoader mounts it under the
 * CRUD's own slug, named like the built-ins (admin_url(controller, method)
 * finds it), and AdminActionSubscriber runs the CRUD's gate before it -
 * the entity permission, the action's own permission, a disabled action's
 * 404 and, for a method that writes, the CSRF token.
 *
 *     // configureActions()
 *     $actions->add(Actions::PAGE_INDEX, Action::new('refreshRates', 'Refresh the rates', 'fa-solid fa-rotate')
 *         ->createAsGlobalAction()
 *         ->linkToCrudAction('refreshRates')
 *         ->askConfirmation('Fetch today\'s rates now?'));
 *
 *     #[AdminAction('/refresh')]
 *     public function refreshRates(): Response
 *     {
 *         // gate and CSRF token already checked
 *         $this->rates->refresh();
 *         $this->addFlash('success', 'Rates refreshed.');
 *
 *         return $this->redirectToIndex();
 *     }
 *
 * A path holding {entityId} makes it a record's action: the method takes
 * `string $entityId` and loads it with $this->findEntity($entityId) (the
 * id, slug or uuid the admin links with). The Action is then an entity
 * action (the default type) on the index/detail/edit pages.
 *
 * A route that takes no GET (the default is POST alone) renders as a small
 * form - its CSRF token, one submit button, the Action's confirmation
 * question if any - instead of a link. The token id is tokenId($method):
 * "admin-action-<method>", posted as `_token` (or an X-CSRF-Token header).
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
final class AdminAction
{
    public const TOKEN_PREFIX = 'admin-action-';

    /**
     * @param string|null $path         relative to the CRUD's own URL, "/refresh" or "/{entityId}/publish";
     *                                  null derives "/<kebab-cased method>"
     * @param string[]    $methods      HTTP methods the route accepts
     * @param bool        $csrf         check the token before the method runs (writing methods only);
     *                                  false leaves it to the method
     * @param array       $requirements extra route requirements ({entityId} already has one)
     */
    public function __construct(
        public readonly ?string $path = null,
        public readonly array $methods = ['POST'],
        public readonly bool $csrf = true,
        public readonly array $requirements = [],
    ) {
    }

    /** @var array<string, self|null> "controller::method" => attribute */
    private static array $cache = [];

    /**
     * The attribute on $controllerFqcn::$methodName, null for any other
     * method (the built-in actions included).
     */
    public static function of(string $controllerFqcn, string $methodName): ?self
    {
        $key = $controllerFqcn . '::' . $methodName;
        if (!\array_key_exists($key, self::$cache)) {
            $attribute = null;
            if (method_exists($controllerFqcn, $methodName)) {
                $attributes = (new \ReflectionMethod($controllerFqcn, $methodName))->getAttributes(self::class);
                $attribute = [] !== $attributes ? $attributes[0]->newInstance() : null;
            }
            self::$cache[$key] = $attribute;
        }

        return self::$cache[$key];
    }

    public static function tokenId(string $methodName): string
    {
        return self::TOKEN_PREFIX . $methodName;
    }

    public function getPath(string $methodName): string
    {
        $path = $this->path ?? strtolower(preg_replace('/(?<=[a-z0-9])([A-Z])/', '-$1', $methodName));

        return '/' . ltrim($path, '/');
    }

    public function isEntityAction(): bool
    {
        return null !== $this->path && str_contains($this->path, '{entityId}');
    }

    /**
     * Whether a plain link can reach it - otherwise it renders as a form.
     */
    public function acceptsGet(): bool
    {
        return [] === $this->methods || \in_array('GET', array_map('strtoupper', $this->methods), true);
    }
}
