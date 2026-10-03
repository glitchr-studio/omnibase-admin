<?php

namespace Base\Admin\Settings;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * A section of the back office's system pages (Base\Admin\Controller\
 * SystemController): the settings page (/admin/settings) or the API keys
 * page (/admin/api-key). Each bundle - and the application - declares its
 * own, as an autoconfigured service:
 *
 *     final class PadletKeySection implements SettingsSectionInterface
 *     {
 *         public function getPage(): string { return self::API_KEYS; }
 *         public function getFields(): array
 *         {
 *             return ['api.padlet.key' => ['required' => false, 'label' => 'Padlet — clé API']];
 *         }
 *     }
 *
 * Fields are LayoutSettingListType's: a setting path => its options
 * ('label', 'form_type', 'translatable', 'required', ...), plus 'roles' - the
 * role a field is shown to. On the API keys page a field is a revealable
 * password field unless it says otherwise, and its value is stored secure.
 * Sections come in their priority order (#[AsTaggedItem(priority: ...)]):
 * omnibase/admin's own at 100, then the bundles', then the application's.
 */
#[AutoconfigureTag('base.admin.settings_section')]
interface SettingsSectionInterface
{
    public const SETTINGS = 'settings';
    public const API_KEYS = 'apikey';

    /** self::SETTINGS or self::API_KEYS. */
    public function getPage(): string;

    /** @return array<string, array<string, mixed>> setting path => field options */
    public function getFields(): array;
}
