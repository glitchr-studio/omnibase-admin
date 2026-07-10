<?php

namespace Base\Admin\Orm;

use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator as DoctrinePaginator;

/**
 * Thin pagination wrapper around Doctrine's Paginator with the counters
 * the index template needs.
 */
class Paginator implements \IteratorAggregate, \Countable
{
    protected DoctrinePaginator $paginator;
    protected int $page;
    protected int $pageSize;
    protected ?int $total = null;

    public function __construct(QueryBuilder $queryBuilder, int $page = 1, int $pageSize = 20)
    {
        $this->page = max(1, $page);
        $this->pageSize = max(1, $pageSize);

        $queryBuilder = (clone $queryBuilder)
            ->setFirstResult(($this->page - 1) * $this->pageSize)
            ->setMaxResults($this->pageSize);

        $this->paginator = new DoctrinePaginator($queryBuilder->getQuery(), true);
    }

    public function getIterator(): \Traversable
    {
        return $this->paginator->getIterator();
    }

    public function count(): int
    {
        return iterator_count($this->getIterator());
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function getPageSize(): int
    {
        return $this->pageSize;
    }

    public function getTotal(): int
    {
        return $this->total ??= \count($this->paginator);
    }

    public function getPageCount(): int
    {
        return max(1, (int) ceil($this->getTotal() / $this->pageSize));
    }

    public function hasPreviousPage(): bool
    {
        return $this->page > 1;
    }

    public function hasNextPage(): bool
    {
        return $this->page < $this->getPageCount();
    }

    public function getPreviousPage(): int
    {
        return max(1, $this->page - 1);
    }

    public function getNextPage(): int
    {
        return min($this->getPageCount(), $this->page + 1);
    }
}
