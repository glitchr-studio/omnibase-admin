<?php

namespace Base\Admin\Controller\Crud;

use Base\Admin\Attribute\AdminAction;
use Base\Admin\Config\Action;
use Base\Admin\Config\Actions;
use Base\Admin\Config\Crud;
use Base\Admin\Controller\AbstractCrudController;
use Base\Admin\Filter\Filters;
use Base\Entity\User\Complaint;
use Base\Field\DateTimeField;
use Base\Field\IdField;
use Base\Field\TextareaField;
use Base\Field\TextField;
use Base\Security\Voter\ComplaintVoter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reports ("signalements", Base\Entity\User\Complaint): what a member reported
 * about another one or about something, read and settled here. Who sees one is
 * ComplaintVoter's call - the staff above the person it is about, never that
 * person. Nobody writes one here: they come from the site (a forum, a game
 * world, a comment); the staff marks one handled or dismissed, with an answer,
 * or opens it again. The dashboard tile is ComplaintsWidgetType.
 */
class ComplaintCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Complaint::class;
    }

    public static function getPreferredIcon(): ?string
    {
        return 'fa-solid fa-hand';
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setEntityPermission(ComplaintVoter::READ)
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setEntityLabelInSingular('@admin.complaint.singular')
            ->setEntityLabelInPlural('@admin.complaint.plural');
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add('status')->add('source');
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnIndex();
        yield DateTimeField::new('createdAt', '@admin.complaint.created_at');
        yield TextField::new('statusLabel', '@admin.complaint.status');
        yield TextField::new('complainantName', '@admin.complaint.complainant');
        yield TextField::new('targetName', '@admin.complaint.target');
        yield TextField::new('targetStatus', '@admin.complaint.target_status')->onlyOnDetail();
        yield TextField::new('source', '@admin.complaint.source');
        yield TextField::new('locationName', '@admin.complaint.location')->hideOnIndex();
        yield TextareaField::new('text', '@admin.complaint.text');
        yield TextareaField::new('answer', '@admin.complaint.answer')->hideOnIndex();
        yield TextField::new('handledBy', '@admin.complaint.handled_by')->onlyOnDetail();
        yield DateTimeField::new('handledAt', '@admin.complaint.handled_at')->onlyOnDetail();
    }

    public function configureActions(Actions $actions): Actions
    {
        // Reports come from the site: read and settled here, never written here.
        $actions = parent::configureActions($actions)
            ->disable(Action::NEW, Action::EDIT, Action::DELETE, Action::BATCH_DELETE)
            ->setPermissions([Action::DETAIL => ComplaintVoter::READ, 'handle' => ComplaintVoter::HANDLE, 'dismiss' => ComplaintVoter::HANDLE, 'reopen' => ComplaintVoter::HANDLE]);
        foreach ([Actions::PAGE_INDEX, Actions::PAGE_DETAIL] as $page) {
            $actions
                ->add($page, Action::new('handle', '@admin.complaint.action.handle', 'fa-solid fa-check')->linkToCrudAction('handle'))
                ->add($page, Action::new('dismiss', '@admin.complaint.action.dismiss', 'fa-solid fa-xmark')->linkToCrudAction('dismiss'))
                ->add($page, Action::new('reopen', '@admin.complaint.action.reopen', 'fa-solid fa-rotate-left')->linkToCrudAction('reopen'));
        }

        return $actions;
    }

    #[AdminAction('/{entityId}/handle')]
    public function handle(Request $request, string $entityId): Response
    {
        return $this->settle($request, $entityId, Complaint::HANDLED, '@admin.complaint.flash.handled');
    }

    #[AdminAction('/{entityId}/dismiss')]
    public function dismiss(Request $request, string $entityId): Response
    {
        return $this->settle($request, $entityId, Complaint::DISMISSED, '@admin.complaint.flash.dismissed');
    }

    #[AdminAction('/{entityId}/reopen')]
    public function reopen(Request $request, string $entityId): Response
    {
        return $this->settle($request, $entityId, Complaint::OPEN, '@admin.complaint.flash.reopened');
    }

    private function settle(Request $request, string $entityId, string $status, string $flash): Response
    {
        /** @var Complaint $complaint */
        $complaint = $this->findEntity($entityId);
        $this->denyAccessUnlessGranted(ComplaintVoter::HANDLE, $complaint);
        $user = $this->getUser();
        $complaint->settle($status, $user instanceof \Base\Entity\User ? $user : null, $request->request->get('answer') ?? $complaint->getAnswer());
        $this->entityManager->flush();
        $this->addFlash('success', $flash);

        return $this->redirectToIndex();
    }
}
