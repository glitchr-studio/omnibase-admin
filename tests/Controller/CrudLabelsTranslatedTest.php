<?php

namespace Tests\Base\Admin\Controller;

use Base\Admin\Config\Crud;
use Base\Admin\Controller\Crud\ComplaintCrudController;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Translation\TranslatableMessage;

/**
 * The labels configureCrud() gives as translation keys are words by the time
 * a template prints them: the list of a CRUD was titled "@admin.complaint.plural".
 */
class CrudLabelsTranslatedTest extends KernelTestCase
{
    protected function setUp(): void
    {
        if (!class_exists('App\\Kernel')) {
            self::markTestSkipped('Requires a host application (the omnibase harness).');
        }

        self::bootKernel();
        $request = Request::create('/admin/complaints');
        $request->setLocale('en');
        $request->setSession(new Session(new MockArraySessionStorage()));
        static::getContainer()->get('request_stack')->push($request);
        static::getContainer()->get('translator')->setLocale('en');
    }

    private function config(object $controller): Crud
    {
        return (new \ReflectionMethod($controller, 'getCrudConfig'))->invoke($controller, Crud::PAGE_INDEX, 'index');
    }

    public function testAKeyThatNamesItsDomainIsTranslated(): void
    {
        $crud = $this->config(static::getContainer()->get(ComplaintCrudController::class));

        $this->assertSame('Reports', $crud->getEntityLabelInPlural());
        $this->assertSame('Report', $crud->getEntityLabelInSingular());
    }

    public function testTheLocaleIsFollowed(): void
    {
        static::getContainer()->get('translator')->setLocale('fr');
        $crud = $this->config(static::getContainer()->get(ComplaintCrudController::class));

        $this->assertNotSame('@admin.complaint.plural', $crud->getEntityLabelInPlural());
        $this->assertNotSame('Reports', $crud->getEntityLabelInPlural());
    }

    public function testPlainWordsAndTranslatablesAndTitles(): void
    {
        $controller = static::getContainer()->get(ComplaintCrudController::class);
        $translate = new \ReflectionMethod($controller, 'translateCrudLabels');

        $crud = $translate->invoke($controller, Crud::new()
            ->setEntityLabelInSingular('Journal')
            ->setEntityLabelInPlural(new TranslatableMessage('complaint.plural', [], 'admin'))
            ->setPageTitle(Crud::PAGE_INDEX, '@admin.complaint.plural')
            ->setPageTitle(Crud::PAGE_NEW, 'Nouvelle écriture'));

        $this->assertSame('Journal', $crud->getEntityLabelInSingular(), 'plain words are left as they are');
        $this->assertSame('Reports', $crud->getEntityLabelInPlural());
        $this->assertSame('Reports', $crud->getPageTitle(Crud::PAGE_INDEX));
        $this->assertSame('Nouvelle écriture', $crud->getPageTitle(Crud::PAGE_NEW));
    }
}
