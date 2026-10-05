# Who may write in a CRUD

A CRUD's list and detail pages are read by whoever enters the back office
(or holds the CRUD's entity permission, see below). What **writes** is the
super-admin's by default: `new`, `edit`, `delete`, the batch delete and the
three "save" buttons require `ROLE_SUPERADMIN`
(`Base\Admin\Config\Actions::$defaultPermissions`). A site's administrator
(`ROLE_ADMIN`) reads everything and changes nothing, unless the CRUD says
otherwise: its buttons are not drawn, and the address typed by hand answers
403 - `"new" requires "ROLE_SUPERADMIN"`.

That default is right for what configures the site (users, settings,
redirections) and wrong for the site's own contents: the posts of a blog, the
dates of an agenda, a scholar's publications are written by the person whose
site it is.

## `#[OpenToAdmins]`: the administrators write here

```php
use Base\Admin\Attribute\OpenToAdmins;

#[OpenToAdmins]
class PostCrudController extends AbstractCrudController
{
}
```

On the controller, the attribute opens the seven actions above to
`ROLE_ADMIN` (held directly or through `security.role_hierarchy`). Two
arguments:

```php
#[OpenToAdmins(actions: ['publish', 'duplicate'])]   // the CRUD's own #[AdminAction] methods as well
#[OpenToAdmins(role: 'ROLE_STAFF')]                  // another role than ROLE_ADMIN
```

- **The default does not move.** A CRUD without the attribute stays as it
  was; nothing is opened application-wide.
- **What the CRUD sets itself is kept.** The attribute stands only where the
  blanket default would have applied: `Actions::setPermission()`,
  `Action::setPermission()` and `allowAnyoneTo()` in `configureActions()` win.
  An opened CRUD that keeps its deletions to the super-admin:

  ```php
  #[OpenToAdmins]
  class InvoiceCrudController extends AbstractCrudController
  {
      public function configureActions(Actions $actions): Actions
      {
          return parent::configureActions($actions)->setPermission(Action::DELETE, 'ROLE_SUPERADMIN');
      }
  }
  ```

- **It is inherited.** An application's CRUD extending a bundle's opened one
  is opened too; an attribute of its own replaces the parent's.
- **A CRUD's own actions** (`#[AdminAction]`) have no permission unless one is
  given: naming them in `actions:` gives them the role, as
  `Action::setPermission()` would one by one.
- **Records, one by one.** The permission is asked again about the record
  once it is loaded (edit, delete, a record's action): a voter given the
  record as its subject may grant an administrator some records only.

`#[OpenToAdmins]` is the one way a bundle opens its screens: it replaces the
`OpenToAdminsTrait` some bundles carried a copy of
(`$this->openToAdmins(parent::configureActions($actions), 'publish')`
becomes `#[OpenToAdmins(actions: ['publish'])]`, and the override of
`configureActions()` goes when it did nothing else). What the attribute does
is `Actions::openTo($role, ...$custom)`, for a CRUD that decides in code.

## Who may see the CRUD at all

`Crud::setEntityPermission()` in `configureCrud()` closes the whole CRUD -
its pages, its menu entry, the links other screens draw to its records
(`CrudAccessChecker`) - to whoever does not hold the permission; a voter's
attribute works as well as a role:

```php
return parent::configureCrud($crud)->setEntityPermission(ComplaintVoter::READ);
```

`#[OpenToAdmins]` does not change it: an administrator writes in the CRUDs
they may see.

## Tests

`tests/Security/OpenToAdminsTest`: the default (an administrator has no
"new" button and no way to run it), an opened CRUD, another role, the CRUD's
own actions, a permission the CRUD keeps, a CRUD that extends an opened one.
