<?php

namespace Base\Admin\Controller;

use Base\Admin\Config\Action;
use Base\Database\Attribute\Alias;
use function Symfony\Component\Translation\t;
use Base\Admin\Config\Actions;
use Base\Admin\Config\Crud;
use Base\Admin\Context\AdminContext;
use Base\Field\FieldDescriptor;
use Base\Field\FieldInterface;
use Base\Field\FieldValueResolver;
use Base\Field\IdField;
use Base\Admin\Form\FieldFormBuilder;
use Base\Admin\Orm\Paginator;
use Base\Admin\Router\AdminUrlGenerator;
use Base\Service\Model\LinkableInterface;
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
    protected \Base\Admin\Layout\LayoutStore $layoutStore;
    protected \Base\Admin\Router\AdminRouteRegistry $routeRegistry;
    protected \Base\Admin\Layout\PageCustomization $pageCustomization;

    #[Required]
    public function setAdminServices(
        EntityManagerInterface $entityManager,
        FieldFormBuilder $fieldFormBuilder,
        FieldValueResolver $fieldValueResolver,
        AdminUrlGenerator $adminUrlGenerator,
        AdminContext $adminContext,
        \Base\Admin\Menu\MenuBuilder $menuBuilder,
        \Base\Admin\Layout\LayoutStore $layoutStore,
        \Base\Admin\Router\AdminRouteRegistry $routeRegistry,
        \Base\Admin\Layout\PageCustomization $pageCustomization,
    ): void {
        $this->entityManager = $entityManager;
        $this->fieldFormBuilder = $fieldFormBuilder;
        $this->fieldValueResolver = $fieldValueResolver;
        $this->adminUrlGenerator = $adminUrlGenerator;
        $this->adminContext = $adminContext;
        $this->menuBuilder = $menuBuilder;
        $this->layoutStore = $layoutStore;
        $this->routeRegistry = $routeRegistry;
        $this->pageCustomization = $pageCustomization;
    }

    /**
     * Convention: App\Controller\Admin\Crud\Xxx\YyyCrudController maps to
     * App\Entity\Xxx\Yyy. Override for non-conventional locations.
     */
    public static array $crudNamespaceCandidates = ['\\Controller\\Crud\\', '\\Controller\\Admin\\Crud\\', '\\Controller\\Backoffice\\Crud\\'];

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

        // App\ wins regardless of WHICH namespace candidate it lives under -
        // check every candidate's App\ variant before falling back to a
        // Base\/direct match, otherwise an early match on an earlier
        // candidate (e.g. the bundle's own Base\...\Backoffice\... default)
        // shadows a real override that only exists under a later candidate
        // (e.g. an app using \Controller\Admin\Crud\...).
        $fallback = null;
        foreach (static::$crudNamespaceCandidates as $namespace) {
            $controllerFqcn = str_replace('\\Entity\\', $namespace, $entityFqcn) . 'CrudController';

            $appVariant = preg_replace('/^Base\\\\/', 'App\\', $controllerFqcn);
            $baseVariant = preg_replace('/^App\\\\/', 'Base\\', $controllerFqcn);

            if (class_exists($appVariant)) {
                return $appVariant;
            }

            if (null === $fallback) {
                foreach (array_unique([$controllerFqcn, $baseVariant]) as $candidate) {
                    if (class_exists($candidate)) {
                        $fallback = $candidate;
                        break;
                    }
                }
            }
        }

        if (null !== $fallback) {
            return $fallback;
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
        $actions = $actions->addDefaults();

        // Entities that expose a real front-end URL (Article, Destination,
        // Gallery, ...) get a "view on the live site" action for free - no
        // per-controller wiring needed, mirrors what every CRUD had under
        // the old EasyAdmin-based admin: a plug icon on index rows, a
        // "see online" entry in the edit/detail action bars.
        if (is_subclass_of(static::getEntityFqcn(), LinkableInterface::class)) {
            $seeOnline = fn () => Action::new(Action::GOTO, t('action.goto', domain: 'admin'), 'fa-solid fa-fw fa-plug')
                ->renderAsTooltip()
                ->targetBlank()
                ->linkToUrl(fn (object $entity) => $entity->__toLink() ?? '');

            $actions->add(Actions::PAGE_INDEX, $seeOnline());
            $actions->add(Actions::PAGE_EDIT, $seeOnline()->setIcon('fa-solid fa-fw fa-square-up-right'));
            $actions->add(Actions::PAGE_DETAIL, $seeOnline()->setIcon('fa-solid fa-fw fa-square-up-right'));
        }

        // Edit/detail get the full historical action bar: jump to detail,
        // delete, and previous/next record navigation (following the
        // default id ordering; the callables resolve lazily per entity at
        // render time, no query happens unless the page renders them).
        $actions->add(Actions::PAGE_EDIT, Action::new(Action::DETAIL, t('action.detail', domain: 'admin'), 'fa-solid fa-fw fa-magnifying-glass')
            ->setCssClass('action-detail')
            ->linkToCrudAction(Action::DETAIL));
        $actions->add(Actions::PAGE_EDIT, Action::new(Action::DELETE, t('action.delete', domain: 'admin'), 'fa-solid fa-fw fa-trash')
            ->setCssClass('action-delete text-danger')
            ->linkToCrudAction(Action::DELETE));

        foreach ([Actions::PAGE_EDIT, Actions::PAGE_DETAIL] as $page) {
            $actions->add($page, Action::new(Action::GOTO_PREV, t('action.goto_prev', domain: 'admin'), 'fa-solid fa-fw fa-angle-left')
                ->renderAsTooltip()
                ->linkToUrl(fn (object $entity) => $this->adjacentEntityUrl($entity, 'prev', $page)));
            $actions->add($page, Action::new(Action::GOTO_NEXT, t('action.goto_next', domain: 'admin'), 'fa-solid fa-fw fa-angle-right')
                ->renderAsTooltip()
                ->linkToUrl(fn (object $entity) => $this->adjacentEntityUrl($entity, 'next', $page)));
        }

        return $actions;
    }

    /**
     * Edit/detail URL of the neighbouring record in the index's default id
     * ordering (id DESC: "next" walks down the list toward older rows) -
     * empty string when there is no neighbour, which the action templates
     * treat as "don't render this action".
     */
    protected function adjacentEntityUrl(object $entity, string $direction, string $page): string
    {
        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->select('e.id')
            ->from(static::getEntityFqcn(), 'e')
            ->setMaxResults(1)
            ->setParameter('id', $entity->getId());

        if ('prev' === $direction) {
            $queryBuilder->where('e.id > :id')->orderBy('e.id', 'ASC');
        } else {
            $queryBuilder->where('e.id < :id')->orderBy('e.id', 'DESC');
        }

        $neighbour = $queryBuilder->getQuery()->getOneOrNullResult();
        if (null === $neighbour) {
            return '';
        }

        return $this->adminUrlGenerator
            ->setController(static::class)
            ->setAction(Actions::PAGE_DETAIL === $page ? Action::DETAIL : Action::EDIT)
            ->setEntityId($neighbour['id'])
            ->generateUrl();
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
            'new_variants' => $this->getNewVariants(),
        ]);
    }

    /**
     * The concrete creatable classes reachable from this CRUD's "new"
     * button: the entity itself plus any Doctrine discriminator-map
     * subclass that has its own registered CRUD controller. More than one
     * entry turns the index's create button into a subclass chooser (the
     * historical "action-discriminator" dropdown); a lone entry (the
     * common case, no inheritance) keeps the plain button.
     *
     * @return array<int, array{label: string, url: string}>
     */
    protected function getNewVariants(): array
    {
        $entityFqcn = static::getEntityFqcn();
        $metadata = $this->entityManager->getClassMetadata($entityFqcn);

        $variants = [];
        foreach ($metadata->discriminatorMap ?: [$entityFqcn] as $class) {
            if ($class !== $entityFqcn && !is_subclass_of($class, $entityFqcn)) {
                continue;
            }
            if ((new \ReflectionClass($class))->isAbstract()) {
                continue;
            }

            $controller = static::getCrudControllerFqcn($class);
            if (null === $controller) {
                continue;
            }

            try {
                $url = $this->adminUrlGenerator->setController($controller)->setAction(Action::NEW)->generateUrl();
            } catch (\InvalidArgumentException) {
                continue; // controller exists but isn't registered (mid-migration)
            }

            $shortName = substr((string) strrchr('\\' . $class, '\\'), 1);
            $variants[$url] = [
                'label' => ucfirst(strtolower(trim(preg_replace('/(?<=[a-z0-9])([A-Z])/', ' $1', $shortName)))),
                'url' => $url,
            ];
        }

        return array_values($variants);
    }

    public function detail(Request $request, string $entityId): Response
    {
        $crud = $this->getCrudConfig(Crud::PAGE_DETAIL, Action::DETAIL);
        $this->denyAccessUnlessGrantedToRun($crud);

        $entity = $this->findEntity($entityId);
        if (null !== $redirect = $this->canonicalIdentifierRedirect($request, $entity, $entityId, Action::DETAIL)) {
            return $redirect;
        }
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
        if (null !== $redirect = $this->canonicalIdentifierRedirect($request, $entity, $entityId, Action::EDIT)) {
            return $redirect;
        }
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

    /**
     * One slice of an embedded collection, rendered on demand.
     *
     * Collections are capped (CollectionType::$max_entries) because every entry
     * is a full sub-form whose relations get hydrated - measured at ~3 queries
     * each, so an unbounded collection is an unbounded query count. The cap
     * bounds the initial page; this renders the rest when the operator actually
     * asks for it.
     *
     * The form is rebuilt with the collection WINDOWED to the requested slice,
     * so this request pays for that slice only. array_slice preserves keys in
     * CollectionType, so a lazily fetched entry keeps its real index and still
     * binds to the right slot when the parent form is submitted.
     */
    public function collectionEntries(Request $request, string $entityId, string $field): Response
    {
        $crud = $this->getCrudConfig(Crud::PAGE_EDIT, Action::EDIT);
        $this->denyAccessUnlessGrantedToRun($crud);

        $entity = $this->findEntity($entityId);
        $offset = max(0, $request->query->getInt('offset'));
        $limit = min(50, max(1, $request->query->getInt('limit', 10)));

        // Work on DESCRIPTORS, not the field objects: getFields() yields
        // FieldInterface instances (IdField, SelectField...), and only their
        // DTO exposes getProperty(). FieldFormBuilder performs the same
        // conversion, and accepts either shape, so passing descriptors through
        // produces byte-identical field names to the full form.
        $fields = [];
        foreach ($this->getFields(Crud::PAGE_EDIT) as $candidate) {
            $fields[] = $candidate instanceof FieldInterface ? $candidate->getAsDto() : $candidate;
        }

        $target = null;
        foreach ($fields as $candidate) {
            if ($candidate->getProperty() === $field) {
                $target = $candidate;
                break;
            }
        }

        if (null === $target) {
            throw $this->createNotFoundException(sprintf('No field "%s" on this CRUD.', $field));
        }

        // Window THIS field only; every other field keeps its normal options so
        // the generated names stay identical to the ones the full form emits.
        $target->setFormTypeOption('entry_offset', $offset);
        $target->setFormTypeOption('max_entries', $limit);

        $form = $this->fieldFormBuilder->createForm($entity, $fields, FieldDescriptor::PAGE_EDIT, $crud->getEditFormOptions());

        $child = $form->get($field);
        if ($child->has('_collection')) {
            $child = $child->get('_collection');
        }

        return $this->render('@Admin/crud/_collection_entries.html.twig', [
            'entries' => $child->createView(),
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

        if (!$this->isCsrfTokenValid(\Base\Field\BooleanField::CSRF_TOKEN_NAME, $token)) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $togglable = null;
        foreach ($this->getFields(Crud::PAGE_INDEX) as $field) {
            $descriptor = $field->getAsDto();
            if ($descriptor->getProperty() === $property
                && $field instanceof \Base\Field\BooleanField
                && $descriptor->getCustomOption(\Base\Field\BooleanField::OPTION_RENDER_AS_SWITCH)
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

    /**
     * Constructor parameter names that mean "who this record belongs to".
     *
     * Matching on the NAME rather than the User type is deliberate and
     * load-bearing. Plenty of constructors here take a User that is not an
     * owner at all - Sanction's is `(?User $user, ?Penalty $penalty, ?User
     * $moderator, ...)`, where the first User is the person being
     * sanctioned - so filling every User parameter with whoever is logged
     * in would quietly sanction the moderator instead of the offender.
     */
    private const OWNER_PARAMETERS = ['owner', 'author'];

    public function createEntity(string $entityFqcn): object
    {
        $reflection = new \ReflectionClass($entityFqcn);
        $constructor = $reflection->getConstructor();

        if (null === $constructor) {
            return new $entityFqcn();
        }

        // A new record defaults to being owned by whoever is creating it -
        // which is what makes a new article arrive with its author already
        // filled in, rather than requiring the one person who cannot be
        // wrong about it to pick themselves from a list every time. It stays
        // an ordinary pre-filled form value: the field is still editable, so
        // publishing on someone else's behalf costs one change instead of
        // being the default state.
        //
        // Passed through the CONSTRUCTOR rather than set afterwards because
        // each entity decides for itself what owning it means. Article and
        // Destination expose the field as `authors`, a property that Alias
        // only binds to `owners` on prePersist/postLoad - so on a brand-new
        // in-memory instance the two collections are still separate, and
        // calling addOwner() afterwards would fill the one the form is not
        // reading. Their constructors seed both; only they know that.
        $owner = $this->getUser();
        $parameters = $constructor->getParameters();
        $ownerIndex = null !== $owner ? $this->resolveOwnerParameter($entityFqcn, $parameters) : null;

        // The owner is not always the first parameter - Photo's signature is
        // `(?string $image, ?User $owner, ...)` - so optional parameters
        // before it are filled with their own declared defaults instead of
        // stopping the walk, then any purely-default tail is trimmed back
        // off so entities without an owner are constructed exactly as before.
        $arguments = [];
        $lastMeaningful = -1;

        foreach ($parameters as $index => $parameter) {
            if ($index === $ownerIndex) {
                $arguments[$index] = $owner;
                $lastMeaningful = $index;

                continue;
            }

            // A variadic tail accepts no positional default, so anything
            // past here would be inventing arguments the signature never
            // asked for. (Gallery's `(...$args)` forwards straight to
            // Thread's constructor, which is why it can still be the owner
            // slot above.)
            if ($parameter->isVariadic()) {
                break;
            }

            if ($parameter->isOptional()) {
                if (!$parameter->isDefaultValueAvailable()) {
                    break;
                }

                $arguments[$index] = $parameter->getDefaultValue();

                continue;
            }

            $type = $parameter->getType();

            // required constructor args (pathed settings, ...): pass null/''
            // defaults so the blank instance is form-fillable; override
            // createEntity() when the entity needs a smarter default
            $arguments[$index] = ($type instanceof \ReflectionNamedType && 'string' === $type->getName() && !$type->allowsNull()) ? '' : null;
            $lastMeaningful = $index;
        }

        $arguments = \array_slice($arguments, 0, $lastMeaningful + 1);
        $entity = [] === $arguments ? new $entityFqcn() : $reflection->newInstanceArgs($arguments);

        if (null !== $ownerIndex) {
            $this->bindOwnerAliases($reflection, $entity);
        }

        return $entity;
    }

    /**
     * The property Thread keeps its owners in, and the one an #[Alias] has to
     * name to be an alias OF ownership rather than of something else.
     */
    private const OWNERS_PROPERTY = 'owners';

    /**
     * Point aliased owner properties at the owners collection.
     *
     * Five entities expose their owners under a second name via #[Alias] -
     * Article, Destination, Comment, Gallery and Calendar all call it
     * `authors` - and that is the name their CRUD form actually binds to. But
     * Alias only ties the two properties together on postLoad/prePersist, so
     * on an instance that has just been constructed and not yet saved they
     * are still two separate values: the constructor filled `owners`, and the
     * form reads an `authors` that is still null. The author field came up
     * blank on four of the five for exactly that reason.
     *
     * Article is the one that worked, because its constructor happens to seed
     * `authors` by hand as well. Doing this here instead means the other four
     * do not each need to remember to - and the entity that already does is
     * unharmed, since Alias::bind() unions the two collections by key rather
     * than appending, so the owner does not end up listed twice.
     *
     * Restricted to aliases OF `owners`: the same attribute is also used for
     * unrelated things (Gallery's `photos` aliases `children`), and binding
     * those early is a broader change than defaulting an author calls for.
     */
    private function bindOwnerAliases(\ReflectionClass $reflection, object $entity): void
    {
        foreach ($reflection->getProperties() as $property) {
            foreach ($property->getAttributes(Alias::class) as $attribute) {
                $alias = $attribute->newInstance();

                if (self::OWNERS_PROPERTY !== $alias->column) {
                    continue;
                }

                $alias->bind($entity, $alias->column, $property->getName());
            }
        }
    }

    /**
     * Which constructor argument, if any, should receive the current user.
     *
     * Two rules, in this order:
     *
     * 1. A parameter explicitly named owner/author, whatever its position.
     *    This has to win outright, because Photo is a Thread whose first
     *    parameter is its image and whose owner is second - positional
     *    guessing there would set the filename to a User.
     *
     * 2. Failing that, the FIRST parameter of a Thread subclass. Every
     *    thread in this codebase takes its owner first and forwards it to
     *    Thread::__construct(), but they do not agree on how to spell it:
     *    Destination declares an untyped `$user`, and Gallery declares
     *    `(...$args)` and forwards the lot. Neither is name- or
     *    type-matchable, and both mean the same thing. Restricting the
     *    positional rule to Thread subclasses is what keeps it safe -
     *    Sanction and Notification also lead with a User that is emphatically
     *    not their owner, and neither is a Thread.
     *
     * @param list<\ReflectionParameter> $parameters
     */
    private function resolveOwnerParameter(string $entityFqcn, array $parameters): ?int
    {
        foreach ($parameters as $index => $parameter) {
            if (\in_array(\strtolower($parameter->getName()), self::OWNER_PARAMETERS, true)) {
                return $this->acceptsOwner($parameter) ? $index : null;
            }
        }

        if ([] === $parameters || !\is_subclass_of($entityFqcn, \Base\Entity\Thread::class)) {
            return null;
        }

        return $this->acceptsOwner($parameters[0]) ? 0 : null;
    }

    /**
     * Whether the logged-in user can actually be passed here - an untyped or
     * variadic parameter takes anything, a typed one has to agree.
     */
    private function acceptsOwner(\ReflectionParameter $parameter): bool
    {
        $type = $parameter->getType();

        if (null === $type || $parameter->isVariadic()) {
            return true;
        }

        return $type instanceof \ReflectionNamedType
            && !$type->isBuiltin()
            && $this->getUser() instanceof ($type->getName());
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

        // Every Thread carries a state, and nothing declared a filter for it -
        // so a Counter widget reading "7 brouillons" had nowhere to send you
        // that actually showed those 7. Offered by default rather than added
        // to each CRUD by hand, and only when that CRUD has not named `state`
        // itself, so an explicit declaration still wins.
        $entityFqcn = static::getEntityFqcn();
        if (null === $filters->get('state') && \is_subclass_of($entityFqcn, \Base\Entity\Thread::class)) {
            $filters->add(\Base\Admin\Filter\Filter::new('state', 'État')->asChoice([
                'Publié' => \Base\Enum\ThreadState::PUBLISH,
                'Brouillon' => \Base\Enum\ThreadState::DRAFT,
                'Programmé' => \Base\Enum\ThreadState::FUTURE,
                'Caché' => \Base\Enum\ThreadState::SECRET,
                'Archivé' => \Base\Enum\ThreadState::ARCHIVE,
            ]));
        }

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

    protected ?Actions $actionsConfig = null;

    protected function getActionsConfig(): Actions
    {
        if (null !== $this->actionsConfig) {
            return $this->actionsConfig;
        }

        $actions = $this->configureActions(Actions::new());

        foreach ([Actions::PAGE_INDEX, Actions::PAGE_DETAIL, Actions::PAGE_EDIT, Actions::PAGE_NEW] as $pageName) {
            foreach ($actions->getAll($pageName) as $actionName => $action) {
                $permission = $actions->getEffectivePermission($actionName, $action);
                if (null !== $permission && !$this->isGranted($permission)) {
                    $actions->remove($pageName, $actionName);
                }
            }
        }

        $this->adminContext->setActions($actions);

        return $this->actionsConfig = $actions;
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

    /**
     * Resolves the {entityId} route parameter, which may be a SLUG, a UUID
     * or the numeric id - URLs are generated with the first of those the
     * entity has (see FieldValueResolver::entityIdentifier), but all three
     * keep working so older links and bookmarks never break.
     *
     * A purely numeric value is tried as the primary key FIRST: that is the
     * overwhelmingly common case and skips two pointless queries. Anything
     * else goes to the slug/uuid lookups, and only those fields that
     * actually exist on the entity's Doctrine metadata are queried - asking
     * for a missing field would throw rather than simply miss.
     */
    /**
     * Redirects a record reached by a NON-canonical identifier (typically the
     * numeric id) to its canonical one (the slug) so the readable URL is what
     * ends up in the address bar and in bookmarks.
     *
     * GET only, and never for a submitted form: redirecting a POST would
     * discard the request body, i.e. silently throw away the user's edits.
     * 302 rather than 301 - a slug is editable, so a permanent redirect the
     * browser caches would outlive the mapping it describes.
     *
     * The comparison is CASE-INSENSITIVE: identifier lookups run through the
     * database's collation, which for this app is case-insensitive, so
     * "/admin/users/marki" and "/admin/users/Marki" already load the same
     * record. Bouncing one to the other would be a redirect that changes
     * nothing a reader can act on. A difference in anything OTHER than case
     * (a stale slug, an id, an accent the collation folded) still redirects.
     */
    protected function canonicalIdentifierRedirect(Request $request, object $entity, string $entityId, string $action): ?Response
    {
        if (!$request->isMethod('GET')) {
            return null;
        }

        $canonical = (string) $this->fieldValueResolver->entityIdentifier($entity);
        if ('' === $canonical || mb_strtolower($canonical) === mb_strtolower($entityId)) {
            return null;
        }

        return $this->redirect(
            $this->adminUrlGenerator->setController(static::class)->setAction($action)->setEntityId($canonical)->generateUrl()
        );
    }

    protected function findEntity(string $entityId): object
    {
        $fqcn = static::getEntityFqcn();
        $entity = null;

        if (ctype_digit($entityId)) {
            $entity = $this->entityManager->find($fqcn, $entityId);
        }

        if (null === $entity) {
            $metadata = $this->entityManager->getClassMetadata($fqcn);
            $repository = $this->entityManager->getRepository($fqcn);

            // Asks the resolver rather than holding its own list, so what we
            // RESOLVE always covers what entityIdentifier() GENERATES under
            // the current admin.url_identifier config - and then some: this
            // list is deliberately the WIDER one, so narrowing the config
            // shortens new URLs without 404ing links already in the wild.
            foreach ($this->fieldValueResolver->resolvableIdentifierFieldsFor($fqcn) as $field) {
                if (!$metadata->hasField($field)) {
                    continue;
                }

                $entity = $repository->findOneBy([$field => $entityId]);
                if (null !== $entity) {
                    break;
                }
            }
        }

        // Last resort: a non-numeric primary key (a string id) still
        // resolves, and so does a numeric one whose row was missed above.
        $entity ??= $this->entityManager->find($fqcn, $entityId);

        if (null === $entity) {
            throw $this->createNotFoundException(sprintf('No "%s" found for identifier "%s".', $fqcn, $entityId));
        }

        return $entity;
    }

    protected function denyAccessUnlessGrantedToRun(Crud $crud): void
    {
        $entityPermission = $crud->getEntityPermission();
        if (null !== $entityPermission && !$this->isGranted($entityPermission)) {
            throw $this->createAccessDeniedException(sprintf('Access to "%s" requires "%s".', static::getEntityFqcn(), $entityPermission));
        }

        $actionName = $crud->getCurrentAction();
        $permission = null !== $actionName ? $this->getActionsConfig()->getEffectivePermission($actionName) : null;
        if (null !== $permission && !$this->isGranted($permission)) {
            throw $this->createAccessDeniedException(sprintf('"%s" requires "%s".', $actionName, $permission));
        }
    }

    protected function redirectAfterSubmit(Request $request, object $entity): Response
    {
        $submitAction = $request->request->getString('submit_action', Action::SAVE_AND_RETURN);
        $url = $this->adminUrlGenerator->setController(static::class);

        // Same slug/uuid-first identifier the rest of the admin links
        // with, so "save and continue" lands on the readable URL.
        $id = $this->fieldValueResolver->entityIdentifier($entity);

        return $this->redirect(match ($submitAction) {
            Action::SAVE_AND_CONTINUE => $url->setAction(Action::EDIT)->setEntityId($id)->generateUrl(),
            Action::SAVE_AND_ADD_ANOTHER => $url->setAction(Action::NEW)->generateUrl(),
            default => $url->setAction(Action::INDEX)->generateUrl(),
        });
    }

    /**
     * The superadmin-customizable title/description for THIS CRUD page.
     *
     * Keyed by the CRUD's URL slug (LayoutScope::CRUD), not by a sidebar
     * menu item: the sidebar-backed customization the system pages use is
     * unreachable here because most CRUD pages have no menu entry at all,
     * so nothing is ever marked selected and the editable header never
     * renders. The slug is stable, unique per CRUD, and already the
     * identity the router uses.
     *
     * Returns the stored item plus the key, so a template can render an
     * editable header even when nothing has been customized yet (the key
     * is what the in-place editor posts back).
     */
    protected function crudPageCustomization(): array
    {
        // The lookup itself is shared with the system pages (settings, API
        // keys), which hang the very same customization off a "system/<page>"
        // key instead of a CRUD slug - see PageCustomization. Only the naming
        // is local: the CRUD templates have always read crud_* variables.
        $customization = $this->pageCustomization->resolve($this->routeRegistry->getSlug(static::class));

        return [
            'crud_page_key' => $customization['page_key'],
            'crud_page' => $customization['page'],
            'crud_action_order' => $customization['action_order'],
            'crud_action_icons' => $customization['action_icons'],
            'crud_action_hidden' => $customization['action_hidden'],
        ];
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
            'customize_enabled' => $this->isGranted(\Base\Enum\UserRole::SUPERADMIN),
        ] + $this->crudPageCustomization());
    }
}
