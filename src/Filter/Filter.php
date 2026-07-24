<?php

namespace Base\Admin\Filter;

use Doctrine\ORM\QueryBuilder;
use Symfony\Contracts\Translation\TranslatableInterface;

/**
 * One filter definition: which property, which comparison widget, and how
 * it constrains the index QueryBuilder. Single class - the type string
 * drives both the rendering (inline bar) and the WHERE clause.
 */
class Filter
{
    public const TYPE_TEXT = 'text';
    public const TYPE_BOOLEAN = 'boolean';
    public const TYPE_CHOICE = 'choice';
    public const TYPE_DATE = 'date';
    public const TYPE_NUMERIC = 'numeric';
    public const TYPE_ASSOCIATION = 'association';

    protected string $property;
    protected TranslatableInterface|string|null $label = null;
    protected string $type = self::TYPE_TEXT;
    /** @var array<string, string> value => label */
    protected array $choices = [];
    /** @var callable|null custom applier: fn(QueryBuilder $qb, string $alias, mixed $value) */
    protected mixed $applyCallable = null;

    public static function new(string $property, TranslatableInterface|string|null $label = null): static
    {
        $filter = new static();
        $filter->property = $property;
        $filter->label = $label;

        return $filter;
    }

    public function getProperty(): string
    {
        return $this->property;
    }

    public function getLabel(): TranslatableInterface|string|null
    {
        return $this->label;
    }

    public function setLabel(TranslatableInterface|string|null $label): static
    {
        $this->label = $label;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function asBoolean(): static
    {
        return $this->setType(self::TYPE_BOOLEAN);
    }

    public function asDate(): static
    {
        return $this->setType(self::TYPE_DATE);
    }

    public function asNumeric(): static
    {
        return $this->setType(self::TYPE_NUMERIC);
    }

    /**
     * @param array<string, string> $choices value => label
     */
    public function asChoice(array $choices): static
    {
        $this->choices = $choices;
        return $this->setType(self::TYPE_CHOICE);
    }

    /**
     * Filter by a to-one association (Destination, Author, Tag, ...) - a
     * dropdown of the related entity's real rows rather than a free-text
     * guess at an id. $entities is typically `$repository->findAll()` (or
     * a narrower query - findBy(), a custom finder, ...); each becomes one
     * choice, keyed by id, labeled with $labelCallback($entity) or the
     * entity's own __toString() if none is given. The WHERE clause itself
     * (entity.<property> = :id) is identical to a plain equality filter -
     * Doctrine resolves a scalar compared against a to-one association to
     * its join column automatically - so this is really "asChoice(), but
     * the choices come from a repository instead of a hand-written array".
     *
     * @param iterable<object> $entities
     */
    public function asAssociation(iterable $entities, ?callable $labelCallback = null): static
    {
        $choices = [];
        foreach ($entities as $entity) {
            if (!method_exists($entity, 'getId')) {
                continue;
            }
            $choices[(string) $entity->getId()] = null !== $labelCallback ? $labelCallback($entity) : (string) $entity;
        }

        $this->choices = $choices;
        return $this->setType(self::TYPE_ASSOCIATION);
    }

    public function getChoices(): array
    {
        return $this->choices;
    }

    public function applyWith(callable $applyCallable): static
    {
        $this->applyCallable = $applyCallable;
        return $this;
    }

    // comparison operators, historical URL shape:
    //   filters[prop][comparison]=like|eq|neq|gt|lt & filters[prop][value]=...
    // a bare scalar (filters[prop]=x) is still accepted and means the
    // type's default comparison, so simple links stay simple
    public const COMPARISON_LIKE = 'like';
    public const COMPARISON_EQ = 'eq';
    public const COMPARISON_NEQ = 'neq';
    public const COMPARISON_GT = 'gt';
    public const COMPARISON_LT = 'lt';

    /**
     * The comparison operators this filter's widget should offer, keyed by
     * operator, valued with a short display symbol. Empty = no chooser
     * (boolean/choice/association/date have a fixed semantic).
     *
     * @return array<string, string>
     */
    public function getComparisons(): array
    {
        return match ($this->type) {
            self::TYPE_TEXT => [self::COMPARISON_LIKE => '≈', self::COMPARISON_EQ => '=', self::COMPARISON_NEQ => '≠'],
            self::TYPE_NUMERIC => [self::COMPARISON_EQ => '=', self::COMPARISON_GT => '>', self::COMPARISON_LT => '<', self::COMPARISON_NEQ => '≠'],
            default => [],
        };
    }

    /**
     * @param mixed $value the raw request value (already checked non-empty)
     */
    public function apply(QueryBuilder $queryBuilder, string $alias, mixed $value): void
    {
        // normalize the {comparison, value} URL shape; scalars mean the
        // type's default comparison
        $comparison = null;
        if (\is_array($value) && \array_key_exists('value', $value)) {
            $comparison = $value['comparison'] ?? null;
            $value = $value['value'];
        }

        if (null !== $this->applyCallable) {
            ($this->applyCallable)($queryBuilder, $alias, $value);
            return;
        }

        $field = $alias . '.' . $this->property;
        $param = 'filter_' . str_replace('.', '_', $this->property);

        switch ($this->type) {
            case self::TYPE_BOOLEAN:
                $queryBuilder->andWhere(sprintf('%s = :%s', $field, $param))
                    ->setParameter($param, '1' === $value || 'true' === $value || true === $value);
                break;

            case self::TYPE_DATE:
                // value: ['from' => 'Y-m-d', 'to' => 'Y-m-d'], either side optional
                $from = \is_array($value) ? ($value['from'] ?? null) : null;
                $to = \is_array($value) ? ($value['to'] ?? null) : null;
                if ($from) {
                    $queryBuilder->andWhere(sprintf('%s >= :%s_from', $field, $param))
                        ->setParameter($param . '_from', new \DateTimeImmutable($from . ' 00:00:00'));
                }
                if ($to) {
                    $queryBuilder->andWhere(sprintf('%s <= :%s_to', $field, $param))
                        ->setParameter($param . '_to', new \DateTimeImmutable($to . ' 23:59:59'));
                }
                break;

            case self::TYPE_NUMERIC:
                $operator = match ($comparison) {
                    self::COMPARISON_GT => '>',
                    self::COMPARISON_LT => '<',
                    self::COMPARISON_NEQ => '!=',
                    default => '=',
                };
                $queryBuilder->andWhere(sprintf('%s %s :%s', $field, $operator, $param))
                    ->setParameter($param, $value);
                break;

            case self::TYPE_CHOICE:
            case self::TYPE_ASSOCIATION:
                $queryBuilder->andWhere(sprintf('%s = :%s', $field, $param))
                    ->setParameter($param, $value);
                break;

            default:
                if (self::COMPARISON_EQ === $comparison || self::COMPARISON_NEQ === $comparison) {
                    $queryBuilder->andWhere(sprintf('%s %s :%s', $field, self::COMPARISON_NEQ === $comparison ? '!=' : '=', $param))
                        ->setParameter($param, $value);
                    break;
                }
                $queryBuilder->andWhere(sprintf('LOWER(%s) LIKE :%s', $field, $param))
                    ->setParameter($param, '%' . mb_strtolower((string) $value) . '%');
        }
    }

    /**
     * A filter value counts as "set" when it constrains anything.
     */
    public static function isActive(mixed $value): bool
    {
        if (\is_array($value)) {
            if (\array_key_exists('value', $value)) {
                return null !== $value['value'] && '' !== trim((string) $value['value']);
            }

            return '' !== trim((string) ($value['from'] ?? '')) || '' !== trim((string) ($value['to'] ?? ''));
        }

        return null !== $value && '' !== trim((string) $value);
    }
}
