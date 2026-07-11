<?php

namespace Base\Admin\Filter;

/**
 * Filter registry for one CRUD, filled in configureFilters().
 * add() accepts a property name (type guessed later from Doctrine
 * metadata) or a fully configured Filter.
 */
class Filters
{
    /** @var array<string, Filter> property => filter */
    protected array $filters = [];

    public static function new(): static
    {
        return new static();
    }

    public function add(Filter|string $propertyOrFilter): static
    {
        $filter = \is_string($propertyOrFilter) ? Filter::new($propertyOrFilter) : $propertyOrFilter;
        $this->filters[$filter->getProperty()] = $filter;

        return $this;
    }

    public function remove(string $property): static
    {
        unset($this->filters[$property]);
        return $this;
    }

    public function get(string $property): ?Filter
    {
        return $this->filters[$property] ?? null;
    }

    /**
     * @return array<string, Filter>
     */
    public function getAll(): array
    {
        return $this->filters;
    }

    public function isEmpty(): bool
    {
        return [] === $this->filters;
    }
}
