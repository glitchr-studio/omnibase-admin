<?php

namespace Tests\Base\Admin\Field;

use Base\Admin\Field\FieldDescriptor;
use Base\Admin\Field\FieldInterface;
use PHPUnit\Framework\TestCase;

/**
 * Instantiates every ported field: catches missing imports, broken fluent
 * chains and EasyCorp leftovers in one sweep.
 */
class AllFieldsSmokeTest extends TestCase
{
    /**
     * @return iterable<string, array{class-string<FieldInterface>}>
     */
    public static function provideFieldClasses(): iterable
    {
        foreach (glob(\dirname(__DIR__, 2) . '/src/Field/*Field.php') as $file) {
            $fqcn = 'Base\\Admin\\Field\\' . basename($file, '.php');
            yield $fqcn => [$fqcn];
        }
    }

    /**
     * @dataProvider provideFieldClasses
     */
    public function testFieldBuildsADescriptor(string $fqcn): void
    {
        $this->assertTrue(class_exists($fqcn), sprintf('%s does not autoload', $fqcn));

        if ((new \ReflectionClass($fqcn))->isAbstract()) {
            $this->markTestSkipped($fqcn . ' is abstract');
        }

        $field = $fqcn::new('someProperty');
        $this->assertInstanceOf(FieldInterface::class, $field);

        $dto = $field->getAsDto();
        $this->assertInstanceOf(FieldDescriptor::class, $dto);
        $this->assertSame($fqcn, $dto->getFieldFqcn());
        // some fields (e.g. TranslationField) force their own property name
        $this->assertNotNull($dto->getProperty(), $fqcn . ' sets no property');
        $this->assertNotNull($dto->getFormType(), $fqcn . ' declares no form type');
        $this->assertNotNull($dto->getTemplateName(), $fqcn . ' declares no template name');
    }

    public function testNoEasyCorpReferenceSurvivesInFieldSources(): void
    {
        foreach (glob(\dirname(__DIR__, 2) . '/src/Field/*.php') as $file) {
            $this->assertStringNotContainsString(
                'EasyCorp\\Bundle',
                file_get_contents($file),
                basename($file) . ' still references EasyCorp'
            );
        }
    }
}
