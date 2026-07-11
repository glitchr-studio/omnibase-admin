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
        $resolved->setFormattedValue(null !== $callable ? $callable($value, $entity) : $this->formatValue($value));

        return $resolved;
    }

    /**
     * Default display formatting, done here rather than in Twig: templates
     * cannot reliably type-check values behind entity magic methods
     * (BaseTrait's __get makes any attribute look "defined").
     */
    protected function formatValue(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i');
        }

        if ($value instanceof \BackedEnum) {
            return $value->value;
        }
        if ($value instanceof \UnitEnum) {
            return $value->name;
        }

        if (\is_object($value) && !is_iterable($value)) {
            return method_exists($value, '__toString')
                ? (string) $value
                : substr(strrchr('\\' . get_class($value), '\\'), 1) . (method_exists($value, 'getId') ? ' #' . $value->getId() : '');
        }

        return $value;
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
