<?php

namespace Base\Admin\Controller;

use Base\Admin\Config\Action;
use Base\Admin\Config\Actions;
use Base\Admin\Context\AdminContext;
use Base\Admin\Layout\PageCustomization;
use Base\Admin\Menu\MenuBuilder;
use Base\Admin\Settings\SettingsSectionInterface;
use Base\Admin\Settings\SettingsSections;
use Base\Database\Attribute\Vault;
use Base\Field\Type\PasswordType;
use Base\Form\Type\LayoutSettingListType;
use Base\Repository\Layout\SettingRepository;
use Base\Service\SettingBagInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Translation\TranslatableMessage;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The system pages of the back office: the settings (/admin/settings) and
 * the API keys (/admin/api-key), the same machinery on every site
 * (SettingBag, LayoutSettingListType). What they hold is declared by
 * sections (Base\Admin\Settings\SettingsSectionInterface, tagged
 * base.admin.settings_section): omnibase's own, each bundle's, the
 * application's. Routed by AdminRouteLoader (admin_settings, admin_apikey,
 * admin_settings_quick); an application that still routes a controller of its
 * own under those names keeps it (its routes load last).
 */
class SystemController extends AbstractController
{
    public function __construct(
        protected readonly SettingBagInterface $settingBag,
        protected readonly SettingRepository $settingRepository,
        protected readonly AdminContext $adminContext,
        protected readonly MenuBuilder $menuBuilder,
        protected readonly TranslatorInterface $translator,
        protected readonly PageCustomization $pageCustomization,
        protected readonly SettingsSections $sections,
    ) {
    }


    public function settings(Request $request): Response
    {
        $fields = $this->sections->fields(SettingsSectionInterface::SETTINGS);

        $this->settingBag->clearAll();

        foreach ($fields as $name => $options) {
            $roles = $options['roles'] ?? null;
            unset($fields[$name]['roles']);
            if (null !== $roles && !$this->isGranted($roles)) {
                unset($fields[$name]);
            }
        }

        $form = $this->createForm(LayoutSettingListType::class, null, ['fields' => $fields]);
        $form->handleRequest($request);

        $fieldNames = array_keys($form->getConfig()->getOption('fields'));
        $settings = [];
        foreach ($this->settingBag->getRawScalar($fieldNames, false) as $setting) {
            if (null !== $setting) {
                $settings[$setting->getPath()] = $setting;
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            foreach ($data as $setting) {
                foreach ($setting->getTranslations() as $translation) {
                    if ($translation->isEmpty()) {
                        $setting->removeTranslation($translation);
                    }
                }
            }

            foreach (array_diff_key($data, $settings) as $setting) {
                $this->settingRepository->persist($setting);
            }

            $this->settingRepository->flush(false); // not clear(): the signed-in admin stays managed for the rest of the request
            $this->addFlash('success', new TranslatableMessage('flash.updated', [], 'admin'));

            return $this->redirectAfterSubmit($request, 'admin_settings');
        }

        // Section headers only - see renderSystemPage()'s own doc comment
        // for why this stays this simple. LayoutSettingListType always
        // splits fields into (at most) two top-level groups by
        // translatability, 'intl'/'unv' - the whole field LIST for this
        // page mixes categories (branding, SEO, access, mail, ...)
        // arbitrarily across that split (e.g. the translatable Logo sits
        // next to the non-translatable Logo - Admin/Email in spirit, but
        // ends up in a different top-level group), so true per-category
        // sections would require reimplementing the shared locale-tab
        // widget (translatable_widget in form_div_layout.html.twig) to
        // support a filtered field subset per group, repeated once per
        // locale tab-pane, plus care so the SAME global tab-switcher still
        // drives every group's translatable fields in lockstep - real
        // functional risk to something that currently works, for a purely
        // cosmetic reorganization. Labeling the two EXISTING structural
        // groups the form already produces gets 90% of the visual benefit
        // (a wall of 20 fields becomes two clearly-headed sections) at
        // zero risk to that mechanism.
        $groups = ['intl' => 'content', 'unv' => 'technical'];

        return $this->renderSystemPage('settings', $form->createView(), $groups);
    }

    // API keys: the creators' only.
    #[IsGranted('ROLE_SUPERADMIN')]
    public function apiKey(Request $request): Response
    {
        $this->settingBag->clearAll();

        $fields = [];
        foreach ($this->sections->fields(SettingsSectionInterface::API_KEYS) as $key => $field) {
            $roles = $field['roles'] ?? null;
            unset($field['roles']);
            if (null === $roles || $this->isGranted($roles)) {
                $fields[$key] = $field;
            }
        }

        foreach ($fields as $key => $field) {
            $fields[$key]['form_type'] ??= PasswordType::class;
            if (PasswordType::class === $fields[$key]['form_type']) {
                $fields[$key] += [
                    'inline' => true, 'revealer' => true, 'repeater' => false,
                    'min_length' => 0, 'max_strength' => 0, 'secure' => false,
                    'hint' => false, 'autocomplete' => false,
                ];
            }
        }

        $form = $this->createForm(LayoutSettingListType::class, null, ['fields' => $fields]);
        $form->handleRequest($request);

        // The keys typed here are sealed by omnibase's #[Vault], which refuses
        // to store them in clear: without the environment's key pair, say so
        // before anything is saved rather than fail in the middle of a flush.
        $vault = new Vault();
        if ($form->isSubmitted() && $form->isValid() && method_exists($vault, 'canSeal') && !$vault->canSeal()) {
            $this->addFlash('danger', new TranslatableMessage('flash.vault_key_missing', ['%command%' => 'php bin/console secrets:generate-keys --env='.$this->getParameter('kernel.environment')], 'admin'));

            return $this->renderSystemPage('apikey', $form->createView());
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $data = array_filter($form->getData(), function ($value, $key) use ($fields) {
                if ($fields[$key]['required'] ?? true) {
                    return null !== $value && null !== $value->getValue();
                }

                return !($fields[$key]['secure'] ?? true);
            }, ARRAY_FILTER_USE_BOTH);

            $fieldNames = array_keys($form->getConfig()->getOption('fields'));
            $settings = [];
            foreach ($this->settingBag->getRawScalar($fieldNames, false) as $setting) {
                if (null !== $setting) {
                    $settings[$setting->getPath()] = $setting;
                    $setting->setSecure(true);
                }
            }

            foreach (array_diff_key($data, $settings) as $setting) {
                $this->settingRepository->persist($setting);
            }

            $this->settingRepository->flush(false); // not clear(): the signed-in admin stays managed for the rest of the request
            $this->settingBag->clearAll();
            $this->addFlash('success', new TranslatableMessage('flash.updated', [], 'admin'));

            return $this->redirectAfterSubmit($request, 'admin_apikey');
        }

        return $this->renderSystemPage('apikey', $form->createView());
    }

    /**
     * The sidebar brand's title/slogan edit in place (contenteditable,
     * same pattern as the dashboard traffic widget's own title - see
     * layout.html.twig's refreshTitleEditability()/blur handler), rather
     * than sending a superadmin all the way to the full Settings form
     * just to fix a typo in the site name. A whitelist, not "any setting
     * path the client sends": this endpoint has no form/field
     * definitions behind it to constrain what a path even means, unlike
     * settings()/apiKey() above.
     */
    private const QUICK_SETTINGS = ['base.settings.title', 'base.settings.slogan'];

    #[IsGranted('ROLE_SUPERADMIN')]
    public function settingsQuick(Request $request): JsonResponse
    {
        if (!$this->isCsrfTokenValid('admin-settings-quick', $request->headers->get('X-CSRF-Token'))) {
            return $this->json(['error' => 'invalid_csrf'], Response::HTTP_FORBIDDEN);
        }

        $data = \json_decode($request->getContent(), true) ?? [];
        $path = $data['path'] ?? null;
        $value = $data['value'] ?? null;

        if (!\in_array($path, self::QUICK_SETTINGS, true) || !\is_string($value)) {
            return $this->json(['error' => 'invalid_field'], Response::HTTP_NOT_FOUND);
        }

        try {
            $this->settingBag->set($path, \trim($value));
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_CONFLICT);
        }

        return $this->json(['value' => \trim($value)]);
    }

    /**
     * The action row of a system page: the same buttons an entity form
     * carries (see Actions::addSystemDefaults()), rendered in the same
     * place - the page header - by @Admin/page/system.html.twig.
     *
     * Overridable per page rather than fixed, which is the point: a page
     * can drop a button (->remove()), relabel or re-icon one (->update()),
     * or add its own. On top of that, a superadmin reorders the row and
     * changes its icons live in customize mode (persisted per page key,
     * see PageCustomization).
     *
     * Deliberately NOT filtered through Actions::$defaultPermissions like
     * a CRUD's row is: those defaults gate mutating an *entity* on
     * ROLE_SUPERADMIN, whereas access to a system page is already decided
     * by the page's own route/field roles - filtering here would take the
     * save button away from an editor who is allowed on the page.
     */
    protected function configureSystemActions(string $page, Actions $actions): Actions
    {
        return $actions;
    }

    /**
     * Where a save lands, decided by the button that was clicked - the
     * same submit_action contract as AbstractCrudController::
     * redirectAfterSubmit(), with the dashboard standing in for the entity
     * list a system page does not have. Anything else (in particular a
     * bare Enter in a text field, which submits with no action at all)
     * keeps the historical behaviour: stay on the page.
     */
    protected function redirectAfterSubmit(Request $request, string $route): Response
    {
        return Action::SAVE_AND_RETURN === $request->request->getString('submit_action')
            ? $this->redirectToRoute('admin')
            : $this->redirectToRoute($route);
    }

    /**
     * @param array<'intl'|'unv', string> $groups Optional section heading
     *      for the form's own 'intl' (translatable fields) and/or 'unv'
     *      (non-translatable) top-level groups - value is a key resolved
     *      to page.<page>.group.<key> in the template. Empty by default so
     *      apiKey() (5 non-translatable fields, reads fine as one flat
     *      list) doesn't need to pass anything.
     */
    protected function renderSystemPage(string $page, $formView, array $groups = []): Response
    {
        if ([] === $this->adminContext->getMainMenu()) {
            $this->adminContext->setMainMenu($this->menuBuilder->buildDefault());
        }
        // Same as AbstractCrudController::renderCrud()'s own pair of these
        // two calls - without this, admin_context.userMenu stays empty on
        // every system page (settings/apikey), which layout.html.twig's
        // hasUserMenu gate reads to decide whether to render the sidebar's
        // top-left account dropdown at all - it silently vanished (no
        // caret, no menu) specifically on these pages, reported live.
        if ([] === $this->adminContext->getUserMenu()) {
            $this->adminContext->setUserMenu($this->menuBuilder->buildUserMenuDefault($this->getUser()));
        }

        $actions = $this->configureSystemActions($page, Actions::new()->addSystemDefaults());

        return $this->render('@Admin/page/system.html.twig', [
            'admin_context' => $this->adminContext,
            'page' => $page,
            'form' => $formView,
            'groups' => $groups,
            'page_actions' => $actions->getAll(Actions::PAGE_SYSTEM),
            // Same gate AbstractCrudController::renderCrud() passes - without
            // it these pages render no customize toggle script/Done button at
            // all, so the topbar's toggle was dead specifically here.
            'customize_enabled' => $this->isGranted(\Base\Enum\UserRole::SUPERADMIN),
        ]
            // Reorder/re-icon state for that row. Namespaced "system/" so a
            // page name can never collide with a CRUD slug in the shared
            // scope (both are flat keys in LayoutScope::CRUD).
            + $this->pageCustomization->resolve('system/' . $page));
    }
}
