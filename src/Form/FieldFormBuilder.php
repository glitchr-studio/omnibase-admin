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
 * One exception: an AssociationField on a user account is built as a picker
 * (SelectType on the user class), not as the account's embedded form - see
 * addField().
 */
class FieldFormBuilder
{
    /**
     * What a user picker keeps of an AssociationField's form options: the
     * rest (allow_add, fields, autoload...) describes an embedded form.
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

        if (null !== $userClass = $this->getPickedUserClass($builder, $descriptor)) {
            // An AssociationField embeds the related entity's own form. For an
            // account that is the whole sign-up form (email, password, roles...)
            // inside the record being edited - and it failed on `roles`, whose
            // choices cannot be guessed there. A related account is chosen,
            // never edited from here: a picker (autocompletion), as
            // SelectField gives.
            $formType = SelectType::class;
            $options = ['class' => $userClass] + array_intersect_key($options, array_flip(self::PICKER_OPTIONS));
            if (null === ($options['class'] ?? null)) {
                $options['class'] = $userClass;
            }
        }

        $builder->add($descriptor->getPropertyWithSuffix(), $formType, $options);
    }

    /**
     * The user class an AssociationField points to, when its form is to be a
     * picker: the field's `class` option, else the Doctrine association of
     * the form's data class. Null for anything else - another entity, another
     * field type, or an AssociationField that names the fields it embeds
     * (->setFields([...]): the developer asked for that form).
     */
    protected function getPickedUserClass(FormBuilderInterface $builder, FieldDescriptor $descriptor): ?string
    {
        if (AssociationType::class !== $descriptor->getFormType()) {
            return null;
        }

        $options = $descriptor->getFormTypeOptions();
        if (!empty($options['fields'])) {
            return null;
        }

        $class = $options['class'] ?? null;
        $dataClass = $builder->getOption('data_class');
        if (null === $class && $this->doctrine && \is_string($dataClass)) {
            $manager = $this->doctrine->getManagerForClass($dataClass);
            $metadata = $manager?->getClassMetadata($dataClass);
            $property = $descriptor->getProperty();
            if ($metadata && $metadata->hasAssociation($property)) {
                $class = $metadata->getAssociationTargetClass($property);
            }
        }

        return \is_string($class) && is_a($class, UserInterface::class, true) ? $class : null;
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
