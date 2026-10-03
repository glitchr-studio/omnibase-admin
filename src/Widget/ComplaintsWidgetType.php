<?php

namespace Base\Admin\Widget;

use Base\Admin\Config\Menu\MenuItem;
use Base\Entity\User\Complaint;
use Base\Repository\User\ComplaintRepository;
use Base\Security\Voter\ComplaintVoter;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * The dashboard's tile for reports: how many wait, the last few the signed-in
 * staff member may read (ComplaintVoter), each opening its page.
 *
 *     yield MenuItem::block('admin_complaints', 'Signalements');
 */
final class ComplaintsWidgetType implements DashboardWidgetTypeInterface
{
    public function __construct(
        private readonly ComplaintRepository $complaints,
        private readonly AuthorizationCheckerInterface $authorization,
    ) {
    }

    public static function getName(): string
    {
        return 'admin_complaints';
    }

    public function getTemplate(): string
    {
        return '@Admin/widget/complaints.html.twig';
    }

    public function getTemplateVars(MenuItem $widget): array
    {
        $open = array_values(array_filter(
            $this->complaints->newest(Complaint::OPEN, null, 200),
            fn (Complaint $complaint) => $this->authorization->isGranted(ComplaintVoter::READ, $complaint),
        ));

        return [
            'open' => \count($open),
            'complaints' => \array_slice($open, 0, 5),
        ];
    }
}
