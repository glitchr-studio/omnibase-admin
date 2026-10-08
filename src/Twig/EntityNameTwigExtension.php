<?php

namespace Base\Admin\Twig;

use Base\Service\Translator;
use Base\Service\TranslatorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * The name a CRUD page gives its entity when configureCrud() set no label:
 * `admin_entity_name(fqcn)` and `admin_entity_name(fqcn, true)` for the plural.
 *
 * The entity's own translation first - the "entities" domain every omnibase
 * bundle and application already fills ("user._plural", "marketplace.order.
 * _singular"...), the one the fields and the flash messages read - and, for
 * an entity nobody named, its class name in plain words ("Wardrobe item"),
 * never the bare class ("WardrobeItem").
 */
class EntityNameTwigExtension extends AbstractExtension
{
    public function __construct(protected readonly ?TranslatorInterface $translator = null)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('admin_entity_name', $this->entityName(...)),
        ];
    }

    public function entityName(?string $entityFqcn, bool $plural = false): string
    {
        if (null === $entityFqcn || '' === $entityFqcn) {
            return '';
        }

        if (null !== $this->translator) {
            // The plural when there is one, else the singular: a list titled
            // "Commande" still beats "Order".
            $nouns = $plural ? [Translator::NOUN_PLURAL, Translator::NOUN_SINGULAR] : [Translator::NOUN_SINGULAR];
            foreach ($nouns as $noun) {
                try {
                    if ($this->translator->transEntityExists($entityFqcn, null, $noun)
                        && null !== ($name = $this->translator->transEntity($entityFqcn, null, $noun))
                        && '' !== $name
                    ) {
                        return $name;
                    }
                } catch (\Throwable) {
                    // an entity the translator cannot parse is named by its class below
                }
            }
        }

        return self::humanize(substr((string) strrchr('\\'.$entityFqcn, '\\'), 1));
    }

    /**
     * Symfony's own `|humanize` (FormRenderer::humanize()), so the name reads
     * like every other humanized label of the back office.
     */
    public static function humanize(string $text): string
    {
        return ucfirst(strtolower(trim(preg_replace(['/([A-Z])/', '/[_\s]+/'], ['_$1', ' '], $text))));
    }
}
