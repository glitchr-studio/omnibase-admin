<?php

namespace Base\Admin\Settings;

use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * omnibase's own API key: Akismet, which reads every comment, question and
 * message a visitor leaves (Base\Service\SpamChecker); typed here it wins
 * over the configuration.
 */
#[AsTaggedItem(priority: 100)]
final class SpamKeySection implements SettingsSectionInterface
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    public function getPage(): string
    {
        return self::API_KEYS;
    }

    public function getFields(): array
    {
        return [
            'api.spam.akismet' => ['required' => false, 'label' => $this->translator->trans('page.apikey.field.akismet', [], 'admin')],
        ];
    }
}
