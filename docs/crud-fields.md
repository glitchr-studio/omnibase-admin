# CRUD fields

A CRUD controller yields its fields from `configureFields()`
(`Base\Field\*Field`, in glitchr/omnibase); `Base\Admin\Form\FieldFormBuilder`
turns each into a Symfony form field: the field's form type with its options,
nothing in between.

## A related user account is chosen, not edited

`AssociationField` embeds the related entity's own form in the record being
edited. On a user account, that is not what a back office wants (the whole
account - email, password, roles - inside an order or a licence), and it did
not work: the embedded form failed on `roles`.

So `FieldFormBuilder` builds an `AssociationField` whose target is a user
(a class implementing Symfony's `UserInterface`) as a **picker**: omnibase's
`SelectType` on that class, fed by the autocompletion - what
`SelectField::new('owner')` gives. Single or multiple follows the association.

```php
yield AssociationField::new('customer')->setColumns(4);      // a picker of users
yield AssociationField::new('owners');                       // several: a multiple picker
yield AssociationField::new('customer')->setDisabled();      // shown, not changeable
```

The target is the field's `class` option when it is set, else the Doctrine
association of the form's entity. The field keeps its label, help, columns,
required and disabled settings; the options that describe an embedded form
(`allowAdd()`, `autoload()`, `showCollapsed()`...) are dropped.

To embed a form of the account all the same, name its fields:

```php
yield AssociationField::new('customer')->autoload(false)->setFields([
    'email' => ['form_type' => EmailType::class],
]);
```

Lists and detail pages are unchanged: the field is displayed by
`crud/field/association.html.twig` as before.
