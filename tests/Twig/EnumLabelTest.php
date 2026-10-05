<?php

namespace Tests\Base\Admin\Twig;

use Base\Admin\Form\FieldFormBuilder;
use Base\Entity\Thread\Comment;
use Base\Enum\CommentState;
use Base\Field\FieldDescriptor;
use Base\Field\FieldValueResolver;
use Base\Field\SelectField;
use Base\Field\TextField;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * A PHP enum's case reads the same in a CRUD's list, on its detail page and
 * in its form: the cell printed the stored value with a capital ("Pending")
 * beside a select saying "En attente de modération". Both pages include the
 * field's template (crud/field/select, crud/field/text), rendered here.
 */
class EnumLabelTest extends KernelTestCase
{
    protected function setUp(): void
    {
        if (!class_exists('App\\Kernel')) {
            self::markTestSkipped('Requires a host application (the omnibase harness).');
        }

        self::bootKernel();
        $request = Request::create('/admin/comments');
        $request->setLocale('fr');
        $request->setSession(new Session(new MockArraySessionStorage()));
        static::getContainer()->get('request_stack')->push($request);
        static::getContainer()->get('translator')->setLocale('fr');
    }

    /** The cell of a list or of a detail page, as crud/index and crud/detail include it. */
    private function cell(object $field, object $entity): string
    {
        $descriptor = $field->getAsDto();
        $resolved = static::getContainer()->get(FieldValueResolver::class)->resolveAll([$descriptor], $entity, FieldDescriptor::PAGE_INDEX)[0];

        $html = static::getContainer()->get('twig')->render('@Admin/'.$resolved->getTemplateName().'.html.twig', ['field' => $resolved, 'entity' => $entity]);

        return trim(preg_replace('/\s+/', ' ', strip_tags($html)));
    }

    private function inEnglish(object $record): object
    {
        static::getContainer()->get('translator')->setLocale('en');

        return $record;
    }

    /** What the form's select says for the case. */
    private function formLabel(object $entity, string $property, \UnitEnum $case): ?string
    {
        $form = (new FieldFormBuilder(static::getContainer()->get('form.factory'), static::getContainer()->get('doctrine')))
            ->createForm($entity, [SelectField::new($property)], FieldDescriptor::PAGE_EDIT, ['csrf_protection' => false]);

        // omnibase's select hands its choices to select2: {id: what is stored, text: the label}.
        $found = null;
        $select2 = json_decode((string) ($form->createView()[$property]->vars['select2'] ?? ''), true);
        foreach ($select2['data'] ?? [] as $choice) {
            if ((string) ($choice['id'] ?? '') === (string) $case->value) {
                $found = $choice['text'] ?? null;
            }
        }

        return \is_string($found) ? $found : null;
    }

    public function testTheListPrintsTheLabelOfAnEnumKeyedInTheEnumsDomain(): void
    {
        $comment = (new Comment())->setState(CommentState::PENDING);

        $this->assertSame('En attente de modération', $this->cell(SelectField::new('state'), $comment));
        $this->assertSame('Indésirable', $this->cell(SelectField::new('state'), (new Comment())->setState(CommentState::SPAM)));
    }

    public function testTheLocaleIsFollowed(): void
    {
        static::getContainer()->get('translator')->setLocale('en');

        $this->assertSame('Awaiting moderation', $this->cell(SelectField::new('state'), (new Comment())->setState(CommentState::PENDING)));
    }

    public function testTheListSaysWhatTheFormSays(): void
    {
        $comment = (new Comment())->setState(CommentState::APPROVED);
        $inForm = $this->formLabel($comment, 'state', CommentState::APPROVED);

        $this->assertSame('Publié', $inForm, 'the form\'s select');
        $this->assertSame($inForm, $this->cell(SelectField::new('state'), $comment), 'the list\'s cell');
    }

    public function testAnEnumThatNamesItselfIsAsked(): void
    {
        $record = new EnumLabelRecord();

        $this->assertSame('À relire', $this->cell(SelectField::new('status'), $record), 'TranslatableInterface: the case says its own words');
        $this->assertSame('À relire', $this->cell(TextField::new('status'), $record), 'and a plain text column prints them too');
    }

    public function testAnEnumWithoutTranslationHasItsNameMadeReadable(): void
    {
        $record = new EnumLabelRecord();

        $this->assertSame('Not contracted', $this->cell(SelectField::new('contract'), $record));
    }

    public function testChoicesGivenByTheFieldKeepTheirLabels(): void
    {
        $record = new EnumLabelRecord();

        $this->assertSame('Invitée', $this->cell(SelectField::new('role')->setChoices(['Soliste' => 'soloist', 'Invitée' => 'guest']), $record), 'the label of what is stored, not "Guest"');
        $this->assertSame('Invitée', $this->cell(SelectField::new('role')->setChoices(['Sur scène' => ['Soliste' => 'soloist'], 'Autour' => ['Invitée' => 'guest']]), $record), 'groups are looked into');
        $this->assertSame('Reports', $this->cell(SelectField::new('role')->setChoices(['@admin.complaint.plural' => 'guest']), $this->inEnglish($record)), 'a label that is a key is translated');
        $this->assertSame('Guest', $this->cell(SelectField::new('role')->setChoices(fn () => ['Invitée' => 'guest']), $record), 'choices made by a closure are the form\'s alone');
        $this->assertSame('Some words', $this->cell(TextField::new('words'), $record));
    }

    public function testTheFunctionAnswersNullForWhatIsNotAnEnum(): void
    {
        $twig = static::getContainer()->get('twig');
        $function = $twig->getFunction('admin_enum_label')->getCallable();

        $this->assertNull($function($twig, 'pending'));
        $this->assertNull($function($twig, null));
        $this->assertNull($function($twig, 'pending', 'Base\\Enum\\UserRole'), 'an omnibase EnumType is trans_enum\'s');
        $this->assertSame('En attente de modération', $function($twig, 'pending', CommentState::class), 'a stored value, the enum named');
        $this->assertNull($function($twig, 'nothing-of-the-kind', CommentState::class));
    }
}

enum EnumLabelStatus: string implements TranslatableInterface
{
    case REVIEW = 'review';

    public function trans(TranslatorInterface $translator, ?string $locale = null): string
    {
        return 'À relire';
    }
}

enum EnumLabelContract
{
    case NOT_CONTRACTED;
}

class EnumLabelRecord
{
    public EnumLabelStatus $status = EnumLabelStatus::REVIEW;
    public EnumLabelContract $contract = EnumLabelContract::NOT_CONTRACTED;
    public string $role = 'guest';
    public string $words = 'Some words';

    public function getId(): int { return 1; }
}
