<?php

namespace Base\Admin\Config;

use Symfony\Contracts\Translation\TranslatableInterface;

/**
 * Per-controller CRUD configuration: one class holding its own state
 * (no CrudDto behind it). Only the options the controllers actually use;
 * grows on demand.
 */
class Crud
{
    public const PAGE_INDEX = 'index';
    public const PAGE_DETAIL = 'detail';
    public const PAGE_EDIT = 'edit';
    public const PAGE_NEW = 'new';

    protected ?string $entityFqcn = null;
    protected TranslatableInterface|string|null $entityLabelInSingular = null;
    protected TranslatableInterface|string|null $entityLabelInPlural = null;

    /** @var array<string, TranslatableInterface|string> page => title */
    protected array $pageTitles = [];
    /** @var array<string, TranslatableInterface|string> page => help */
    protected array $helpMessages = [];

    protected array $defaultSort = [];
    protected int $paginatorPageSize = 20;
    protected array $searchFields = [];
    protected bool $entityActionsAsDropdown = false;

    protected array $newFormOptions = [];
    protected array $editFormOptions = [];

    protected ?string $currentAction = null;
    protected ?string $currentPage = null;

    public static function new(): static
    {
        return new static();
    }

    public function getEntityFqcn(): ?string
    {
        return $this->entityFqcn;
    }

    public function setEntityFqcn(?string $entityFqcn): static
    {
        $this->entityFqcn = $entityFqcn;
        return $this;
    }

    public function getEntityLabelInSingular(): TranslatableInterface|string|null
    {
        return $this->entityLabelInSingular;
    }

    public function setEntityLabelInSingular(TranslatableInterface|string|null $label): static
    {
        $this->entityLabelInSingular = $label;
        return $this;
    }

    public function getEntityLabelInPlural(): TranslatableInterface|string|null
    {
        return $this->entityLabelInPlural;
    }

    public function setEntityLabelInPlural(TranslatableInterface|string|null $label): static
    {
        $this->entityLabelInPlural = $label;
        return $this;
    }

    public function getPageTitle(string $pageName): TranslatableInterface|string|null
    {
        return $this->pageTitles[$pageName] ?? null;
    }

    public function setPageTitle(string $pageName, TranslatableInterface|string $title): static
    {
        $this->pageTitles[$pageName] = $title;
        return $this;
    }

    public function getHelp(string $pageName): TranslatableInterface|string|null
    {
        return $this->helpMessages[$pageName] ?? null;
    }

    public function setHelp(string $pageName, TranslatableInterface|string $helpMessage): static
    {
        $this->helpMessages[$pageName] = $helpMessage;
        return $this;
    }

    public function getDefaultSort(): array
    {
        return $this->defaultSort;
    }

    /**
     * @param array<string, 'ASC'|'DESC'> $sortFieldsAndOrder
     */
    public function setDefaultSort(array $sortFieldsAndOrder): static
    {
        $this->defaultSort = $sortFieldsAndOrder;
        return $this;
    }

    public function getPaginatorPageSize(): int
    {
        return $this->paginatorPageSize;
    }

    public function setPaginatorPageSize(int $maxResultsPerPage): static
    {
        $this->paginatorPageSize = $maxResultsPerPage;
        return $this;
    }

    public function getSearchFields(): array
    {
        return $this->searchFields;
    }

    public function setSearchFields(array $fieldNames): static
    {
        $this->searchFields = $fieldNames;
        return $this;
    }

    public function showEntityActionsInlined(bool $inlined = true): static
    {
        $this->entityActionsAsDropdown = !$inlined;
        return $this;
    }

    public function showEntityActionsAsDropdown(bool $asDropdown = true): static
    {
        $this->entityActionsAsDropdown = $asDropdown;
        return $this;
    }

    public function areEntityActionsInlined(): bool
    {
        return !$this->entityActionsAsDropdown;
    }

    public function getNewFormOptions(): array
    {
        return $this->newFormOptions;
    }

    public function getEditFormOptions(): array
    {
        return $this->editFormOptions;
    }

    public function setFormOptions(array $newFormOptions, ?array $editFormOptions = null): static
    {
        $this->newFormOptions = $newFormOptions;
        $this->editFormOptions = $editFormOptions ?? $newFormOptions;
        return $this;
    }

    public function getCurrentAction(): ?string
    {
        return $this->currentAction;
    }

    public function setCurrentAction(?string $currentAction): static
    {
        $this->currentAction = $currentAction;
        return $this;
    }

    public function getCurrentPage(): ?string
    {
        return $this->currentPage;
    }

    public function setCurrentPage(?string $currentPage): static
    {
        $this->currentPage = $currentPage;
        return $this;
    }

    /**
     * Kept for source compatibility with the former builder/DTO split.
     */
    public function getAsDto(): static
    {
        return $this;
    }
}
