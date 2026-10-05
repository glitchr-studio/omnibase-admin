# CRUD fields

A CRUD controller yields its fields from `configureFields()`
(`Base\Field\*Field`, in glitchr/omnibase); `Base\Admin\Form\FieldFormBuilder`
turns each into a Symfony form field: the field's form type with its options,
nothing in between.

## A related record is chosen, not edited

`AssociationType` (glitchr/omnibase) embeds the related entity's own form in
the record being edited. For a record that exists on its own - the account an
order belongs to, the journal of a bank account, the venue of a concert - that
is not what a back office wants: the whole account (email, password, roles)
or the whole journal inside another record. It did not work either: the
embedded form failed on what it cannot guess (`roles`), and put a PHP enum of
the related record in a text input ("AccountType could not be converted to
string", a 500 on `/admin/reconciliation-rules/new`).

So `FieldFormBuilder` builds an `AssociationField` as a **picker** - omnibase's
`SelectType` on the related class, fed by the autocompletion, what
`SelectField::new('owner')` gives - when the related record exists on its
own, and keeps the embedded form for what the record owns:

| The association | The field is |
|---|---|
| many-to-one, many-to-many | a picker (single or multiple, as the association says) |
| a user account (a class implementing Symfony's `UserInterface`), whatever the association | a picker |
| one-to-many, one-to-one | the embedded form (the lines of an entry, the address of a place) |
| `->embed()`, or `->setFields([...])` with the fields to embed | the embedded form, whatever the association |
| `->embed(false)` | a picker, whatever the association |

```php
yield AssociationField::new('customer')->setColumns(4);      // many-to-one: a picker
yield AssociationField::new('owners');                       // many-to-many: a multiple picker
yield AssociationField::new('customer')->setDisabled();      // shown, not changeable
yield AssociationField::new('lines');                        // one-to-many: the lines' forms, embedded
yield AssociationField::new('lines')->embed(false);          // ...or chosen among existing lines
yield AssociationField::new('address')->embed();             // the address's own form, asked for
yield AssociationField::new('customer')->autoload(false)->setFields([
    'email' => ['form_type' => EmailType::class],            // an embedded form of the fields named
]);
```

The related class is the field's `class` option when it is set, else the
Doctrine association of the form's entity. A picker keeps the field's label,
help, columns, required and disabled settings; the options that describe an
embedded form (`allowAdd()`, `autoload()`, `showCollapsed()`...) are dropped.

`AssociationType` used by name in a form of your own
(`$builder->add('address', AssociationType::class)`) always embeds: the rule
above is the back office's, applied to an `AssociationField`.

In an embedded form, a column that holds a PHP enum is a select of its cases
(see below), no longer a text input.

Lists and detail pages are unchanged: the field is displayed by
`crud/field/association.html.twig` as before.

## A PHP enum is a select of its cases

`SelectField` (omnibase's `SelectType`) finds its choices by itself when the
property holds a PHP enum:

```php
#[ORM\Column(length: 16, enumType: CommentState::class)]    // or simply a property typed CommentState
protected CommentState $state = CommentState::PENDING;

yield SelectField::new('state');                             // pending, approved, spam, trash
```

- **Where the enum is read**: Doctrine's `enumType:` (which Doctrine also
  sets from a property typed with a backed enum), the type of the property on
  a model that is not an entity, or the field's own class:
  `SelectField::new('states')->setClass(CommentState::class)->allowMultipleChoices()`.
  An `enumType:` on a `simple_array` or `json` column is a multiple select.
- **What the record receives**: the case (`CommentState::SPAM`), or a list of
  cases. A value that names no case is refused ("The selected choice is
  invalid"), and the record keeps what it had.
- **What the select holds**: the backed value (`spam`); the case's name when
  the enum is not backed (`NO_TRUMP`).
- **The labels**, in this order: what the enum says itself when it implements
  Symfony's `TranslatableInterface`; the key
  `<short class name in snake case>.<value>` in the `enums` domain
  (`comment_state.spam` in `translations/enums.fr.yaml`); the case's name made
  readable (`NO_TRUMP`: "No trump").

A field that brings its own choices keeps them, and what it stores is what
they hold - the enum is not guessed over them:

```php
yield SelectField::new('role')->setChoices([
    '@agenda.role.soloist' => 'soloist',      // label => stored value; a label that is a
    'Guest' => 'guest',                       // translation key is translated
]);
```

(The labels of such choices are now what is shown; the stored value was
printed in their place.)

### The list and the detail page say what the form says

A cell of the list, a line of the detail page and the form's select name a
value with the same words:

- a PHP enum's case by the labels above (`admin_enum_label()`), whether the
  column is a `SelectField` or a plain `TextField`;
- a value among the field's own choices by its label, translated when it is a
  key (`admin_field_choices()` turns `['Label' => 'value']` round; groups are
  looked into, choices made by a closure are not);
- an omnibase `EnumType` (`->setEnumClass(ThreadState::class)`) by
  `trans_enum`, as before.

The cell used to print the stored value with a capital - "Pending", "Other" -
beside a form saying "À valider", "Autre". A field template of your own
gets the same words from the two functions:

```twig
{{ admin_enum_label(field.value) ?? field.formattedValue }}
```

Before this, a `SelectField` on an enum stopped the form on "No choices, or
autocomplete option, could be guessed without using data information".

## A record chosen in any repository

`SelectField::new('room')->setClass(Room::class)` - or any picker, an
`AssociationField` included - works whatever the repository of the class: one
of omnibase's (`Base\Database\Repository\ServiceEntityRepository`, asked
through `cacheById()`), or Doctrine's own (`extends
Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository`, what
`make:entity` writes), asked through `find()` and `findBy()` on the
identifier. With a Doctrine repository the form used to die on submission:
`Undefined method "cacheById"`.

## A collection of objects: `allowObject()`

`CollectionField` holds a list; each entry is built with the form type named
by `setEntryType()`. By default the entries must be scalars: the type throws
"Object data are not allowed in collection unless you mark it as so" when the
list holds objects, because an entry whose type cannot take an object would
try to print it. When the entry type has a `data_class` - one form per
object - say so:

```php
yield CollectionField::new('targets')
    ->setEntryType(TargetType::class)      // TargetType: data_class => SocialPostTarget::class
    ->allowAdd()
    ->allowDelete()
    ->allowObject()                        // the entries are objects, TargetType knows how to edit one
    ->hideOnIndex();
```

- `allowObject()` only lifts that guard (the `allow_object` option of
  omnibase's `CollectionType`); the entry type is still the one that maps an
  object to its fields.
- With `allowAdd()`, give the entry type an `empty_data` (or a constructor
  without required arguments): a new entry is created from nothing.
- The property needs an adder and a remover (`addTarget()`, `removeTarget()`)
  or a setter; with Doctrine, `cascade: ['persist']` and `orphanRemoval: true`
  on the one-to-many, so that an added entry is saved and a removed one
  deleted.
- An `AssociationField` on a one-to-many does this by itself (its inner
  collection sets `allow_object`): `CollectionField` + `allowObject()` is for
  an entry type of your own.

## The words of a CRUD page are translated once

`configureCrud()` gives its labels, page titles and helps as plain words, as
keys that name their domain, or as `TranslatableInterface`:

```php
return parent::configureCrud($crud)
    ->setEntityLabelInSingular('@agenda.admin.event.singular')
    ->setEntityLabelInPlural('@agenda.admin.event.plural')
    ->setPageTitle(Crud::PAGE_NEW, new TranslatableMessage('event.new', [], 'agenda'))
    ->setHelp(Crud::PAGE_INDEX, 'Les dates passées restent visibles.');     // plain words: as they are
```

The controller translates the keys and the translatables when it builds the
page's configuration (`AbstractCrudController::translateCrudLabels()`), so
every template prints words: the list's title printed the key itself
(`@agenda.admin.event.plural`).

## A menu entry on the application's own class

`MenuItem::linkToCrud(\App\Entity\User::class, 'Utilisateurs')` leads to the
CRUD registered for that class, else to the one of the nearest parent class:
omnibase registers its users' CRUD against `Base\Entity\User`, which the
application's `User` extends. (With no fallback the entry was printed without
an address, `href="#"`.)

## A record's address

A record is linked by the first readable identifier it **stores**, else by
its id: `/admin/articles/petit-cours`, `/admin/users/14`.

```yaml
# config/packages/admin.yaml
admin:
    url_identifier:
        fields: [slug, uuid]                  # the default, in order; []: always the id
        entities:
            App\Entity\User: [username]
```

A field counts only when it is a column of the entity: the address has to be
looked up again, and the lookup queries columns. An entity whose `getSlug()`
is computed - made from the title at each call, or read from a translation -
is linked by its id: the address made of the computed slug answered 404
(`/admin/scholars/publications/2/edit` redirected to it). To give such an
entity a readable address, store the slug (`#[Slugify]` on a column).

An address by id keeps working for every record, and a GET by an identifier
that is not the canonical one is redirected to it.
