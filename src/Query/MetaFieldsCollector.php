<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Query;

use ReflectionClass;
use Throwable;
use WebCodingCat\WpAppFramework\DependencyInjection\NoService;
use WebCodingCat\WpAppFramework\Post\PostMeta;
use WebCodingCat\WpAppFramework\Post\PostTypeCollector;
use WebCodingCat\WpAppFramework\Query\Field\Field;
use WebCodingCat\WpAppFramework\Query\Field\FieldTypes;
use WebCodingCat\WpAppFramework\Value\ValueFactory;

/**
 * Checks the classes with #[MetaFields] in the build: every public static method that returns a field is called,
 * and its field must belong to the meta class of the attribute and fit the type of the field. The manifest gets
 * nothing: queries find the meta keys through the meta classes.
 *
 * In development mode the build runs again when signatures change, not when only a method body changes; the query
 * checks the field again when it runs.
 *
 * @internal
 *
 * @phpstan-import-type MetaEntry from PostTypeCollector
 * @phpstan-import-type ValueField from ValueFactory
 */
#[NoService]
final class MetaFieldsCollector
{
    /** @var list<string> */
    private array $errors = [];

    /**
     * @param array<class-string, MetaEntry>                 $meta   Valid meta classes.
     * @param array<class-string, array<string, ValueField>> $values Value descriptions.
     */
    public function __construct(
        private readonly array $meta,
        private readonly array $values,
    ) {}

    /**
     * @param array<class-string, ReflectionClass<object>> $classes All scanned classes.
     */
    public function collect(array $classes): void
    {
        /** @var array<class-string, class-string> $owners Class with #[MetaFields] by meta class. */
        $owners = [];
        foreach ($classes as $class => $reflection) {
            $attribute = $reflection->getAttributes(MetaFields::class)[0] ?? null;
            if ($attribute === null) {
                continue;
            }
            try {
                $metaClass = $attribute->newInstance()->metaClass;
            } catch (Throwable $exception) {
                $this->errors[] = sprintf('#[MetaFields] on %s: %s', $class, $exception->getMessage());
                continue;
            }
            $target = $classes[$metaClass] ?? null;
            if ($target === null || $target->getAttributes(PostMeta::class) === []) {
                $this->errors[] = sprintf('#[MetaFields] on %s names %s, which is no meta class (#[PostMeta]) of this theme or plugin.', $class, $metaClass);
                continue;
            }
            if (isset($owners[$metaClass])) {
                $this->errors[] = sprintf('#[MetaFields] on %s names %s, whose fields %s already provides; one class per meta class.', $class, $metaClass, $owners[$metaClass]);
                continue;
            }
            $owners[$metaClass] = $class;
            // An invalid meta class is reported by the post type collector.
            if (isset($this->meta[$metaClass], $this->values[$metaClass])) {
                $this->checkMethods($reflection, $metaClass);
            }
        }
    }

    /**
     * @return list<string>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * @param ReflectionClass<object> $reflection
     * @param class-string            $metaClass
     */
    private function checkMethods(ReflectionClass $reflection, string $metaClass): void
    {
        $values = $this->values[$metaClass];
        array_push($this->errors, ...FieldClassCheck::methods(
            $reflection,
            $metaClass,
            '#[MetaFields]',
            array_keys($this->meta[$metaClass]['fields']),
            static fn(Field $field): ?string => isset($values[$field->getField()]) ? FieldTypes::mismatch($field, $values[$field->getField()]['value']) : sprintf('%s::$%s has no value description', $metaClass, $field->getField()),
        ));
    }
}
