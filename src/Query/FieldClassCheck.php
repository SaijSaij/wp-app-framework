<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Query;

use Closure;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use Saij\WpAppFramework\DependencyInjection\NoService;
use Saij\WpAppFramework\Query\Field\Field;
use Throwable;

/**
 * The build check of a field class (#[MetaFields], #[TableFields]): every public static method that returns a field
 * is called, and its field must belong to the class of the attribute, exist there and fit its type.
 *
 * @internal
 */
#[NoService]
final class FieldClassCheck
{
    /**
     * @param ReflectionClass<object>       $reflection The field class.
     * @param class-string                  $owner      The class whose fields it provides.
     * @param string                        $attribute  The attribute of the field class, e.g. "#[MetaFields]".
     * @param list<string>                  $fields     The names of the fields of $owner.
     * @param Closure(Field): (string|null) $mismatch   Why a field object does not fit its field, or null if it does.
     * @return list<string> The errors.
     */
    public static function methods(ReflectionClass $reflection, string $owner, string $attribute, array $fields, Closure $mismatch): array
    {
        $errors = [];
        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_STATIC) as $method) {
            $type = $method->getReturnType();
            if (!$method->isStatic() || !$method->isPublic() || !$type instanceof ReflectionNamedType || $type->isBuiltin() || !is_a($type->getName(), Field::class, true)) {
                continue;
            }
            $location = sprintf('%s::%s()', $reflection->getName(), $method->getName());
            if ($method->getNumberOfRequiredParameters() > 0) {
                $errors[] = sprintf('%s returns a field and must have no parameters.', $location);
                continue;
            }
            try {
                $field = $method->invoke(null);
            } catch (Throwable $exception) {
                $errors[] = sprintf('%s failed: %s', $location, $exception->getMessage());
                continue;
            }
            if (!$field instanceof Field) {
                continue;
            }
            if ($field->getClass() !== $owner) {
                $errors[] = sprintf('%s returns a field of %s; the class provides the fields of %s (%s).', $location, $field->getClass(), $owner, $attribute);
                continue;
            }
            if (!in_array($field->getField(), $fields, true)) {
                $errors[] = sprintf('%s returns the field $%s, which %s does not have (fields: %s).', $location, $field->getField(), $owner, implode(', ', $fields));
                continue;
            }
            $error = $mismatch($field);
            if ($error !== null) {
                $errors[] = sprintf('%s: %s.', $location, $error);
            }
        }

        return $errors;
    }
}
