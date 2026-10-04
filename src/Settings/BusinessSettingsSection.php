<?php

namespace Base\Admin\Settings;

use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\Form\Extension\Core\Type\CountryType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The business behind the site: its phone and its address. Read by
 * glitchr/omnibase's LocalBusiness (the page's JSON-LD: Twig
 * local_business_jsonld()), where what is typed here wins over the
 * configuration (base.local_business); the phone is also the one omnibase's
 * notifier signs its messages with (base.settings.phone).
 */
#[AsTaggedItem(priority: 90)]
final class BusinessSettingsSection implements SettingsSectionInterface
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
            'base.settings.phone' => ['form_type' => TelType::class, 'required' => false, 'label' => $label('phone')],
            'base.settings.address.street' => ['required' => false, 'label' => $label('address_street')],
            'base.settings.address.postal_code' => ['required' => false, 'label' => $label('address_postal_code')],
            'base.settings.address.locality' => ['required' => false, 'label' => $label('address_locality')],
            'base.settings.address.region' => ['required' => false, 'label' => $label('address_region')],
            'base.settings.address.country' => ['form_type' => CountryType::class, 'required' => false, 'label' => $label('address_country')],
        ];
    }
}
