<?php

namespace Base\Admin\Controller;

use Base\Admin\Context\AdminContext;
use Base\Admin\Menu\MenuBuilder;
use Base\Entity\Extension\TrashBall;
use Base\Enum\UserRole;
use Base\Service\TrashManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The trash: what soft deletion put aside, and the two things you can do with
 * it - put it back, or destroy it.
 *
 * Listing is GET; both writes are POST + CSRF, because unlike the read-only
 * endpoints elsewhere in this bundle these change the database, and one of
 * them does so beyond recovery.
 */
class TrashController extends AbstractController
{
    private const PER_PAGE = 50;

    public function __construct(
        private readonly TrashManager $trashManager,
        private readonly EntityManagerInterface $entityManager,
        private readonly TranslatorInterface $translator,
        private readonly AdminContext $adminContext,
        private readonly MenuBuilder $menuBuilder,
    ) {
    }

    public function index(Request $request): Response
    {
        $this->denyAccessUnlessGranted(UserRole::ADMIN);

        $page = max(1, (int) $request->query->get('page', 1));
        $total = $this->trashManager->count();
        $trashBalls = $this->trashManager->contents(null, self::PER_PAGE, ($page - 1) * self::PER_PAGE);

        // The entity itself is deliberately NOT loaded per row. Its label was
        // snapshotted when it went in, which is what keeps the listing
        // readable for something whose __toString() leans on translations or
        // on a parent that has since gone - and keeps a page of 50 entries
        // from being 50 hydrations of unrelated entity types.
        $rows = [];
        foreach ($trashBalls as $trashBall) {
            $entityData = $trashBall->getEntityData();

            $rows[] = [
                'id' => $trashBall->getId(),
                'label' => $entityData['label'] ?? null,
                'class' => $trashBall->getEntityClass(),
                'shortClass' => $this->shortClass($trashBall->getEntityClass()),
                'entityId' => $trashBall->getEntityId(),
                'deletedAt' => $trashBall->getCreatedAt(),
                'permanentAfter' => $trashBall->getPermanentAfter(),
                'expired' => $trashBall->getPermanentAfter() !== null && $trashBall->getPermanentAfter() <= new \DateTime(),
                'by' => $trashBall->getInitiator()?->getUsername(),
                'impersonator' => $trashBall->getImpersonator()?->getUsername(),
            ];
        }

        // Same pair AbstractCrudController::renderCrud() and the app's own
        // system pages both make. The layout builds the sidebar nav from
        // admin_context.mainMenu and gates the account dropdown on
        // admin_context.userMenu - a page that renders without seeding them
        // comes out with an EMPTY sidebar and no account menu, which is
        // exactly how this page first looked.
        if ([] === $this->adminContext->getMainMenu()) {
            $this->adminContext->setMainMenu($this->menuBuilder->buildDefault());
        }
        if ([] === $this->adminContext->getUserMenu()) {
            $this->adminContext->setUserMenu($this->menuBuilder->buildUserMenuDefault($this->getUser()));
        }

        return $this->render('@Admin/page/trash.html.twig', [
            'admin_context' => $this->adminContext,
            'page' => 'trash',
            'rows' => $rows,
            'total' => $total,
            'currentPage' => $page,
            'pageCount' => max(1, (int) ceil($total / self::PER_PAGE)),
        ]);
    }

    public function restore(Request $request, int $id): RedirectResponse
    {
        $this->denyAccessUnlessGranted(UserRole::ADMIN);
        $trashBall = $this->guard($request, $id, 'admin-trash-restore');

        if ($this->trashManager->restore($trashBall)) {
            $this->addFlash('success', $this->translator->trans('page.trash.restored', [], 'admin'));
        } else {
            $this->addFlash('warning', $this->translator->trans('page.trash.restore_failed', [], 'admin'));
        }

        return $this->redirectToRoute('admin_trash');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $this->denyAccessUnlessGranted(UserRole::ADMIN);
        $trashBall = $this->guard($request, $id, 'admin-trash-destroy');

        $this->trashManager->destroy($trashBall);
        $this->addFlash('success', $this->translator->trans('page.trash.destroyed', [], 'admin'));

        return $this->redirectToRoute('admin_trash');
    }

    /**
     * Destroy everything whose grace period has run out - the same thing the
     * cron does, offered here so an admin need not wait for it.
     *
     * Deliberately NOT "destroy everything in the trash": the grace period is
     * the promise soft deletion makes, and a button that voids it for entries
     * put there a minute ago would make the trash a slower hard delete.
     */
    public function empty(Request $request): RedirectResponse
    {
        $this->denyAccessUnlessGranted(UserRole::ADMIN);

        if (!$this->isCsrfTokenValid('admin-trash-empty', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $destroyed = $this->trashManager->purge();
        $this->addFlash('success', $this->translator->trans('page.trash.emptied', ['%count%' => $destroyed], 'admin'));

        return $this->redirectToRoute('admin_trash');
    }

    private function guard(Request $request, int $id, string $tokenId): TrashBall
    {
        if (!$this->isCsrfTokenValid($tokenId . '-' . $id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $trashBall = $this->entityManager->getRepository(TrashBall::class)->find($id);
        if (!$trashBall) {
            throw $this->createNotFoundException(\sprintf('No trash entry #%d.', $id));
        }

        return $trashBall;
    }

    private function shortClass(?string $className): string
    {
        if (!$className) {
            return '?';
        }

        $parts = \explode('\\', $className);

        return \end($parts);
    }
}
