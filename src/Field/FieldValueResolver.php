<?php

namespace Base\Admin\Field;

use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * Resolves a field's raw and formatted value for one entity instance:
 * property access, then the field's formatValue() callable if any.
 * Returns a clone so one descriptor list can serve every row of an index.
 */
class FieldValueResolver
{
    protected PropertyAccessorInterface $accessor;

    public function __construct(?PropertyAccessorInterface $accessor = null)
    {
        $this->accessor = $accessor ?? PropertyAccess::createPropertyAccessorBuilder()
            ->disableExceptionOnInvalidPropertyPath()
            ->disableExceptionOnInvalidIndex()
            ->getPropertyAccessor();
    }

    public function resolve(FieldDescriptor $descriptor, object $entity): FieldDescriptor
    {
        $resolved = clone $descriptor;

        $value = null;
        if (null !== $descriptor->getProperty() && !$descriptor->isVirtual()) {
            $value = $this->accessor->isReadable($entity, $descriptor->getProperty())
                ? $this->accessor->getValue($entity, $descriptor->getProperty())
                : null;
        }

        $resolved->setValue($value);

        $callable = $descriptor->getFormatValueCallable();
        $resolved->setFormattedValue(null !== $callable ? $callable($value, $entity) : $value);

        return $resolved;
    }

    /**
     * @param iterable<FieldInterface|FieldDescriptor> $fields
     * @return FieldDescriptor[]
     */
    public function resolveAll(iterable $fields, object $entity, ?string $page = null): array
    {
        $resolved = [];
        foreach ($fields as $field) {
            $descriptor = $field instanceof FieldInterface ? $field->getAsDto() : $field;
            if (null !== $page && !$descriptor->isDisplayedOn($page)) {
                continue;
            }
            $resolved[] = $this->resolve($descriptor, $entity);
        }

        return $resolved;
    }
}
