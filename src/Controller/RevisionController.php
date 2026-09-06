<?php

namespace Base\Admin\Controller;

use Base\Entity\Extension\Revision;
use Base\Enum\UserRole;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Serves the raw previous value behind one entry of a field's history badge.
 *
 * Read-only/GET, no CSRF, same shape as AnalyticsController - it hands back
 * data the caller is already allowed to read and edit, and restoring is not
 * what this does: the value goes into the form input, and nothing is written
 * until the editor saves the form like any other change.
 *
 * Values are fetched rather than embedded in the page on purpose. An EditorJS
 * article body runs to tens of kilobytes, and with five revisions per field
 * the badge alone would have added several hundred KB of data attributes to
 * every edit page - for values almost nobody clicks.
 */
class RevisionController extends AbstractController
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function value(Request $request, int $id): JsonResponse
    {
        $this->denyAccessUnlessGranted(UserRole::ADMIN);

        $revision = $this->entityManager->getRepository(Revision::class)->find($id);
        if (!$revision) {
            throw $this->createNotFoundException(\sprintf('No revision #%d.', $id));
        }

        $field = (string) $request->query->get('field', '');
        $entityData = $revision->getEntityData();

        if ($field === '' || !\array_key_exists($field, $entityData)) {
            throw $this->createNotFoundException(\sprintf('Revision #%d records no "%s".', $id, $field));
        }

        return new JsonResponse([
            'field' => $field,
            'hash' => $revision->getHashShort(),
            'value' => $entityData[$field][0] ?? null,
        ]);
    }
}
