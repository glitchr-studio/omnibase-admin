<?php

namespace Base\Admin\Security;

use Base\Admin\Config\Action;
use Base\Admin\Config\Menu\MenuItem;
use Base\Field\FieldDescriptor;
use Base\Field\FieldInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Votes on the five admin permissions by delegating each subject's own
 * declared permission to Symfony Security. No context service needed:
 * every check receives its subject explicitly.
 */
class SecurityVoter extends Voter
{
    public function __construct(protected readonly AuthorizationCheckerInterface $authorizationChecker)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return Permission::exists($attribute);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        return match ($attribute) {
            Permission::EA_VIEW_MENU_ITEM => $this->voteOnPermission($subject instanceof MenuItem ? $subject->getPermission() : null),
            Permission::EA_EXECUTE_ACTION => $this->voteOnExecuteAction($subject),
            Permission::EA_VIEW_FIELD => $this->voteOnViewField($subject),
            Permission::EA_ACCESS_ENTITY => $this->voteOnPermission(\is_array($subject) ? ($subject['permission'] ?? null) : null, \is_array($subject) ? ($subject['entity'] ?? null) : $subject),
            Permission::EA_EXIT_IMPERSONATION => $this->authorizationChecker->isGranted('IS_IMPERSONATOR'),
            default => true,
        };
    }

    protected function voteOnExecuteAction(mixed $subject): bool
    {
        $action = \is_array($subject) ? ($subject['action'] ?? null) : $subject;
        if (!$action instanceof Action) {
            return true;
        }

        return $this->voteOnPermission($action->getPermission(), \is_array($subject) ? ($subject['entity'] ?? null) : null);
    }

    protected function voteOnViewField(mixed $subject): bool
    {
        $descriptor = $subject instanceof FieldInterface ? $subject->getAsDto() : $subject;
        if (!$descriptor instanceof FieldDescriptor) {
            return true;
        }

        $permission = $descriptor->getPermission();

        return null === $permission || $this->authorizationChecker->isGranted($permission, $descriptor->getValue());
    }

    protected function voteOnPermission(mixed $permission, mixed $subject = null): bool
    {
        return null === $permission || '' === $permission || $this->authorizationChecker->isGranted($permission, $subject);
    }
}
