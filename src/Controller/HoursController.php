<?php

namespace Base\Admin\Controller;

use Base\Entity\Hours\SpecialDay;
use Base\Enum\UserRole;
use Base\Repository\Hours\ScopedWeekRepository;
use Base\Repository\Hours\SpecialDayRepository;
use Base\Repository\Hours\WeekDayHoursRepository;
use Base\Service\OpeningHours;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * "Opening hours" (/admin/hours): the usual week, day by day, and the days
 * off it - closed, or open at other hours, for one day or a run of days.
 * What glitchr/omnibase's OpeningHours reads everywhere: the footer, the day
 * an order is for, the time slots, the page's JSON-LD.
 *
 * The site's own hours; with ?scope=store:12, one place's (its own week, or
 * the site's until it has one; its own days off beside the site's).
 *
 * The page is read with GET; every change is a POST with its CSRF token.
 * Routed by AdminRouteLoader (admin_hours, admin_hours_week,
 * admin_hours_special, admin_hours_special_delete).
 */
class HoursController extends AbstractController
{
    /** Slots a day may have: a morning, an afternoon, an evening. */
    public const SLOTS = 3;

    public function __construct(
        private readonly OpeningHours $hours,
        private readonly WeekDayHoursRepository $weekDays,
        private readonly ScopedWeekRepository $scopedWeeks,
        private readonly SpecialDayRepository $specialDays,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function index(Request $request): Response
    {
        $this->denyAccessUnlessGranted(UserRole::ADMIN);
        $scope = $this->scope($request);
        $hours = $this->hours->for($scope);
        $today = new \DateTimeImmutable('today', $hours->timezone());

        return $this->render('@Admin/page/hours.html.twig', [
            'page' => 'hours',
            'scope' => $scope,
            // A place that has no week of its own shows the site's, and says so.
            'own_week' => null === $scope || null !== $this->scopedWeeks->week($scope),
            'week' => $hours->week(),
            'slots' => self::SLOTS,
            'special_days' => $this->specialDays->upcoming($today, 200, $scope),
            'site_special_days' => null !== $scope ? $this->specialDays->upcoming($today, 200) : [],
            'timezone' => $hours->timezone()->getName(),
            'open_now' => $hours->isOpenAt(),
        ]);
    }

    /** The usual week: week[<ISO day>][<n>][open|close], empty rows left out. */
    public function week(Request $request): RedirectResponse
    {
        $this->denyAccessUnlessGranted(UserRole::ADMIN);
        $this->guard($request, 'admin-hours-week');
        $scope = $this->scope($request);

        if (null !== $scope && $request->request->getBoolean('follow_site')) {
            $this->scopedWeeks->forget($scope);
            $this->addFlash('success', new TranslatableMessage('page.hours.flash.follows_site', [], 'admin'));

            return $this->back($scope);
        }

        $week = [];
        foreach ($request->request->all('week') as $day => $rows) {
            foreach (\is_array($rows) ? $rows : [] as $row) {
                $open = trim((string) ($row['open'] ?? ''));
                $close = trim((string) ($row['close'] ?? ''));
                if ('' === $open && '' === $close) {
                    continue;
                }
                $week[(int) $day][] = [substr($open, 0, 5), substr($close, 0, 5)];
            }
        }
        foreach ($week as $day => $slots) {
            usort($slots, static fn (array $a, array $b) => $a[0] <=> $b[0]);
            $week[$day] = $slots;
        }

        try {
            null === $scope ? $this->weekDays->save($week) : $this->scopedWeeks->save($scope, $week);
        } catch (\InvalidArgumentException) {
            // An opening without its closing, a closing before its opening, two slots overlapping.
            $this->addFlash('danger', new TranslatableMessage('page.hours.flash.bad_hours', [], 'admin'));

            return $this->back($scope);
        }

        $this->hours->reset();
        $this->addFlash('success', new TranslatableMessage('page.hours.flash.week_saved', [], 'admin'));

        return $this->back($scope);
    }

    /** A day off, or a run of days: closed, or open at other hours. */
    public function addSpecialDay(Request $request): RedirectResponse
    {
        $this->denyAccessUnlessGranted(UserRole::ADMIN);
        $this->guard($request, 'admin-hours-special');
        $scope = $this->scope($request);
        $in = $request->request;
        $zone = $this->hours->timezone();

        try {
            $from = new \DateTimeImmutable((string) $in->get('from').' 00:00', $zone);
            $until = '' !== trim((string) $in->get('until')) ? new \DateTimeImmutable((string) $in->get('until').' 00:00', $zone) : null;
        } catch (\Exception) {
            $from = null;
        }
        if (null === $from || '' === trim((string) $in->get('from'))) {
            $this->addFlash('danger', new TranslatableMessage('page.hours.flash.bad_date', [], 'admin'));

            return $this->back($scope);
        }

        $day = new SpecialDay($from, $until ?? null, mb_substr(trim((string) $in->get('reason')), 0, 120), $scope);
        if ('hours' === $in->get('mode')) {
            $slots = [];
            foreach ($in->all('hours') as $row) {
                $open = trim((string) ($row['open'] ?? ''));
                $close = trim((string) ($row['close'] ?? ''));
                if ('' !== $open || '' !== $close) {
                    $slots[] = [substr($open, 0, 5), substr($close, 0, 5)];
                }
            }
            try {
                $day->setHours($slots ?: null);
            } catch (\InvalidArgumentException) {
                $slots = [];
            }
            if (!$slots) {
                $this->addFlash('danger', new TranslatableMessage('page.hours.flash.bad_hours', [], 'admin'));

                return $this->back($scope);
            }
        }

        $this->entityManager->persist($day);
        $this->entityManager->flush();
        $this->hours->reset();
        $this->addFlash('success', new TranslatableMessage('page.hours.flash.special_saved', [], 'admin'));

        return $this->back($scope);
    }

    public function deleteSpecialDay(Request $request, int $id): RedirectResponse
    {
        $this->denyAccessUnlessGranted(UserRole::ADMIN);
        $this->guard($request, 'admin-hours-special-delete-'.$id);

        $day = $this->entityManager->find(SpecialDay::class, $id);
        if (!$day) {
            throw $this->createNotFoundException();
        }
        $scope = $day->getScope();

        $this->entityManager->remove($day);
        $this->entityManager->flush();
        $this->hours->reset();
        $this->addFlash('success', new TranslatableMessage('page.hours.flash.special_deleted', [], 'admin'));

        return $this->back($scope);
    }

    private function scope(Request $request): ?string
    {
        $scope = trim((string) ($request->request->get('scope') ?? $request->query->get('scope') ?? ''));

        return '' === $scope ? null : mb_substr($scope, 0, 190);
    }

    private function guard(Request $request, string $tokenId): void
    {
        if (!$this->isCsrfTokenValid($tokenId, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
    }

    private function back(?string $scope): RedirectResponse
    {
        return $this->redirectToRoute('admin_hours', null !== $scope ? ['scope' => $scope] : []);
    }
}
