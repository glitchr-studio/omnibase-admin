<?php

namespace Base\Admin\Form;

use Base\Field\FieldDescriptor;
use Base\Field\FieldInterface;
use Base\Field\Type\AssociationType;
use Base\Field\Type\SelectType;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Builds a plain Symfony form from a collection of field descriptors.
 *
 * This is the whole Field -> FormType bridge: each descriptor names a real
 * Symfony FormTypeInterface FQCN (a Base\Field\Type\* or a Symfony core type)
 * plus its options; nothing else happens in between. The same field list a
 * CRUD controller yields in configureFields() can therefore be used to build
 * a form anywhere - inside the admin or in any regular controller.
 *
 * One exception: an AssociationField that points to a record existing on its
 * own (a many-to-one, a many-to-many, a user account) is built as a picker
 * (SelectType on the related class), not as that record's embedded form - see
 * addField() and getPickedClass().
 */
class FieldFormBuilder
{
    /**
     * What a picker keeps of an AssociationField's form options: the rest
     * (allow_add, fields, autoload...) describes an embedded form.
     */
    private const PICKER_OPTIONS = [
        'class', 'multiple', 'required', 'disabled', 'mapped', 'label', 'help', 'attr', 'row_attr', 'placeholder',
        'translation_domain', 'label_translation_parameters', 'help_translation_parameters',
    ];

    public function __construct(protected FormFactoryInterface $formFactory, protected ?ManagerRegistry $doctrine = null)
    {
    }

    /**
     * @param iterable<FieldInterface|FieldDescriptor> $fields
     */
    public function createFormBuilder(mixed $data, iterable $fields, string $page = FieldDescriptor::PAGE_NEW, array $formOptions = []): FormBuilderInterface
    {
        // createNamedBuilder on purpose: binding the entity itself is the
        // whole point of a CRUD form, and the named variant is the sanctioned
        // path for that (base-bundle's decorated factory guards createBuilder
        // against accidental entity data in ad-hoc forms)
        $builder = $this->formFactory->createNamedBuilder('crud_form', FormType::class, $data, $formOptions + [
            'data_class' => is_object($data) ? get_class($data) : null,
            'translation_domain' => 'forms',
        ]);

        foreach ($fields as $field) {
            $descriptor = $field instanceof FieldInterface ? $field->getAsDto() : $field;
            if (!$descriptor->isDisplayedOn($page)) {
                continue;
            }

            $this->addField($builder, $descriptor);
        }

        return $builder;
    }

    /**
     * @param iterable<FieldInterface|FieldDescriptor> $fields
     */
    public function createForm(mixed $data, iterable $fields, string $page = FieldDescriptor::PAGE_NEW, array $formOptions = []): FormInterface
    {
        return $this->createFormBuilder($data, $fields, $page, $formOptions)->getForm();
    }

    public function addField(FormBuilderInterface $builder, FieldDescriptor $descriptor): void
    {
        $formType = $descriptor->getFormType();
        $options = $this->getFormOptions($descriptor);

        if (null !== $pickedClass = $this->getPickedClass($builder, $descriptor)) {
            // AssociationType embeds the related entity's own form in the
            // record being edited. For a record that exists on its own - the
            // account an order belongs to, the journal of a bank account, the
            // venue of a concert - that is not what a back office wants: the
            // whole account (email, password, roles...) or the whole journal
            // inside another record, and it failed on what such a form cannot
            // guess (`roles`, an enum). A related record is chosen, not
            // edited from here: a picker (autocompletion), as SelectField
            // gives.
            $formType = SelectType::class;
            $options = ['class' => $pickedClass] + array_intersect_key($options, array_flip(self::PICKER_OPTIONS));
            if (null === ($options['class'] ?? null)) {
                $options['class'] = $pickedClass;
            }
        } elseif (AssociationType::class === $formType) {
            unset($options['embed']);
        }

        $builder->add($descriptor->getPropertyWithSuffix(), $formType, $options);
    }

    /**
     * The class an AssociationField points to, when its form is to be a
     * picker; null when the related record's form is embedded.
     *
     * - `->embed()` (or naming the embedded fields, `->setFields([...])`):
     *   the embedded form, the developer asked for it;
     * - `->embed(false)`: a picker, whatever the association;
     * - otherwise a picker when the related record exists on its own - a
     *   many-to-one, a many-to-many, a user account - and the embedded form
     *   when the record owns what it points to (one-to-many, one-to-one: the
     *   lines of an entry, the address of a place).
     *
     * The related class is the field's `class` option, else the Doctrine
     * association of the form's data class.
     */
    protected function getPickedClass(FormBuilderInterface $builder, FieldDescriptor $descriptor): ?string
    {
        if (AssociationType::class !== $descriptor->getFormType()) {
            return null;
        }

        $options = $descriptor->getFormTypeOptions();
        $embed = $options['embed'] ?? null;
        if (true === $embed || !empty($options['fields']) || !$this->doctrine) {
            return null;
        }

        $class = $options['class'] ?? null;
        $chosen = false;    // a many-to-one or a many-to-many
        $dataClass = $builder->getOption('data_class');
        if (\is_string($dataClass)) {
            $metadata = $this->doctrine->getManagerForClass($dataClass)?->getClassMetadata($dataClass);
            $property = $descriptor->getProperty();
            if ($metadata && $metadata->hasAssociation($property)) {
                $class ??= $metadata->getAssociationTargetClass($property);
                $mapping = $metadata->getAssociationMapping($property);
                $chosen = $mapping->isManyToOne() || $mapping->isManyToMany();
            }
        }

        if (!\is_string($class) || !class_exists($class) && !interface_exists($class)) {
            return null;
        }
        if (is_a($class, UserInterface::class, true)) {
            return $class;
        }
        if (!$chosen && false !== $embed) {
            return null;
        }

        // A picker needs an entity to look into.
        $manager = $this->doctrine->getManagerForClass($class);

        return $manager && !$manager->getMetadataFactory()->isTransient($class) ? $class : null;
    }

    /**
     * @deprecated use getPickedClass(): the picker is no longer for user accounts only
     */
    protected function getPickedUserClass(FormBuilderInterface $builder, FieldDescriptor $descriptor): ?string
    {
        $class = $this->getPickedClass($builder, $descriptor);

        return null !== $class && is_a($class, UserInterface::class, true) ? $class : null;
    }

    /**
     * Maps the descriptor's generic settings onto Symfony form options;
     * explicit formTypeOptions always win.
     */
    public function getFormOptions(FieldDescriptor $descriptor): array
    {
        $options = [];

        if (null !== $descriptor->getLabel()) {
            $options['label'] = $descriptor->getLabel();
        }
        if (null !== $descriptor->isRequired()) {
            $options['required'] = $descriptor->isRequired();
        }
        if (null !== $descriptor->isDisabled()) {
            $options['disabled'] = $descriptor->isDisabled();
        }
        if (null !== $descriptor->getEmptyData()) {
            $options['empty_data'] = $descriptor->getEmptyData();
        }
        if (null !== $descriptor->getHelp()) {
            $options['help'] = $descriptor->getHelp();
        }
        if ([] !== $descriptor->getTranslationParameters()) {
            $options['label_translation_parameters'] = $descriptor->getTranslationParameters();
        }
        if (true === $descriptor->isVirtual()) {
            $options['mapped'] = false;
        }
        if ([] !== $descriptor->getHtmlAttributes()) {
            $options['attr'] = array_merge($options['attr'] ?? [], $descriptor->getHtmlAttributes());
        }
        if (null !== $descriptor->getColumns()) {
            // Consumed by _form.html.twig as a CSS grid-column span (out of
            // 12) - lets a form reuse the same ->setColumns() metadata the
            // index/detail views were already carrying, without pulling in
            // a Bootstrap-style col-N class system.
            $options['row_attr'] = array_merge($options['row_attr'] ?? [], ['data-columns' => $descriptor->getColumns()]);
        }

        return array_replace_recursive($options, $descriptor->getFormTypeOptions());
    }
}
