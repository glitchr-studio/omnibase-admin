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
        protected readonly ?\Base\Service\LocalizerInterface $localizer = null,
        protected readonly ?\Base\Service\IconProvider $iconProvider = null,
    ) {
    }

    /**
     * Searchable icon list for the page-icon picker popover (see
     * layout.html.twig's data-page-icon handler) - backed by base-bundle's
     * own cached FontAwesome metadata (IconProvider), NOT the select2
     * autocomplete endpoint, whose obfuscated-token contract exists for
     * the form widget and has nothing this simple popover needs. Flat,
     * deduped, solid-style entries first (the admin's own icon set is
     * all but exclusively fa-solid), capped small - this is a picker,
     * not a browser.
     */
    public function icons(Request $request): JsonResponse
    {
        if (!$this->isGranted(UserRole::SUPERADMIN)) {
            throw $this->createAccessDeniedException('Customizing the admin layout requires ' . UserRole::SUPERADMIN . '.');
        }

        if (null === $this->iconProvider) {
            return $this->json(['icons' => []]);
        }

        $adapter = $this->iconProvider->getAdapter('fa');
        if (null === $adapter) {
            return $this->json(['icons' => []]);
        }

        $term = mb_strtolower(trim((string) $request->query->get('term', '')));
        $choices = $adapter->getChoices($term);

        // "Solid Style" group first, then the rest in adapter order.
        uksort($choices, fn($a, $b) => (int) !str_starts_with($a, 'Solid') <=> (int) !str_starts_with($b, 'Solid'));

        // The adapter's own matching is substring-anywhere (label AND
        // search terms), so a short query like "key" surfaces Hockey/
        // Turkey/Monkey before the actual Key icon - rank exact-name,
        // then name-prefix, then everything else, before capping.
        $icons = [];
        foreach ($choices as $group) {
            foreach ($group as $label => $class) {
                if (isset($icons[$class])) {
                    continue;
                }
                $lower = mb_strtolower((string) $label);
                $rank = ('' === $term || $lower === $term) ? 0 : (str_starts_with($lower, $term) ? 1 : 2);
                $icons[$class] = ['rank' => $rank, 'class' => $class, 'label' => (string) $label];
            }
        }

        $icons = array_values($icons);
        usort($icons, fn($a, $b) => [$a['rank'], $a['label']] <=> [$b['rank'], $b['label']]);
        $icons = array_map(fn($icon) => ['class' => $icon['class'], 'label' => $icon['label']], \array_slice($icons, 0, 72));

        return $this->json(['icons' => $icons]);
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

        // Sidebar captures never carry label/description/intl (the client's
        // captureLevel() only reads data-widget-label, which sidebar <li>s
        // don't have) - a plain overwrite would silently drop every stored
        // page-title customization on the next "Termine". Merge those keys
        // forward from the currently persisted config for entries that
        // don't bring their own. Dashboard captures DO carry labels
        // explicitly, so their omit-to-reset semantics stay untouched.
        if (LayoutScope::SIDEBAR === $scope && \is_array($data)) {
            $stored = [];
            $index = function (array $items) use (&$index, &$stored): void {
                foreach ($items as $item) {
                    if (\is_string($item['key'] ?? null)) {
                        $stored[$item['key']] = $item;
                    }
                    if (\is_array($item['children'] ?? null)) {
                        $index($item['children']);
                    }
                }
            };
            $index($this->layoutStore->get($scope)->toArray()['items']);

            $merge = function (array &$items) use (&$merge, $stored): void {
                foreach ($items as &$item) {
                    $key = $item['key'] ?? null;
                    if (\is_string($key) && isset($stored[$key])) {
                        foreach (['label', 'description', 'intl', 'icon'] as $field) {
                            if (!\array_key_exists($field, $item) && \array_key_exists($field, $stored[$key])) {
                                $item[$field] = $stored[$key][$field];
                            }
                        }
                    }
                    if (\is_array($item['children'] ?? null)) {
                        $merge($item['children']);
                    }
                }
                unset($item);
            };
            if (\is_array($data['items'] ?? null)) {
                $merge($data['items']);
            }
        }

        // LayoutConfig::fromArray() is the real validation boundary - it
        // whitelists keys/coerces types/caps depth-count regardless of what
        // the request body actually contained, so a malformed body just
        // degrades to an empty config rather than erroring.
        $this->layoutStore->save($scope, LayoutConfig::fromArray($data));

        return $this->json(['ok' => true]);
    }

    /**
     * Title/text edits used to only ever persist via the full save() above
     * - batched behind "Termine", same as position/size/hide/etc - which
     * meant a text edit looked committed (the DOM updates immediately,
     * contentEditable) but was silently lost if you left customize mode
     * any other way (reported live). Narrowly scoped to just 'label'/
     * 'text' rather than a general per-field PATCH: those are the only
     * two edits that visually commit themselves as you type, so they're
     * the only ones a user would reasonably expect to already be saved -
     * position/resize/hide/background still batch via save() above,
     * unchanged, since dragging/resizing has no equivalent "looks done"
     * moment mid-gesture.
     *
     * Read-modify-write against the CURRENTLY PERSISTED config (not
     * whatever's live in the DOM) - deliberately does NOT touch any other
     * item's still-pending position/size/etc, and re-validates the whole
     * result through LayoutConfig::fromArray() rather than hand-rolling
     * the same length-capping/type-coercion fromArray() already does.
     */
    public function quickSave(Request $request, string $scope): JsonResponse
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

        $data = json_decode($request->getContent(), true) ?? [];
        $key = $data['key'] ?? null;
        $field = $data['field'] ?? null;
        $value = $data['value'] ?? null;
        $paneIndex = $data['paneIndex'] ?? null;

        // 'actions' is the one field whose value is a LIST (the ordered
        // action names for a CRUD page's top-right button row), not a
        // string - see the apply() branch below for why it is stored as
        // children rather than as a new schema field.
        if ('actions' === $field) {
            if (!\is_array($value) || $value !== array_values($value)) {
                return $this->json(['error' => 'invalid_field'], JsonResponse::HTTP_NOT_FOUND);
            }

            foreach ($value as $actionName) {
                if (!\is_string($actionName) || '' === $actionName || \strlen($actionName) > 64) {
                    return $this->json(['error' => 'invalid_field'], JsonResponse::HTTP_NOT_FOUND);
                }
            }
        } elseif (!\is_string($key) || '' === $key || !\in_array($field, ['label', 'text', 'description', 'icon'], true) || !\is_string($value)) {
            return $this->json(['error' => 'invalid_field'], JsonResponse::HTTP_NOT_FOUND);
        }

        if (!\is_string($key) || '' === $key) {
            return $this->json(['error' => 'invalid_field'], JsonResponse::HTTP_NOT_FOUND);
        }
        if (null !== $paneIndex && !\is_int($paneIndex)) {
            return $this->json(['error' => 'invalid_field'], JsonResponse::HTTP_NOT_FOUND);
        }

        $array = $this->layoutStore->get($scope)->toArray();

        // A label/description edit belongs to the ADMIN LOCALE it was typed
        // in: the default locale writes the plain keys (the historical
        // shape, and the only one dashboard widgets use), any other locale
        // writes its own entry in the 'intl' map (see LayoutConfig /
        // LayoutArranger). text/paneIndex edits stay locale-agnostic -
        // dashboard widget content never localized.
        $locale = $this->localizer?->getLocale();
        $defaultLocale = $this->localizer ? $this->localizer::getDefaultLocale() : null;
        $intlLocale = (null !== $locale && null !== $defaultLocale && $locale !== $defaultLocale
            && \in_array($field, ['label', 'description'], true) && null === $paneIndex)
            ? $locale : null;

        // Recursive, not a flat top-level scan: sidebar items mostly live
        // as CHILDREN of their section's entry (see MenuBuilder::
        // groupIntoSections()), and a page-title edit (data-page-title)
        // targets exactly such a nested item.
        $apply = function (array &$items) use (&$apply, $key, $field, $value, $paneIndex, $intlLocale): bool {
            foreach ($items as &$item) {
                if (($item['key'] ?? null) === $key) {
                    if (null !== $paneIndex) {
                        // A composite pane's own title (see composite.html.twig's
                        // data-pane-index / layout.html.twig's input handler for
                        // the matching client-side routing) - lives under
                        // params.panes[n].label, never on the composite's own
                        // top-level label.
                        $item['params']['panes'][$paneIndex]['label'] = $value;
                    } elseif ('text' === $field) {
                        // "Generic" widget's own free-form body (see
                        // welcome.html.twig / data-widget-text).
                        $item['params']['text'] = $value;
                    } elseif (null !== $intlLocale) {
                        $item['intl'][$intlLocale][$field] = $value;
                    } elseif ('description' === $field) {
                        // A page's own customized description (see MenuItem::
                        // $description / the data-page-desc handler).
                        $item['description'] = $value;
                    } elseif ('actions' === $field) {
                        // Stored as CHILDREN, not as a bespoke schema field:
                        // LayoutConfig already sanitizes children as an
                        // ordered list of keyed entries, which is exactly
                        // what an action order is. Reusing it means no
                        // change to the config validator and no new shape
                        // for LayoutArranger to learn - the key is the
                        // action name and the array order IS the button
                        // order. Locale-agnostic, like 'icon'.
                        $item['children'] = array_map(static fn (string $name): array => ['key' => $name], $value);
                    } elseif ('icon' === $field) {
                        // Page/menu icon - locale-agnostic by design (see
                        // LayoutConfig's icon-alone branch), so it never
                        // routes through the intl map above.
                        $item['icon'] = $value;
                    } else {
                        $item['label'] = $value;
                    }

                    return true;
                }

                if (\is_array($item['children'] ?? null) && $apply($item['children'])) {
                    return true;
                }
            }

            return false;
        };

        $found = $apply($array['items']);

        if (!$found) {
            // label/description edits target a CODE-DEFINED item (a menu
            // entry, a page heading) that legitimately may never have been
            // stored yet - nothing else about it was ever customized. An
            // upsert of just this one field is exactly the same shape a
            // full save would produce for it; LayoutConfig::fromArray()
            // below normalizes it like everything else. parentKey places
            // the entry under its section's own entry (created as a bare
            // stub if the section itself was never customized either) -
            // LayoutArranger matches children per level, a top-level entry
            // for a nested item would be silently dropped as stale.
            // text/paneIndex stay strict: they only ever mean something
            // for an ad-hoc/composite widget, which by definition already
            // has a stored entry - a miss there is a real client bug, not
            // a fresh item.
            if (null === $paneIndex && 'actions' === $field) {
                // Same first-customization case as label/description below:
                // a CRUD page whose buttons are reordered before anything
                // else about it was ever touched has no stored entry yet.
                $array['items'][] = ['key' => $key, 'children' => array_map(
                    static fn (string $name): array => ['key' => $name],
                    $value
                )];
            } elseif (null === $paneIndex && \in_array($field, ['label', 'description', 'icon'], true)) {
                $entry = null !== $intlLocale
                    ? ['key' => $key, 'intl' => [$intlLocale => [$field => $value]]]
                    : ['key' => $key, $field => $value];
                $parentKey = $data['parentKey'] ?? null;
                $inserted = false;

                if (\is_string($parentKey) && '' !== $parentKey) {
                    foreach ($array['items'] as &$item) {
                        if (($item['key'] ?? null) === $parentKey) {
                            $item['children'][] = $entry;
                            $inserted = true;
                            break;
                        }
                    }
                    unset($item);

                    if (!$inserted) {
                        $array['items'][] = ['key' => $parentKey, 'children' => [$entry]];
                        $inserted = true;
                    }
                }

                if (!$inserted) {
                    $array['items'][] = $entry;
                }
            } else {
                return $this->json(['error' => 'unknown_item'], JsonResponse::HTTP_NOT_FOUND);
            }
        }

        $this->layoutStore->save($scope, LayoutConfig::fromArray($array));

        return $this->json(['ok' => true]);
    }
}
