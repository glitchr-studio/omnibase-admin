<?php

namespace Tests\Base\Admin\Controller;

use Base\Admin\Controller\Crud\RedirectionCrudController;
use Base\Admin\Form\FieldFormBuilder;
use Base\Admin\Router\AdminRouteRegistry;
use Base\Admin\Settings\SettingsSectionInterface;
use Base\Admin\Settings\SettingsSections;
use Base\Entity\Layout\Redirection;
use Base\Field\FieldDescriptor;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * The back office's part of the redirections and of the business's details:
 * the CRUD is registered and routed, its form writes a Redirection (the old
 * address kept as its path), and the settings page has the phone and address
 * the LocalBusiness JSON-LD reads.
 */
class RedirectionCrudControllerTest extends KernelTestCase
{
    protected function setUp(): void
    {
        if (!class_exists('App\\Kernel')) {
            self::markTestSkipped('Requires a host application (the omnibase harness).');
        }

        self::bootKernel();
        $request = Request::create('/admin/redirections');
        $request->setSession(new Session(new MockArraySessionStorage()));
        static::getContainer()->get('request_stack')->push($request);
    }

    public function testTheCrudIsRoutedUnderTheBackOffice(): void
    {
        $registry = static::getContainer()->get(AdminRouteRegistry::class);
        $this->assertSame(RedirectionCrudController::class, $registry->getControllerForEntity(Redirection::class));

        $routes = static::getContainer()->get('router')->getRouteCollection();
        $index = $routes->get($registry->getRouteName(RedirectionCrudController::class, 'index'));
        $this->assertNotNull($index);
        $this->assertStringStartsWith('/admin/', $index->getPath());
        $this->assertNotNull($routes->get('admin_hours'));
        $this->assertSame(['POST'], $routes->get('admin_hours_special_delete')->getMethods());
    }

    public function testTheFormWritesARedirection(): void
    {
        $controller = static::getContainer()->get(RedirectionCrudController::class);
        $redirection = new Redirection();

        $form = (new FieldFormBuilder(static::getContainer()->get('form.factory'), static::getContainer()->get('doctrine')))
            ->createForm($redirection, $controller->configureFields('new'), FieldDescriptor::PAGE_NEW, ['csrf_protection' => false]);

        $this->assertTrue($form->has('source'));
        $this->assertTrue($form->has('status'));
        $this->assertFalse($form->has('hits'), 'the counter is not typed');

        $form->submit(['source' => 'https://old.example/produit/enseigne/', 'target' => 'savoir-faire/enseigne', 'status' => '302', 'enabled' => '1']);
        $this->assertTrue($form->isSynchronized(), (string) $form->getErrors(true));
        $this->assertSame('/produit/enseigne', $redirection->getSource());
        $this->assertSame('/savoir-faire/enseigne', $redirection->getTarget());
        $this->assertSame(302, $redirection->getStatus());
    }

    public function testTheSettingsPageHasThePhoneAndTheAddress(): void
    {
        $fields = static::getContainer()->get(SettingsSections::class)->fields(SettingsSectionInterface::SETTINGS);

        foreach (['base.settings.phone', 'base.settings.address.street', 'base.settings.address.postal_code', 'base.settings.address.locality', 'base.settings.address.country'] as $path) {
            $this->assertArrayHasKey($path, $fields);
            $this->assertNotSame('', (string) $fields[$path]['label'], $path.' is labelled');
        }
        $this->assertNotSame('', (string) $fields['base.settings.title']['label'], 'omnibase\'s own settings are labelled too');
    }
}
