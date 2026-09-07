<?php

namespace Base\Admin\Form;

use Base\Field\FieldDescriptor;
use Base\Field\FieldInterface;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;

/**
 * Builds a plain Symfony form from a collection of field descriptors.
 *
 * This is the whole Field -> FormType bridge: each descriptor names a real
 * Symfony FormTypeInterface FQCN (a Base\Field\Type\* or a Symfony core type)
 * plus its options; nothing else happens in between. The same field list a
 * CRUD controller yields in configureFields() can therefore be used to build
 * a form anywhere - inside the admin or in any regular controller.
 */
class FieldFormBuilder
{
    public function __construct(protected FormFactoryInterface $formFactory)
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
        $builder->add(
            $descriptor->getPropertyWithSuffix(),
            $descriptor->getFormType(),
            $this->getFormOptions($descriptor)
        );
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
