<?php

namespace Base\Admin\Controller;

use Base\Admin\Config\Action;
use Base\Admin\Config\Actions;
use Base\Admin\Config\Crud;
use Base\Admin\Context\AdminContext;
use Base\Admin\Field\FieldDescriptor;
use Base\Admin\Field\FieldInterface;
use Base\Admin\Field\FieldValueResolver;
use Base\Admin\Field\IdField;
use Base\Admin\Form\FieldFormBuilder;
use Base\Admin\Orm\Paginator;
use Base\Admin\Router\AdminUrlGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use LogicException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * Direct, linear CRUD pipeline: every page is an ordinary controller action
 * calling overridable hooks - no event dispatch, no listener-side rendering.
 *
 * Hook names (configureFields/configureActions/configureCrud/
 * createIndexQueryBuilder/...) intentionally match the historical API so
 * existing controllers port without rewriting their bodies.
 */
abstract class AbstractCrudController extends AbstractController implements CrudControllerInterface
{
    protected EntityManagerInterface $entityManager;
    protected FieldFormBuilder $fieldFormBuilder;
    protected FieldValueResolver $fieldValueResolver;
    protected AdminUrlGenerator $adminUrlGenerator;
    protected AdminContext $adminContext;
    protected \Base\Admin\Menu\MenuBuilder $menuBuilder;

    #[Required]
    public function setAdminServices(
        EntityManagerInterface $entityManager,
        FieldFormBuilder $fieldFormBuilder,
        FieldValueResolver $fieldValueResolver,
        AdminUrlGenerator $adminUrlGenerator,
        AdminContext $adminContext,
        \Base\Admin\Menu\MenuBuilder $menuBuilder,
    ): void {
        $this->entityManager = $entityManager;
        $this->fieldFormBuilder = $fieldFormBuilder;
        $this->fieldValueResolver = $fieldValueResolver;
        $this->adminUrlGenerator = $adminUrlGenerator;
        $this->adminContext = $adminContext;
        $this->menuBuilder = $menuBuilder;
    }

    /**
     * Convention: App\Controller\Admin\Crud\Xxx\YyyCrudController maps to
     * App\Entity\Xxx\Yyy. Override for non-conventional locations.
     */
    public static array $crudNamespaceCandidates = ['\\Controller\\Crud\\', '\\Controller\\Backoffice\\Crud\\'];

    public static function getEntityFqcn(): string
    {
        $controllerFqcn = static::class;
        $entityBase = preg_replace('/CrudController$/', '', $controllerFqcn);

        foreach (static::$crudNamespaceCandidates as $namespace) {
            $entityFqcn = str_replace($namespace, '\\Entity\\', $entityBase);
            if ($entityFqcn !== $entityBase && class_exists($entityFqcn)) {
                return $entityFqcn;
            }
        }

        throw new LogicException(sprintf('Failed to guess the entity FQCN of "%s". Override getEntityFqcn().', $controllerFqcn));
    }

    public static function getPreferredIcon(): ?string
    {
        return null;
    }

    /**
     * Convention-based reverse lookup: entity FQCN (or instance) to its CRUD
     * controller FQCN. Mirrors getEntityFqcn(): each namespace candidate is
     * substituted for "\Entity\", trying the App\ variant before Base\.
     */
    public static function getCrudControllerFqcn(object|string|null $entity): ?string
    {
        $entityFqcn = \is_object($entity) ? \get_class($entity) : $entity;
        if (null === $entityFqcn || !class_exists($entityFqcn)) {
            return null;
        }

        // strip doctrine proxy prefix
        if (false !== ($pos = strrpos($entityFqcn, '\\__CG__\\'))) {
            $entityFqcn = substr($entityFqcn, $pos + 8);
        }

        foreach (static::$crudNamespaceCandidates as $namespace) {
            $controllerFqcn = str_replace('\\Entity\\', $namespace, $entityFqcn) . 'CrudController';

            $appVariant = preg_replace('/^Base\\\\/', 'App\\', $controllerFqcn);
            $baseVariant = preg_replace('/^App\\\\/', 'Base\\', $controllerFqcn);

            foreach (array_unique([$appVariant, $controllerFqcn, $baseVariant]) as $candidate) {
                if (class_exists($candidate)) {
                    return $candidate;
                }
            }
        }

        return null !== get_parent_class($entityFqcn) && false !== get_parent_class($entityFqcn)
            ? static::getCrudControllerFqcn(get_parent_class($entityFqcn))
            : null;
    }

    // -----------------------------------------------------------------
    // configuration hooks
    // -----------------------------------------------------------------

    /**
     * @return iterable<FieldInterface>
     */
    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id');
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions->addDefaults();
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setDefaultSort(['id' => 'DESC'])
            ->setPaginatorPageSize(20);
    }

    public function configureFilters(\Base\Admin\Filter\Filters $filters): \Base\Admin\Filter\Filters
    {
        return $filters;
    }

    // -----------------------------------------------------------------
    // page actions
    // -----------------------------------------------------------------

    public function index(Request $request): Response
    {
        $crud = $this->getCrudConfig(Crud::PAGE_INDEX, Action::INDEX);
        $this->denyAccessUnlessGrantedToRun($crud);

        $fields = $this->getFields(Crud::PAGE_INDEX);

        $sort = $this->getSort($request, $crud);
        $queryBuilder = $this->createIndexQueryBuilder($crud, $sort);

        $query = trim($request->query->getString('query'));
        if ('' !== $query) {
            $this->applySearch($queryBuilder, $crud, $query);
        }

        $filters = $this->getFiltersConfig();
        $filterValues = $request->query->all('filters');
        $this->applyFilters($queryBuilder, $filters, $filterValues);

        $paginator = new Paginator(
            $queryBuilder,
            max(1, $request->query->getInt('page', 1)),
            $crud->getPaginatorPageSize()
        );

        $rows = [];
        foreach ($paginator as $entity) {
            $rows[] = [
                'entity' => $entity,
                'fields' => $this->fieldValueResolver->resolveAll($fields, $entity, FieldDescriptor::PAGE_INDEX),
            ];
        }

        $actions = $this->getActionsConfig();

        return $this->renderCrud('@Admin/crud/index.html.twig', [
            'crud' => $crud,
            'fields' => array_map(fn ($f) => $f instanceof FieldInterface ? $f->getAsDto() : $f, is_array($fields) ? $fields : iterator_to_array($fields, false)),
            'rows' => $rows,
            'paginator' => $paginator,
            'actions' => $actions,
            'sort' => $sort,
            'query' => $query,
            'filters' => $filters,
            'filter_values' => $filterValues,
        ]);
    }

    public function detail(Request $request, string $entityId): Response
    {
        $crud = $this->getCrudConfig(Crud::PAGE_DETAIL, Action::DETAIL);
        $this->denyAccessUnlessGrantedToRun($crud);

        $entity = $this->findEntity($entityId);
        $this->adminContext->setEntity($entity);

        return $this->renderCrud('@Admin/crud/detail.html.twig', [
            'crud' => $crud,
            'entity' => $entity,
            'entityId' => $entityId,
            'fields' => $this->fieldValueResolver->resolveAll($this->getFields(Crud::PAGE_DETAIL), $entity, FieldDescriptor::PAGE_DETAIL),
            'actions' => $this->getActionsConfig(),
        ]);
    }

    public function new(Request $request): Response
    {
        $crud = $this->getCrudConfig(Crud::PAGE_NEW, Action::NEW);
        $this->denyAccessUnlessGrantedToRun($crud);

        $entity = $this->createEntity(static::getEntityFqcn());
        $this->adminContext->setEntity($entity);

        $form = $this->fieldFormBuilder->createForm($entity, $this->getFields(Crud::PAGE_NEW), FieldDescriptor::PAGE_NEW, $crud->getNewFormOptions());
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->persistEntity($this->entityManager, $entity);
            $this->addFlash('success', new \Symfony\Component\Translation\TranslatableMessage('flash.created', [], 'admin'));

            return $this->redirectAfterSubmit($request, $entity);
        }

        return $this->renderCrud('@Admin/crud/new.html.twig', [
            'crud' => $crud,
            'entity' => $entity,
            'form' => $form,
            'actions' => $this->getActionsConfig(),
        ]);
    }

    public function edit(Request $request, string $entityId): Response
    {
        $crud = $this->getCrudConfig(Crud::PAGE_EDIT, Action::EDIT);
        $this->denyAccessUnlessGrantedToRun($crud);

        $entity = $this->findEntity($entityId);
        $this->adminContext->setEntity($entity);

        $form = $this->fieldFormBuilder->createForm($entity, $this->getFields(Crud::PAGE_EDIT), FieldDescriptor::PAGE_EDIT, $crud->getEditFormOptions());
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->updateEntity($this->entityManager, $entity);
            $this->addFlash('success', new \Symfony\Component\Translation\TranslatableMessage('flash.updated', [], 'admin'));

            return $this->redirectAfterSubmit($request, $entity);
        }

        return $this->renderCrud('@Admin/crud/edit.html.twig', [
            'crud' => $crud,
            'entity' => $entity,
            'entityId' => $entityId,
            'form' => $form,
            'actions' => $this->getActionsConfig(),
        ]);
    }

    public function delete(Request $request, string $entityId): Response
    {
        $crud = $this->getCrudConfig(Crud::PAGE_INDEX, Action::DELETE);
        $this->denyAccessUnlessGrantedToRun($crud);

        // same token as the batch form: the row delete button submits it
        if (!$this->isCsrfTokenValid('admin-batch-' . static::getEntityFqcn(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $entity = $this->findEntity($entityId);
        $this->deleteEntity($this->entityManager, $entity);
        $this->addFlash('success', new \Symfony\Component\Translation\TranslatableMessage('flash.deleted', [], 'admin'));

        return $this->redirect($this->adminUrlGenerator->setController(static::class)->setAction(Action::INDEX)->generateUrl());
    }

    public function batchDelete(Request $request): Response
    {
        $crud = $this->getCrudConfig(Crud::PAGE_INDEX, Action::BATCH_DELETE);
        $this->denyAccessUnlessGrantedToRun($crud);

        if (!$this->isCsrfTokenValid('admin-batch-' . static::getEntityFqcn(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        foreach ($request->request->all('batchIds') as $entityId) {
            $entity = $this->entityManager->find(static::getEntityFqcn(), $entityId);
            if (null !== $entity) {
                $this->deleteEntity($this->entityManager, $entity);
            }
        }

        return $this->redirect($this->adminUrlGenerator->setController(static::class)->setAction(Action::INDEX)->generateUrl());
    }

    /**
     * Backs crud/field/boolean.html.twig's inline switch - the whole reason
     * this exists rather than routing a toggle through the normal edit
     * form. Deliberately narrow: only properties that are ACTUALLY
     * configured as a BooleanField with the switch option on, on THIS
     * crud's own field list, can be flipped - a client can't toggle an
     * arbitrary property just by guessing its name, and this can't be used
     * to touch fields the controller never chose to expose as a switch in
     * the first place. Requires edit permission, same CSRF token family as
     * the rest of this controller's mutations.
     */
    public function toggle(Request $request, string $entityId): Response
    {
        $crud = $this->getCrudConfig(Crud::PAGE_EDIT, Action::EDIT);
        $this->denyAccessUnlessGrantedToRun($crud);

        $payload = $request->toArray();
        $property = (string) ($payload['property'] ?? '');
        $token = (string) ($payload['_token'] ?? '');

        if (!$this->isCsrfTokenValid(\Base\Admin\Field\BooleanField::CSRF_TOKEN_NAME, $token)) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $togglable = null;
        foreach ($this->getFields(Crud::PAGE_INDEX) as $field) {
            $descriptor = $field->getAsDto();
            if ($descriptor->getProperty() === $property
                && $field instanceof \Base\Admin\Field\BooleanField
                && $descriptor->getCustomOption(\Base\Admin\Field\BooleanField::OPTION_RENDER_AS_SWITCH)
            ) {
                $togglable = $descriptor;
                break;
            }
        }
        if (null === $togglable) {
            throw $this->createNotFoundException(sprintf('"%s" is not a switchable field on this crud.', $property));
        }

        $entity = $this->findEntity($entityId);
        $accessor = \Symfony\Component\PropertyAccess\PropertyAccess::createPropertyAccessor();
        $newValue = !$accessor->getValue($entity, $property);
        $accessor->setValue($entity, $property, $newValue);
        $this->updateEntity($this->entityManager, $entity);

        return $this->json(['property' => $property, 'value' => $newValue]);
    }

    // -----------------------------------------------------------------
    // overridable persistence + query hooks
    // -----------------------------------------------------------------

    public function createEntity(string $entityFqcn): object
    {
        $reflection = new \ReflectionClass($entityFqcn);
        $constructor = $reflection->getConstructor();

        if (null === $constructor || 0 === $constructor->getNumberOfRequiredParameters()) {
            return new $entityFqcn();
        }

        // required constructor args (author entities, pathed settings, ...):
        // pass null/'' defaults so the blank instance is form-fillable;
        // override createEntity() when the entity needs a smarter default
        $arguments = [];
        foreach ($constructor->getParameters() as $parameter) {
            if ($parameter->isOptional()) {
                break;
            }
            $type = $parameter->getType();
            $arguments[] = ($type instanceof \ReflectionNamedType && 'string' === $type->getName() && !$type->allowsNull()) ? '' : null;
        }

        return $reflection->newInstanceArgs($arguments);
    }

    public function persistEntity(EntityManagerInterface $entityManager, object $entity): void
    {
        $entityManager->persist($entity);
        $entityManager->flush();
    }

    public function updateEntity(EntityManagerInterface $entityManager, object $entity): void
    {
        $entityManager->flush();
    }

    public function deleteEntity(EntityManagerInterface $entityManager, object $entity): void
    {
        $entityManager->remove($entity);
        $entityManager->flush();
    }

    public function createIndexQueryBuilder(Crud $crud, array $sort): QueryBuilder
    {
        $queryBuilder = $this->entityManager
            ->getRepository(static::getEntityFqcn())
            ->createQueryBuilder('entity');

        foreach ($sort as $property => $direction) {
            $queryBuilder->addOrderBy('entity.' . $property, $direction);
        }

        return $queryBuilder;
    }

    protected function getFiltersConfig(): \Base\Admin\Filter\Filters
    {
        $filters = $this->configureFilters(\Base\Admin\Filter\Filters::new());

        // guess widget types from Doctrine metadata for plain add('property')
        $metadata = $this->entityManager->getClassMetadata(static::getEntityFqcn());
        foreach ($filters->getAll() as $property => $filter) {
            if (\Base\Admin\Filter\Filter::TYPE_TEXT !== $filter->getType() || !$metadata->hasField($property)) {
                continue;
            }
            match ($metadata->getTypeOfField($property)) {
                'boolean' => $filter->asBoolean(),
                'integer', 'smallint', 'bigint', 'float', 'decimal' => $filter->asNumeric(),
                'date', 'datetime', 'datetime_immutable', 'date_immutable' => $filter->asDate(),
                default => null,
            };
        }

        return $filters;
    }

    protected function applyFilters(QueryBuilder $queryBuilder, \Base\Admin\Filter\Filters $filters, array $filterValues): void
    {
        foreach ($filters->getAll() as $property => $filter) {
            $value = $filterValues[$property] ?? null;
            if (\Base\Admin\Filter\Filter::isActive($value)) {
                $filter->apply($queryBuilder, 'entity', $value);
            }
        }
    }

    /**
     * Case-insensitive LIKE over the crud's searchFields; falls back to
     * every string-ish field shown on the index when none are declared.
     */
    protected function applySearch(QueryBuilder $queryBuilder, Crud $crud, string $query): void
    {
        $searchFields = $crud->getSearchFields();
        if ([] === $searchFields) {
            $metadata = $this->entityManager->getClassMetadata(static::getEntityFqcn());
            foreach ($this->getFields(Crud::PAGE_INDEX) as $field) {
                $property = $field->getAsDto()->getProperty();
                if ($metadata->hasField($property) && \in_array($metadata->getTypeOfField($property), ['string', 'text'], true)) {
                    $searchFields[] = $property;
                }
            }
        }

        if ([] === $searchFields) {
            return;
        }

        $or = $queryBuilder->expr()->orX();
        foreach ($searchFields as $i => $property) {
            $or->add($queryBuilder->expr()->like('LOWER(entity.' . $property . ')', ':admin_query'));
        }

        $queryBuilder->andWhere($or)->setParameter('admin_query', '%' . mb_strtolower($query) . '%');
    }

    // -----------------------------------------------------------------
    // plumbing
    // -----------------------------------------------------------------

    protected function getCrudConfig(string $pageName, string $actionName): Crud
    {
        $crud = $this->configureCrud(
            Crud::new()
                ->setEntityFqcn(static::getEntityFqcn())
                ->setCurrentPage($pageName)
                ->setCurrentAction($actionName)
        );

        $this->adminContext
            ->setCrudControllerFqcn(static::class)
            ->setCrud($crud);

        return $crud;
    }

    protected function getActionsConfig(): Actions
    {
        $actions = $this->configureActions(Actions::new());
        $this->adminContext->setActions($actions);

        return $actions;
    }

    /**
     * @return iterable<FieldInterface>
     */
    protected function getFields(string $pageName): iterable
    {
        $fields = $this->configureFields($pageName);

        return is_array($fields) ? $fields : iterator_to_array($fields, false);
    }

    protected function getSort(Request $request, Crud $crud): array
    {
        $sort = $request->query->all('sort');
        if ([] === $sort) {
            return $crud->getDefaultSort();
        }

        $clean = [];
        foreach ($sort as $property => $direction) {
            if (preg_match('/^[a-zA-Z0-9_.]+$/', (string) $property)) {
                $clean[$property] = 'DESC' === strtoupper((string) $direction) ? 'DESC' : 'ASC';
            }
        }

        return $clean;
    }

    protected function findEntity(string $entityId): object
    {
        $entity = $this->entityManager->find(static::getEntityFqcn(), $entityId);
        if (null === $entity) {
            throw $this->createNotFoundException(sprintf('No "%s" found for id "%s".', static::getEntityFqcn(), $entityId));
        }

        return $entity;
    }

    protected function denyAccessUnlessGrantedToRun(Crud $crud): void
    {
        // per-action permissions are declared with Actions::setPermission();
        // entity-level restriction hooks in here later (EA_ACCESS_ENTITY)
    }

    protected function redirectAfterSubmit(Request $request, object $entity): Response
    {
        $submitAction = $request->request->getString('submit_action', Action::SAVE_AND_RETURN);
        $url = $this->adminUrlGenerator->setController(static::class);

        $id = method_exists($entity, 'getId') ? $entity->getId() : null;

        return $this->redirect(match ($submitAction) {
            Action::SAVE_AND_CONTINUE => $url->setAction(Action::EDIT)->setEntityId($id)->generateUrl(),
            Action::SAVE_AND_ADD_ANOTHER => $url->setAction(Action::NEW)->generateUrl(),
            default => $url->setAction(Action::INDEX)->generateUrl(),
        });
    }

    protected function renderCrud(string $template, array $parameters): Response
    {
        if ([] === $this->adminContext->getMainMenu()) {
            $this->adminContext->setMainMenu($this->menuBuilder->buildDefault());
        }
        if ([] === $this->adminContext->getUserMenu()) {
            $this->adminContext->setUserMenu($this->menuBuilder->buildUserMenuDefault($this->getUser()));
        }

        return $this->render($template, $parameters + [
            'admin_context' => $this->adminContext,
            'controller_fqcn' => static::class,
        ]);
    }
}
