<?php

namespace Base\Admin\Controller;

use Base\Admin\Layout\LayoutConfig;
use Base\Admin\Layout\LayoutScope;
use Base\Admin\Layout\LayoutStore;
use Base\Enum\UserRole;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * The superadmin-only customize-mode save endpoint: one POST per "Done"
 * click (never per-drag - see LayoutStore's cache-invalidation note),
 * scoped to sidebar or dashboard. Registered by AdminRouteLoader alongside
 * the dashboard/CRUD routes - the app needs no routing config of its own.
 */
class LayoutController extends AbstractController
{
    public function __construct(
        protected readonly LayoutStore $layoutStore,
    ) {
    }

    public function save(Request $request, string $scope): JsonResponse
    {
        if (!$this->isGranted(UserRole::SUPERADMIN)) {
            throw $this->createAccessDeniedException('Customizing the admin layout requires ' . UserRole::SUPERADMIN . '.');
        }

        if (!LayoutScope::isValid($scope)) {
            throw $this->createNotFoundException(sprintf('Unknown layout scope "%s".', $scope));
        }

        if (!$this->isCsrfTokenValid('admin-layout', $request->headers->get('X-CSRF-Token', ''))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $data = json_decode($request->getContent(), true);
        // LayoutConfig::fromArray() is the real validation boundary - it
        // whitelists keys/coerces types/caps depth-count regardless of what
        // the request body actually contained, so a malformed body just
        // degrades to an empty config rather than erroring.
        $this->layoutStore->save($scope, LayoutConfig::fromArray($data));

        return $this->json(['ok' => true]);
    }
}
