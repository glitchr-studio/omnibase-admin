<?php

namespace Base\Admin\Settings;

use Base\Field\Type\BooleanType;
use Base\Field\Type\DateTimePickerType;
use Base\Field\Type\ImageType;
use Base\Field\Type\RouteType;
use Base\Field\Type\SelectType;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * omnibase's own settings, the same on every site: the logos, the title and
 * slogan, the meta tags, the launch date, access restrictions, maintenance,
 * account security, the mail sender. Explicit labels (LayoutSettingListType
 * would otherwise derive "Meta - Author" from the path).
 */
#[AsTaggedItem(priority: 100)]
final class SiteSettingsSection implements SettingsSectionInterface
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    public function getPage(): string
    {
        return self::SETTINGS;
    }

    public function getFields(): array
    {
        $label = fn (string $key): string => $this->translator->trans('page.settings.field.'.$key, [], 'admin');

        return [
            'base.settings.logo' => ['translatable' => true, 'multiple' => false, 'form_type' => ImageType::class, 'label' => $label('logo')],
            'base.settings.logo.admin' => ['form_type' => ImageType::class, 'multiple' => false, 'required' => false, 'label' => $label('logo_admin')],
            'base.settings.logo.email' => ['form_type' => ImageType::class, 'multiple' => false, 'required' => false, 'label' => $label('logo_email')],
            'base.settings.title' => ['translatable' => true, 'label' => $label('title')],
            'base.settings.slogan' => ['translatable' => true, 'required' => false, 'label' => $label('slogan')],
            'base.settings.meta.author' => ['translatable' => true, 'required' => false, 'label' => $label('meta_author')],
            'base.settings.meta.description' => ['form_type' => TextareaType::class, 'translatable' => true, 'required' => false, 'label' => $label('meta_description')],
            'base.settings.meta.keywords' => ['form_type' => SelectType::class, 'required' => false, 'tags' => true, 'tokenSeparators' => [',', ';'], 'multiple' => true, 'translatable' => true, 'label' => $label('meta_keywords')],
            'base.settings.launchdate' => ['form_type' => DateTimePickerType::class, 'label' => $label('launchdate')],
            'base.settings.launchdate.redirect_on_deny' => ['form_type' => BooleanType::class, 'label' => $label('launchdate_redirect_on_deny')],
            'base.settings.access_restriction.redirect_on_deny' => ['roles' => 'ROLE_EDITOR', 'form_type' => RouteType::class, 'required' => false, 'label' => $label('access_redirect_on_deny')],
            'base.settings.access_restriction.public_access' => ['roles' => 'ROLE_SUPERADMIN', 'form_type' => BooleanType::class, 'label' => $label('access_public')],
            'base.settings.access_restriction.user_access' => ['roles' => 'ROLE_SUPERADMIN', 'form_type' => BooleanType::class, 'label' => $label('access_user')],
            'base.settings.access_restriction.admin_access' => ['roles' => 'ROLE_EDITOR', 'form_type' => BooleanType::class, 'label' => $label('access_admin')],
            'base.settings.maintenance' => ['form_type' => BooleanType::class, 'label' => $label('maintenance')],
            'base.settings.maintenance.downtime' => ['form_type' => DateTimePickerType::class, 'required' => false, 'label' => $label('maintenance_downtime')],
            'base.settings.maintenance.uptime' => ['form_type' => DateTimePickerType::class, 'required' => false, 'label' => $label('maintenance_uptime')],
            // Account security: read back through Base\Service\SecurityPolicy,
            // which holds the defaults until this page is first saved.
            'base.settings.security.two_factor' => ['roles' => 'ROLE_SUPERADMIN', 'form_type' => BooleanType::class, 'label' => $label('security_two_factor')],
            'base.settings.security.two_factor.mandatory' => ['roles' => 'ROLE_SUPERADMIN', 'form_type' => BooleanType::class, 'label' => $label('security_two_factor_mandatory')],
            'base.settings.security.passkeys' => ['roles' => 'ROLE_SUPERADMIN', 'form_type' => BooleanType::class, 'label' => $label('security_passkeys')],
            'base.settings.security.new_device_email' => ['roles' => 'ROLE_SUPERADMIN', 'form_type' => BooleanType::class, 'label' => $label('security_new_device_email')],
            'base.settings.security.new_device_prompt' => ['roles' => 'ROLE_SUPERADMIN', 'form_type' => BooleanType::class, 'label' => $label('security_new_device_prompt')],
            'base.settings.mail' => ['form_type' => EmailType::class, 'label' => $label('mail_from')],
            'base.settings.mail.name' => ['translatable' => true, 'label' => $label('mail_name')],
            'base.settings.mail.contact' => ['form_type' => EmailType::class, 'label' => $label('mail_contact')],
        ];
    }
}
