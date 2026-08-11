<?php

namespace Base\Admin\Widget;

use Base\Enum\ThreadState;
use Base\Service\Model\LinkableInterface;
use Doctrine\ORM\EntityManagerInterface;

/**
 * What EntityViewsWidgetType's own settings picker (and any future "pick a
 * trackable page" feature) chooses from: every Doctrine-mapped entity class
 * this app happens to have that implements Base\Service\Model\LinkableInterface
 * (a __toLink() a real request path can be tracked under, plus __toString()
 * for a human-readable option label) - discovered generically off Doctrine's
 * own metadata, not a hardcoded list. In this app that resolves to every
 * Base\Entity\Thread subtype (Article, Destination, Gallery, Comment, ...)
 * PLUS User side by side - the two class FAMILIES this was generalized to
 * comply with (was Article-only) - but nothing here actually references
 * either Thread or User by name: a third, unrelated LinkableInterface
 * entity added later needs zero changes here to become pickable too.
 *
 * Thread itself is never offered (it has no LinkableInterface of its own -
 * only its concrete subtypes each implement __toLink()/__toString()
 * individually), so this naturally lists only the concrete, meaningful
 * types an admin would actually want traffic for.
 */
final class LinkableEntityRegistry
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * @return array<string, string> entity FQCN => humanized short class
     *         name (e.g. "Article", "Comment reply"), sorted alphabetically
     *         by that label - this package stays entity-agnostic (no
     *         per-class translation catalog to keep in sync), matching the
     *         same auto-humanized-label convention every field/column in
     *         this admin already falls back to.
     */
    public function getPickableClasses(): array
    {
        $classes = [];
        foreach ($this->em->getMetadataFactory()->getAllMetadata() as $metadata) {
            $class = $metadata->getName();
            if (!\is_a($class, LinkableInterface::class, true)) {
                continue;
            }
            $classes[$class] = $this->humanize($class);
        }
        \asort($classes);

        return $classes;
    }

    /**
     * Up to $limit instances of $class, newest id first - the option list
     * for the picker's second (instance) select. Silently empty for a
     * class that isn't actually pickable (untrusted input boundary, same
     * defensive posture as DashboardWidgetController's own sanitizers)
     * rather than throwing.
     *
     * @return array<int, LinkableInterface>
     */
    public function findInstances(string $class, int $limit = 200): array
    {
        if (!\is_a($class, LinkableInterface::class, true)) {
            return [];
        }

        return $this->em->getRepository($class)->findBy([], ['id' => 'DESC'], $limit);
    }

    /**
     * Every $class instance's own __toLink(), for the "all $class,
     * summed" aggregate mode - Thread subtypes are narrowed to PUBLISH
     * state (a draft/deleted page has no real traffic worth summing in,
     * mirrors ArticleViewsWidgetType::ALL_ARTICLES's prior behavior
     * exactly); a non-Thread class (User) has no such state to filter on
     * and lists every instance.
     *
     * @return string[]
     */
    public function allPaths(string $class): array
    {
        if (!\is_a($class, LinkableInterface::class, true)) {
            return [];
        }

        $criteria = \is_subclass_of($class, \Base\Entity\Thread::class)
            ? ['state' => ThreadState::PUBLISH]
            : [];

        $entities = $this->em->getRepository($class)->findBy($criteria);

        return \array_values(\array_filter(\array_map(
            fn (LinkableInterface $entity) => $entity->__toLink(),
            $entities,
        )));
    }

    public function find(string $class, int $id): ?LinkableInterface
    {
        if (!\is_a($class, LinkableInterface::class, true)) {
            return null;
        }

        $entity = $this->em->getRepository($class)->find($id);

        return $entity instanceof LinkableInterface ? $entity : null;
    }

    private function humanize(string $class): string
    {
        $short = (new \ReflectionClass($class))->getShortName();

        return \trim((string) \preg_replace('/(?<!^)[A-Z]/', ' $0', $short));
    }
}
