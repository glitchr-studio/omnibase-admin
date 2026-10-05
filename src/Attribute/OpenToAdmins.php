<?php

namespace Base\Admin\Attribute;

use Base\Enum\UserRole;

/**
 * A CRUD the site's administrators write in: on the controller, it opens the
 * actions that write - new, edit, delete, batch delete and the three saves,
 * which the back office reserves to the super-admin by default
 * (Actions::$defaultPermissions) - to ROLE_ADMIN.
 *
 *     #[OpenToAdmins]
 *     class PostCrudController extends AbstractCrudController
 *
 *     #[OpenToAdmins(actions: ['publish', 'duplicate'])]    // and the screen's own #[AdminAction] methods
 *     #[OpenToAdmins(role: 'ROLE_STAFF')]                   // another role than ROLE_ADMIN
 *
 * The default does not change: a CRUD without the attribute stays the
 * super-admin's to write. A permission the CRUD sets itself in
 * configureActions() - Actions::setPermission(), Action::setPermission(),
 * allowAnyoneTo() - is kept: the attribute only stands where the blanket
 * default would have applied. A CRUD that extends an opened one is opened
 * too, unless it carries an attribute of its own.
 *
 * Who may see the CRUD at all is another matter: Crud::setEntityPermission().
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class OpenToAdmins
{
    /**
     * @param string   $role    the role that writes (held directly or through security.role_hierarchy)
     * @param string[] $actions the CRUD's own actions (#[AdminAction] method names) opened to that role as well
     */
    public function __construct(
        public readonly string $role = UserRole::ADMIN,
        public readonly array $actions = [],
    ) {
    }

    /** @var array<class-string, self|null> */
    private static array $cache = [];

    /** The attribute of a CRUD controller: its own, else its nearest parent's; null when none says so. */
    public static function of(string $controllerFqcn): ?self
    {
        if (!\array_key_exists($controllerFqcn, self::$cache)) {
            $attribute = null;
            for ($class = class_exists($controllerFqcn) ? new \ReflectionClass($controllerFqcn) : null; $class && null === $attribute; $class = $class->getParentClass() ?: null) {
                $attributes = $class->getAttributes(self::class);
                $attribute = [] !== $attributes ? $attributes[0]->newInstance() : null;
            }
            self::$cache[$controllerFqcn] = $attribute;
        }

        return self::$cache[$controllerFqcn];
    }
}
