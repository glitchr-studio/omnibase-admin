<?php

namespace Base\Admin\Controller\Crud;

use Base\Admin\Config\Crud;
use Base\Admin\Controller\AbstractCrudController;
use Base\Admin\Filter\Filters;
use Base\Entity\Layout\Redirection;
use Base\Field\BooleanField;
use Base\Field\DateTimeField;
use Base\Field\IdField;
use Base\Field\IntegerField;
use Base\Field\TextField;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

/**
 * "Redirections": the addresses the site no longer answers, and where each
 * leads now (Base\Entity\Layout\Redirection, glitchr/omnibase). An old address
 * is typed or pasted whole - it is kept as its path -, the new one is a path
 * of the site or another site's address; permanent (301) unless it is a
 * detour. The list shows how many visitors each one carried, and when last:
 * the old addresses still in use, and those that can go.
 */
class RedirectionCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Redirection::class;
    }

    public static function getPreferredIcon(): ?string
    {
        return 'fa-solid fa-diamond-turn-right';
    }

    public function configureCrud(Crud $crud): Crud
    {
        // No label of its own: "Redirection", the class's name, is the word in French and in English.
        return parent::configureCrud($crud)
            ->setDefaultSort(['hits' => 'DESC']);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add('status')->add('enabled');
    }

    public function configureFields(string $pageName): iterable
    {
        yield IdField::new('id')->onlyOnIndex();
        yield TextField::new('source', '@admin.redirection.source')->setColumns(6)
            ->setHelp('@admin.redirection.source_help');
        yield TextField::new('target', '@admin.redirection.target')->setColumns(6)
            ->setHelp('@admin.redirection.target_help');
        yield IntegerField::new('status', '@admin.redirection.status')->setColumns(4)
            ->setFormType(ChoiceType::class)->setFormTypeOptions([
                'choices' => ['redirection.status_301' => Redirection::PERMANENT, 'redirection.status_302' => Redirection::TEMPORARY],
                'translation_domain' => 'admin',
                'choice_translation_domain' => 'admin',
            ]);
        yield BooleanField::new('enabled', '@admin.redirection.enabled')->setColumns(4);
        yield IntegerField::new('hits', '@admin.redirection.hits')->hideOnForm();
        yield DateTimeField::new('lastHitAt', '@admin.redirection.last_hit_at')->hideOnForm();
        yield DateTimeField::new('createdAt', '@admin.redirection.created_at')->onlyOnDetail();
    }
}
