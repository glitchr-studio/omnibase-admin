<?php

namespace Base\Admin\Field;

use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;

/**
 * Resolves a field's raw and formatted value for one entity instance:
 * property access, then the field's formatValue() callable if any.
 * Returns a clone so one descriptor list can serve every row of an index.
 */
class FieldValueResolver
{
    protected PropertyAccessorInterface $accessor;

    /** @var string[] */
    protected array $identifierFields;

    /** @var array<class-string, string[]> */
    protected array $identifierFieldsByEntity;

    protected bool $lowercaseIdentifiers;

    /**
     * @param string[]|null                 $identifierFields         null = DEFAULT_IDENTIFIER_FIELDS
     * @param array<class-string, string[]> $identifierFieldsByEntity per-class overrides
     * @param bool                          $lowercaseIdentifiers     see $this->lowercaseIdentifiers
     */
    public function __construct(
        ?PropertyAccessorInterface $accessor = null,
        ?array $identifierFields = null,
        array $identifierFieldsByEntity = [],
        bool $lowercaseIdentifiers = false,
    ) {
        $this->lowercaseIdentifiers = $lowercaseIdentifiers;
        $this->accessor = $accessor ?? PropertyAccess::createPropertyAccessorBuilder()
            ->disableExceptionOnInvalidPropertyPath()
            ->disableExceptionOnInvalidIndex()
            ->getPropertyAccessor();

        $this->identifierFields = $identifierFields ?? self::DEFAULT_IDENTIFIER_FIELDS;
        $this->identifierFieldsByEntity = $identifierFieldsByEntity;
    }

    public function resolve(FieldDescriptor $descriptor, object $entity): FieldDescriptor
    {
        $resolved = clone $descriptor;

        $value = null;
        if (null !== $descriptor->getProperty() && !$descriptor->isVirtual()) {
            $value = $this->accessor->isReadable($entity, $descriptor->getProperty())
                ? $this->accessor->getValue($entity, $descriptor->getProperty())
                : null;
        }

        $resolved->setValue($value);

        $callable = $descriptor->getFormatValueCallable();
        $resolved->setFormattedValue(null !== $callable ? $callable($value, $entity) : $this->formatValue($value));

        return $resolved;
    }

    /**
     * Default display formatting, done here rather than in Twig: templates
     * cannot reliably type-check values behind entity magic methods
     * (BaseTrait's __get makes any attribute look "defined"). Public so
     * templates that must format individual items of a raw collection
     * themselves (a SelectField's index badge, iterating real entity
     * objects one by one) can reuse the exact same logic via the
     * admin_display() twig function instead of duplicating it.
     */
    public function formatValue(mixed $value): mixed
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i');
        }

        if ($value instanceof \BackedEnum) {
            return $value->value;
        }
        if ($value instanceof \UnitEnum) {
            return $value->name;
        }

        if (\is_object($value) && !is_iterable($value)) {
            return method_exists($value, '__toString')
                ? (string) $value
                : substr(strrchr('\\' . get_class($value), '\\'), 1) . (method_exists($value, 'getId') ? ' #' . $value->getId() : '');
        }

        return $value;
    }

    /**
     * How a related entity should be labelled in a list/detail cell.
     * formatValue() above always yields __toString(), which for a User is
     * the username - fine as a default, useless when the usernames are
     * short handles ("NN") and the reader wants a person's actual name or
     * just their face. Fields opt into one of these via
     * setEntityDisplay(); see SelectField/AssociationField.
     */
    public const DISPLAY_USERNAME = 'username';
    public const DISPLAY_FULLNAME = 'fullname';
    public const DISPLAY_AVATAR = 'avatar';

    /**
     * The text to show for a related entity under the requested mode.
     *
     * Every mode degrades rather than blanking the cell: an entity with no
     * getFullname() (or one returning an empty string, which User does when
     * both name parts are unset) falls back to the __toString() label, so
     * switching a field to 'fullname' can never turn a populated column
     * into empty cells. AVATAR returns the same text too - the templates
     * use it for the alt/title and as the visible fallback when the entity
     * has no avatar image.
     */
    public function entityLabel(mixed $entity, string $mode = self::DISPLAY_USERNAME): mixed
    {
        if (!\is_object($entity)) {
            return $this->formatValue($entity);
        }

        $fallback = $this->formatValue($entity);

        if (self::DISPLAY_FULLNAME === $mode && method_exists($entity, 'getFullname')) {
            $full = trim((string) $entity->getFullname());

            return '' !== $full ? $full : $fallback;
        }

        if (self::DISPLAY_USERNAME === $mode && method_exists($entity, 'getUsername')) {
            $username = trim((string) $entity->getUsername());

            return '' !== $username ? $username : $fallback;
        }

        return $fallback;
    }

    /**
     * Fields that may stand in for the primary key in an admin URL when
     * nothing is configured, most readable first.
     *
     * Deliberately does NOT include "username": a username is an
     * APPLICATION-level idea (an app may or may not have one, and may or
     * may not want it in URLs), whereas the base app's User is identified
     * by its id. An app that wants it opts in per entity:
     *
     *     admin:
     *         url_identifier:
     *             entities:
     *                 App\Entity\User: ['username']
     *
     * Set `fields: []` to switch the whole admin back to plain numeric ids
     * - the escape hatch for slugs long enough to make URLs unwieldy.
     */
    public const DEFAULT_IDENTIFIER_FIELDS = ['slug', 'uuid'];

    /**
     * Whether a generated identifier is lowercased, so a User stored as
     * "Marki" links as "/admin/users/marki".
     *
     * OFF by default, and that default is a correctness choice rather than
     * a taste one: lowercasing is only safe where the lookup that resolves
     * the URL back is CASE-INSENSITIVE. That holds on MySQL under a _ci
     * collation (this app's userProfile.username is utf8mb4_general_ci),
     * but PostgreSQL compares strings case-sensitively by default, where a
     * lowercased link to "Marki" would simply 404. Apps enable it knowing
     * their own collation:
     *
     *     admin:
     *         url_identifier:
     *             lowercase: true
     *
     * Reading a URL is unaffected either way - AbstractCrudController
     * compares the canonical identifier case-insensitively, so a
     * mixed-case URL is never bounced to a lowercase one.
     */

    /**
     * The identifier fields that apply to one entity class, honouring any
     * per-class override. Shared with
     * AbstractCrudController::findEntity(), which resolves a URL segment
     * back to an entity by walking this same list - asking ONE method is
     * what guarantees that every identifier the admin generates is one the
     * admin can also resolve, under whatever configuration is in force.
     *
     * An override configured on a PARENT class covers its subclasses (and
     * therefore Doctrine's runtime proxies, which extend the entity), so
     * configuring App\Entity\User does not have to be repeated for every
     * discriminator subtype.
     *
     * @return string[]
     */
    public function identifierFieldsFor(object|string $entity): array
    {
        $class = \is_object($entity) ? $entity::class : $entity;

        if (isset($this->identifierFieldsByEntity[$class])) {
            return $this->identifierFieldsByEntity[$class];
        }

        foreach ($this->identifierFieldsByEntity as $configured => $fields) {
            if (is_a($class, $configured, true)) {
                return $fields;
            }
        }

        return $this->identifierFields;
    }

    /**
     * Every field a URL segment may be RESOLVED from - deliberately wider
     * than identifierFieldsFor(), which says only what we GENERATE.
     *
     * Config decides what the admin emits; it must not decide what the
     * admin still understands. Otherwise switching `fields: []` to shorten
     * URLs would 404 every slug link already sitting in someone's
     * bookmarks, history or an email - a silent breakage of old links,
     * which is exactly what the id fallback was designed to avoid.
     *
     * The configured fields come FIRST so they win a tie; the rest are
     * accepted afterwards. Candidates the entity does not actually map are
     * skipped by the caller, so a wide list costs no extra queries.
     *
     * @return string[]
     */
    public function resolvableIdentifierFieldsFor(object|string $entity): array
    {
        return array_values(array_unique(array_merge(
            $this->identifierFieldsFor($entity),
            self::DEFAULT_IDENTIFIER_FIELDS,
            ...array_values($this->identifierFieldsByEntity),
        )));
    }

    /**
     * The identifier to put in an admin URL for this entity: the first
     * populated configured field, else the numeric id.
     *
     * A readable URL is the point ("/admin/articles/petit-cours-..." rather
     * than "/admin/articles/1147", "/admin/users/marki" rather than
     * "/admin/users/14"), and a slug is also stable across environments in
     * a way a database id is not. The id is the last resort and remains
     * valid everywhere, since resolution accepts all of these.
     *
     * Two kinds of candidate are rejected rather than trusted:
     *
     * - Empty/whitespace-only, which would generate "/admin/articles/" and
     *   silently point at the collection instead of the record.
     * - All-digits, which is genuinely AMBIGUOUS: resolution reads a
     *   numeric segment as a primary key first, so a user literally named
     *   "12345" (the username validator allows digits-only) would link to
     *   whichever account holds id 12345 - someone else's. Falling through
     *   to the real id keeps the URL unambiguous by construction.
     */
    public function entityIdentifier(object $entity): string|int|null
    {
        foreach ($this->identifierFieldsFor($entity) as $field) {
            $method = 'get' . ucfirst($field);
            if (!method_exists($entity, $method)) {
                continue;
            }

            $value = $entity->{$method}();
            $value = null === $value ? '' : trim((string) $value);
            if ('' !== $value && !ctype_digit($value)) {
                return $this->lowercaseIdentifiers ? mb_strtolower($value) : $value;
            }
        }

        return method_exists($entity, 'getId') ? $entity->getId() : null;
    }

    /**
     * The avatar URL for a related entity, or null when it has none (the
     * templates then fall back to the label). getFormattedAvatar() is the
     * app-side accessor that already resolves the stored file to a usable
     * URL; getAvatar() is the raw column and only worth using when the
     * formatted one isn't there.
     */
    public function entityAvatar(mixed $entity): ?string
    {
        if (!\is_object($entity)) {
            return null;
        }

        foreach (['getFormattedAvatar', 'getAvatar'] as $method) {
            if (!method_exists($entity, $method)) {
                continue;
            }

            $avatar = $entity->{$method}();
            if (\is_string($avatar) && '' !== $avatar) {
                return $avatar;
            }
        }

        return null;
    }

    /**
     * @param iterable<FieldInterface|FieldDescriptor> $fields
     * @return FieldDescriptor[]
     */
    public function resolveAll(iterable $fields, object $entity, ?string $page = null): array
    {
        $resolved = [];
        foreach ($fields as $field) {
            $descriptor = $field instanceof FieldInterface ? $field->getAsDto() : $field;
            if (null !== $page && !$descriptor->isDisplayedOn($page)) {
                continue;
            }
            $resolved[] = $this->resolve($descriptor, $entity);
        }

        return $resolved;
    }
}
