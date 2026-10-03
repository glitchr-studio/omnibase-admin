<?php

namespace Base\Admin\Controller\Crud;

use Base\Admin\Controller\AbstractCrudController;
use Base\Entity\Layout\TextOverride;
use Base\Field\DateTimeField;
use Base\Field\IdField;
use Base\Field\TextareaField;
use Base\Field\TextField;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Contracts\Service\Attribute\Required;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * "Textes du site": any text of the site's pages rewritten here - a heading, a
 * button, a paragraph, in one of the site's languages. Pick the text by its
 * key (the list shows its current wording), write the new one; delete it to
 * bring the original back. {placeholders} stay as they are. The text wins over
 * translations/ through glitchr/omnibase's OverridingTranslator.
 * Moved here from the apps' App\Controller\Admin\Crud\TextOverrideCrudController.
 */
class TextOverrideCrudController extends AbstractCrudController
{
    private TranslatorInterface $translator;

    /** @var list<string> */
    private array $locales = ['fr', 'en'];

    #[Required]
    public function setTextServices(TranslatorInterface $translator, #[Autowire('%kernel.enabled_locales%')] array $locales = []): void
    {
        $this->translator = $translator;
        if ($locales) {
            $this->locales = array_values(array_unique(array_map(fn (string $l) => substr($l, 0, 2), $locales)));
        }
    }

    public static function getEntityFqcn(): string
    {
        return TextOverride::class;
    }

    public static function getPreferredIcon(): ?string
    {
        return 'fa-solid fa-font';
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnIndex();
        yield TextField::new('key', '@admin.text_override.key')->setColumns(8)
            ->setFormType(ChoiceType::class)->setFormTypeOptions(['choices' => $this->keys(), 'placeholder' => 'text_override.key_placeholder', 'translation_domain' => 'admin', 'choice_translation_domain' => false])
            ->setHelp('@admin.text_override.key_help');
        yield TextField::new('locale', '@admin.text_override.locale')->setColumns(4)
            ->setFormType(ChoiceType::class)->setFormTypeOptions(['choices' => array_combine(array_map('strtoupper', $this->locales), $this->locales), 'choice_translation_domain' => false]);
        yield TextareaField::new('value', '@admin.text_override.value')->setColumns(12)
            ->setHelp('@admin.text_override.value_help');
        yield DateTimeField::new('updatedAt', '@admin.text_override.updated_at')->onlyOnIndex();
    }

    /** @return array<string, array<string, string>> "key - current wording" => key, by section, in the site's first language */
    private function keys(): array
    {
        /** @var TranslatorInterface&\Symfony\Component\Translation\TranslatorBagInterface $translator */
        $translator = $this->translator;
        $messages = $translator->getCatalogue($this->locales[0] ?? 'fr')->all('messages');
        ksort($messages);

        $choices = [];
        foreach ($messages as $key => $text) {
            $section = strtok((string) $key, '.');
            $label = $key.' — '.mb_strimwidth(preg_replace('/\s+/', ' ', strip_tags((string) $text)), 0, 90, '…');
            $choices[$section][$label] = $key;
        }

        return $choices;
    }
}
