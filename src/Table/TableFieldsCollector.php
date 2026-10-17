<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Table;

use ReflectionClass;
use Throwable;
use WebCodingCat\WpAppFramework\DependencyInjection\NoService;
use WebCodingCat\WpAppFramework\Query\Field\Field;
use WebCodingCat\WpAppFramework\Query\Field\FieldTypes;
use WebCodingCat\WpAppFramework\Query\FieldClassCheck;

/**
 * Checks the classes with #[TableFields] in the build: every public static method that returns a field is called,
 * and its field must belong to the row class of the attribute and fit the type of its column, nullability and enum
 * included. The manifest gets nothing: queries find the columns through the row classes.
 *
 * In development mode the build runs again when signatures change, not when only a method body changes; the query
 * checks the field again when it runs.
 *
 * @internal
 *
 * @phpstan-import-type TableEntry from TableCollector
 */
#[NoService]
final class TableFieldsCollector
{
    /** @var list<string> */
    private array $errors = [];

    /**
     * @param array<class-string<AbstractRow>, TableEntry> $tables Valid row classes.
     */
    public function __construct(
        private readonly array $tables,
    ) {}

    /**
     * @param array<class-string, ReflectionClass<object>> $classes All scanned classes.
     */
    public function collect(array $classes): void
    {
        /** @var array<class-string, class-string> $owners Class with #[TableFields] by row class. */
        $owners = [];
        foreach ($classes as $class => $reflection) {
            $attribute = $reflection->getAttributes(TableFields::class)[0] ?? null;
            if ($attribute === null) {
                continue;
            }
            try {
                $rowClass = $attribute->newInstance()->rowClass;
            } catch (Throwable $exception) {
                $this->errors[] = sprintf('#[TableFields] on %s: %s', $class, $exception->getMessage());
                continue;
            }
            $target = $classes[$rowClass] ?? null;
            if ($target === null || $target->getAttributes(Table::class) === []) {
                $this->errors[] = sprintf('#[TableFields] on %s names %s, which is no row class (#[Table]) of this theme or plugin.', $class, $rowClass);
                continue;
            }
            if (isset($owners[$rowClass])) {
                $this->errors[] = sprintf('#[TableFields] on %s names %s, whose fields %s already provides; one class per row class.', $class, $rowClass, $owners[$rowClass]);
                continue;
            }
            $owners[$rowClass] = $class;
            // An invalid row class is reported by the table collector.
            $table = $this->tables[$rowClass] ?? null;
            if ($table === null) {
                continue;
            }
            array_push($this->errors, ...FieldClassCheck::methods(
                $reflection,
                $rowClass,
                '#[TableFields]',
                array_keys($table['columns']),
                static fn(Field $field): ?string => FieldTypes::columnMismatch($field, $table['columns'][$field->getField()], $field->getField() === $table['primaryKey']),
            ));
        }
    }

    /**
     * @return list<string>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
