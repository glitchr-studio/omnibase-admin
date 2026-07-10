<?php

namespace Base\Admin\Config;

use InvalidArgumentException;

use function Symfony\Component\Translation\t;

/**
 * Per-page action registry. One class holds the collection state directly
 * (no ActionConfigDto behind it); built-in actions are created lazily with
 * sensible defaults and can be reconfigured through the update() callable.
 */
class Actions
{
    public const PAGE_INDEX = 'index';
    public const PAGE_DETAIL = 'detail';
    public const PAGE_EDIT = 'edit';
    public const PAGE_NEW = 'new';

    /** @var array<string, array<string, Action>> page => name => Action */
    protected array $actions = [];

    /** @var array<string, string[]> page => disabled action names */
    protected array $disabledActions = [];

    /** @var array<string, string> action name => required permission */
    protected array $permissions = [];

    public static function new(): static
    {
        return new static();
    }

    public function add(string $pageName, Action|string $actionNameOrObject, ?string $actionIcon = null, ?callable $callable = null): static
    {
        $action = \is_string($actionNameOrObject)
            ? $this->createBuiltInAction($pageName, $actionNameOrObject)
            : $actionNameOrObject;

        $this->actions[$pageName][$action->getName()] = $action;

        if (null !== $actionIcon) {
            $action->setIcon($actionIcon);
        }

        if (null !== $callable) {
            $this->update($pageName, $action->getName(), $callable);
        }

        return $this;
    }

    public function update(string $pageName, string $actionName, callable $callable): static
    {
        $action = $this->actions[$pageName][$actionName] ?? null;
        if (null === $action) {
            throw new InvalidArgumentException(sprintf('The "%s" action does not exist in the "%s" page, so you cannot update it. Instead, add the action with the "add()" method.', $actionName, $pageName));
        }

        $this->actions[$pageName][$actionName] = $callable($action);

        return $this;
    }

    public function remove(string $pageName, string $actionName): static
    {
        unset($this->actions[$pageName][$actionName]);
        return $this;
    }

    public function disable(string ...$actionNames): static
    {
        foreach (array_keys($this->actions) + [self::PAGE_INDEX => null, self::PAGE_DETAIL => null, self::PAGE_EDIT => null, self::PAGE_NEW => null] as $pageName => $_) {
            foreach ($actionNames as $actionName) {
                $this->disabledActions[$pageName][] = $actionName;
                unset($this->actions[$pageName][$actionName]);
            }
        }

        return $this;
    }

    public function isDisabled(string $pageName, string $actionName): bool
    {
        return \in_array($actionName, $this->disabledActions[$pageName] ?? [], true);
    }

    public function setPermission(string $actionName, string $permission): static
    {
        $this->permissions[$actionName] = $permission;
        return $this;
    }

    public function setPermissions(array $permissions): static
    {
        foreach ($permissions as $actionName => $permission) {
            $this->setPermission($actionName, $permission);
        }

        return $this;
    }

    public function getPermission(string $actionName): ?string
    {
        return $this->permissions[$actionName] ?? null;
    }

    /**
     * @return array<string, Action>
     */
    public function getAll(string $pageName): array
    {
        return $this->actions[$pageName] ?? [];
    }

    /**
     * @return array<string, Action>
     */
    public function getEntityActions(string $pageName): array
    {
        return array_filter($this->getAll($pageName), fn (Action $a) => $a->isEntityAction());
    }

    /**
     * @return array<string, Action>
     */
    public function getGlobalActions(string $pageName): array
    {
        return array_filter($this->getAll($pageName), fn (Action $a) => $a->isGlobalAction());
    }

    /**
     * @return array<string, Action>
     */
    public function getBatchActions(string $pageName): array
    {
        return array_filter($this->getAll($pageName), fn (Action $a) => $a->isBatchAction());
    }

    /**
     * Registers the conventional set of actions for every page.
     */
    public function addDefaults(): static
    {
        return $this
            ->add(self::PAGE_INDEX, Action::NEW)
            ->add(self::PAGE_INDEX, Action::EDIT)
            ->add(self::PAGE_INDEX, Action::DELETE)
            ->add(self::PAGE_INDEX, Action::DETAIL)

            ->add(self::PAGE_DETAIL, Action::EDIT)
            ->add(self::PAGE_DETAIL, Action::DELETE)
            ->add(self::PAGE_DETAIL, Action::INDEX)

            ->add(self::PAGE_EDIT, Action::SAVE_AND_RETURN)
            ->add(self::PAGE_EDIT, Action::SAVE_AND_CONTINUE)
            ->add(self::PAGE_EDIT, Action::INDEX)

            ->add(self::PAGE_NEW, Action::SAVE_AND_RETURN)
            ->add(self::PAGE_NEW, Action::SAVE_AND_ADD_ANOTHER)
            ->add(self::PAGE_NEW, Action::SAVE_AND_CONTINUE)
            ->add(self::PAGE_NEW, Action::INDEX);
    }

    protected function createBuiltInAction(string $pageName, string $actionName): Action
    {
        return match ($actionName) {
            Action::SEPARATOR => Action::new(Action::SEPARATOR, '')
                ->setCssClass('action-separator')
                ->displayAsSeparator()
                ->linkToCrudAction(Action::SEPARATOR),

            Action::INDEX => Action::new(Action::INDEX, t('action.index', domain: 'admin'))
                ->setCssClass('action-index btn btn-secondary')
                ->linkToCrudAction(Action::INDEX),

            Action::NEW => Action::new(Action::NEW, t('action.new', domain: 'admin'))
                ->setCssClass('action-new btn btn-primary')
                ->createAsGlobalAction()
                ->linkToCrudAction(Action::NEW),

            Action::DETAIL => Action::new(Action::DETAIL, t('action.detail', domain: 'admin'))
                ->setCssClass('action-detail')
                ->linkToCrudAction(Action::DETAIL),

            Action::EDIT => Action::new(Action::EDIT, t('action.edit', domain: 'admin'))
                ->setCssClass('action-edit')
                ->linkToCrudAction(Action::EDIT),

            Action::DELETE => Action::new(Action::DELETE, t('action.delete', domain: 'admin'), \in_array($pageName, [self::PAGE_DETAIL, self::PAGE_EDIT], true) ? 'fa fa-fw fa-trash-o' : null)
                ->setCssClass((\in_array($pageName, [self::PAGE_DETAIL, self::PAGE_EDIT], true) ? 'btn btn-secondary pr-0 text-danger' : 'text-danger') . ' action-delete')
                ->linkToCrudAction(Action::DELETE),

            Action::BATCH_DELETE => Action::new(Action::BATCH_DELETE, t('action.delete', domain: 'admin'))
                ->setCssClass('action-batch-delete btn text-danger')
                ->createAsBatchAction()
                ->linkToCrudAction(Action::BATCH_DELETE),

            Action::SAVE_AND_RETURN => Action::new(Action::SAVE_AND_RETURN, t(self::PAGE_EDIT === $pageName ? 'action.save' : 'action.create', domain: 'admin'))
                ->setCssClass('action-saveAndReturn')
                ->addCssClass('btn btn-primary action-save')
                ->setHtmlAttributes(['name' => 'submit_action', 'value' => $actionName])
                ->renderAsButton()
                ->linkToCrudAction(self::PAGE_EDIT === $pageName ? Action::EDIT : Action::NEW),

            Action::SAVE_AND_CONTINUE => Action::new(Action::SAVE_AND_CONTINUE, t(self::PAGE_EDIT === $pageName ? 'action.save_and_continue' : 'action.create_and_continue', domain: 'admin'), 'fa-regular fa-edit')
                ->setCssClass('action-saveAndContinue')
                ->addCssClass('btn btn-secondary action-save')
                ->setHtmlAttributes(['name' => 'submit_action', 'value' => $actionName])
                ->renderAsButton()
                ->linkToCrudAction(self::PAGE_EDIT === $pageName ? Action::EDIT : Action::NEW),

            Action::SAVE_AND_ADD_ANOTHER => Action::new(Action::SAVE_AND_ADD_ANOTHER, t('action.create_and_add_another', domain: 'admin'))
                ->setCssClass('action-saveAndAddAnother')
                ->addCssClass('btn btn-secondary action-save')
                ->setHtmlAttributes(['name' => 'submit_action', 'value' => $actionName])
                ->renderAsButton()
                ->linkToCrudAction(Action::NEW),

            default => Action::new($actionName)->linkToCrudAction($actionName),
        };
    }
}
