<?php

namespace Base\Admin\Controller;

/**
 * Marker interface: implementing it (via AbstractCrudController) tags the
 * service base.admin.crud_controller, which registers its routes and menus.
 */
interface CrudControllerInterface
{
    public static function getEntityFqcn(): string;
}
