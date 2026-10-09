<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Value;

use BackedEnum;
use ReflectionClass;
use ReflectionEnum;
use ReflectionNamedType;
use ReflectionParameter;
use Saij\WpAppFramework\Block\Content;
use Saij\WpAppFramework\Block\Hidden;
use Saij\WpAppFramework\Block\Image;
use Saij\WpAppFramework\Block\Label;
use Saij\WpAppFramework\Block\Labeled;
use Saij\WpAppFramework\Block\PlainText;
use Saij\WpAppFramework\Block\RichText;
use Saij\WpAppFramework\DependencyInjection\NoService;
use Throwable;

/**
 * Derives the JSON schema and the runtime description of typed values during
 * the build: block attributes, their value classes and REST arguments.
 *
 * Supported types are `int`, `float`, `string`, `bool`, backed enums, value
 * classes and `array` with `#[ListOf]`, each optionally nullable. A value class
 * is `readonly` and declares all its values as public promoted constructor
 * parameters. Enums and value classes must be among the scanned classes.
 *
 * For the block editor it also describes the inline fields of a value (their
 * paths relative to the value, `*` for list items) and the control of the
 * generated inspector (null for inline and hidden values).
 *
 * @phpstan-import-type Value from ValueFactory
 * @phpstan-import-type ValueField from ValueFactory
 * @phpstan-type EditorField array{path: string, kind: 'plain'|'rich'|'image', label: string, allowedFormats?: list<string>}
 * @phpstan-type Control array<string, mixed>
 * @phpstan-type Field array{schema: array<string, mixed>, value: Value, content: bool, fields: list<EditorField>, control: Control|null}
 * @phpstan-type Markers array{content: bool, url: bool, kind: 'plain'|'rich'|'image'|null, allowedFormats: list<string>|null, label: string, hidden: bool}
 */
#[NoService]
final class ValueSchemaBuilder
{
    public const string SUPPORTED = 'use int, float, string, bool, a backed enum, a value class or array with #[ListOf]';

    private const array SCALAR_TYPES = ['int' => 'integer', 'float' => 'number', 'string' => 'string', 'bool' => 'boolean'];

    /** @var array<class-string, array<string, Field>|null> Fields of checked value classes; null if a class is invalid. */
    private array $objects = [];

    /** @var list<class-string> Value classes being checked, to detect recursion. */
    private array $pending = [];

    /** @var list<string> */
    private array $errors = [];

    /**
     * @param array<class-string, ReflectionClass<object>> $classes All scanned classes.
     */
    public function __construct(private readonly array $classes)
    {
    }

    /**
     * Schema, runtime description and content flag of a typed parameter.
     *
     * The schema contains the type keywords only (`type`, `enum`, `format`,
     * `properties`, `items`); callers add `required`, `default` or `role`.
     *
     * @return Field|null Null if the parameter is invalid; the error is recorded.
     */
    public function parameter(ReflectionParameter $parameter, string $location): ?array
    {
        $type = $parameter->getType();
        if ($type === null) {
            $this->errors[] = sprintf('%s has no type; %s.', $location, self::SUPPORTED);

            return null;
        }
        if (!$type instanceof ReflectionNamedType || $type->getName() === 'mixed' || $type->getName() === 'null') {
            $this->errors[] = sprintf('%s: type %s is not supported; %s, optionally nullable.', $location, $type, self::SUPPORTED);

            return null;
        }

        $markers = $this->markers($parameter, $type, $location);
        if ($markers === null) {
            return null;
        }

        $nullable = $type->allowsNull();
        $name = $type->getName();
        $listOf = $this->listOf($parameter, $location);
        if ($listOf === false) {
            return null;
        }

        if ($name === 'array') {
            if ($listOf === null) {
                $this->errors[] = sprintf('%s is an array and needs #[ListOf(Item::class)] with a backed enum or a value class.', $location);

                return null;
            }
            $item = $this->classField($listOf, false, sprintf('%s: #[ListOf]', $location));
            if ($item === null) {
                return null;
            }

            return [
                'schema' => ['type' => self::type('array', $nullable), 'items' => $item['schema']],
                'value' => ['type' => 'list', 'items' => $item['value'], 'nullable' => $nullable],
                'content' => $markers['content'] || $item['content'],
                'fields' => self::prefixFields('*', $item['fields']),
                'control' => $markers['hidden'] ? null : [
                    'type' => 'list',
                    'label' => $markers['label'],
                    'nullable' => $nullable,
                    'item' => $item['control'],
                ],
            ];
        }

        if ($listOf !== null) {
            $this->errors[] = sprintf('%s has #[ListOf], but is not an array.', $location);

            return null;
        }

        if (isset(self::SCALAR_TYPES[$name])) {
            $schema = ['type' => self::type(self::SCALAR_TYPES[$name], $nullable)];
            if ($markers['url']) {
                $schema['format'] = 'uri';
            }

            $fields = [];
            if ($markers['kind'] !== null) {
                $field = ['path' => '', 'kind' => $markers['kind'], 'label' => $markers['label']];
                if ($markers['allowedFormats'] !== null) {
                    $field['allowedFormats'] = $markers['allowedFormats'];
                }
                $fields[] = $field;
            }
            $control = ['type' => $name, 'label' => $markers['label'], 'nullable' => $nullable];
            if ($markers['url']) {
                $control['url'] = true;
            }

            return [
                'schema' => $schema,
                'value' => ['type' => $name, 'nullable' => $nullable],
                'content' => $markers['content'],
                'fields' => $fields,
                'control' => $markers['kind'] !== null || $markers['hidden'] ? null : $control,
            ];
        }

        if ($type->isBuiltin()) {
            $this->errors[] = sprintf('%s: type %s is not supported; %s.', $location, $name, self::SUPPORTED);

            return null;
        }

        $field = $this->classField($name, $nullable, $location);
        if ($field === null) {
            return null;
        }
        $field['content'] = $field['content'] || $markers['content'];
        // An object whose values are all inline or hidden needs no control of its own.
        $field['control'] = $markers['hidden'] || $field['control'] === null || ($field['control']['type'] === 'object' && $field['control']['fields'] === [])
            ? null
            : ['type' => $field['control']['type'], 'label' => $markers['label'], 'nullable' => $nullable] + $field['control'];

        return $field;
    }

    /**
     * The fields of a value class, by parameter name.
     *
     * @param class-string $class
     * @param string       $location Where the class is used, for error messages.
     * @return array<string, Field>|null Null if the class is invalid; the errors are recorded.
     */
    public function objectFields(string $class, string $location): ?array
    {
        if (array_key_exists($class, $this->objects)) {
            return $this->objects[$class];
        }

        if (in_array($class, $this->pending, true)) {
            $this->errors[] = sprintf('%s: value class %s contains itself (%s); recursive value classes are not supported.', $location, $class, implode(' -> ', [...$this->pending, $class]));

            return null;
        }

        $reflection = $this->classes[$class] ?? null;
        if ($reflection === null) {
            $this->errors[] = sprintf('%s: %s is outside the scanned PSR-4 directories of the theme and the framework.', $location, $class);

            return $this->objects[$class] = null;
        }

        $error = $this->valueClassError($reflection);
        if ($error !== null) {
            $this->errors[] = sprintf('%s: %s', $location, $error);

            return $this->objects[$class] = null;
        }

        $this->pending[] = $class;
        $fields = [];
        $valid = true;
        foreach ($reflection->getConstructor()?->getParameters() ?? [] as $parameter) {
            $field = $this->parameter($parameter, sprintf('parameter $%s of %s::__construct()', $parameter->getName(), $class));
            if ($field === null) {
                $valid = false;
                continue;
            }
            $fields[$parameter->getName()] = $field;
        }
        array_pop($this->pending);

        return $this->objects[$class] = $valid ? $fields : null;
    }

    /**
     * Whether a class is among the scanned classes.
     */
    public function hasClass(string $class): bool
    {
        return isset($this->classes[$class]);
    }

    /**
     * The reflection of a scanned class.
     *
     * @param class-string $class
     * @return ReflectionClass<object>
     */
    public function getClass(string $class): ReflectionClass
    {
        return $this->classes[$class];
    }

    /**
     * The JSON representation of a parameter default: enums become their value,
     * value objects arrays of their values.
     */
    public static function export(mixed $value): mixed
    {
        if ($value instanceof BackedEnum) {
            return $value->value;
        }
        if (is_object($value)) {
            return self::export(get_object_vars($value));
        }
        if (is_array($value)) {
            return array_map(self::export(...), $value);
        }

        return $value;
    }

    /**
     * Runtime descriptions of all value classes, for the manifest.
     *
     * @return array<class-string, array<string, ValueField>>
     */
    public function values(): array
    {
        $values = [];
        foreach ($this->objects as $class => $fields) {
            if ($fields === null) {
                continue;
            }
            $parameters = $this->classes[$class]->getConstructor()?->getParameters() ?? [];
            $values[$class] = [];
            foreach ($parameters as $parameter) {
                $values[$class][$parameter->getName()] = [
                    'value' => $fields[$parameter->getName()]['value'],
                    'required' => !$parameter->isDefaultValueAvailable(),
                ];
            }
        }
        ksort($values, SORT_STRING);

        return $values;
    }

    /**
     * @return list<string>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * @return Markers|null
     */
    private function markers(ReflectionParameter $parameter, ReflectionNamedType $type, string $location): ?array
    {
        $plain = $parameter->getAttributes(PlainText::class) !== [];
        $richAttributes = $parameter->getAttributes(RichText::class);
        $rich = $richAttributes !== [];
        $image = $parameter->getAttributes(Image::class) !== [];
        $content = $parameter->getAttributes(Content::class) !== [];
        $url = $parameter->getAttributes(Url::class) !== [];
        $hidden = $parameter->getAttributes(Hidden::class) !== [];
        $labelAttributes = $parameter->getAttributes(Label::class);
        $isString = $type->getName() === 'string';
        $kinds = array_keys(array_filter(['PlainText' => $plain, 'RichText' => $rich, 'Image' => $image]));
        $kind = $kinds[0] ?? null;

        $error = match (true) {
            count($kinds) > 1 => sprintf('has #[%s]; use only one of #[PlainText], #[RichText] and #[Image]', implode('] and #[', $kinds)),
            $kind !== null && $content => sprintf('has #[%s] and #[Content]; #[%1$s] already marks the field as content', $kind),
            ($plain || $rich) && !$isString => sprintf('has #[%s], which is only allowed on strings', $kind),
            $image && ($type->getName() !== 'int' || !$type->allowsNull()) => 'has #[Image], which is only allowed on ?int (the attachment ID, null without image)',
            $url && !$isString => 'has #[Url], which is only allowed on strings',
            $url && ($plain || $rich) => sprintf('has #[Url] and #[%s]; a URL is not inline text, mark it with #[Content] instead', $kind),
            $hidden && $kind !== null => sprintf('has #[Hidden] and #[%s]; inline fields have no inspector control to hide', $kind),
            default => null,
        };
        if ($error !== null) {
            $this->errors[] = sprintf('%s %s.', $location, $error);

            return null;
        }

        try {
            $allowedFormats = $rich ? $richAttributes[0]->newInstance()->allowedFormats : null;
            $label = $labelAttributes !== [] ? $labelAttributes[0]->newInstance()->label : self::humanize($parameter->getName());
        } catch (Throwable $exception) {
            $this->errors[] = sprintf('Invalid #[RichText] or #[Label] on %s: %s', $location, $exception->getMessage());

            return null;
        }

        if ($allowedFormats !== null && (!array_is_list($allowedFormats) || array_filter($allowedFormats, static fn (mixed $format): bool => !is_string($format) || $format === '') !== [])) {
            $this->errors[] = sprintf('%s: allowedFormats of #[RichText] must be a list of format names such as "core/bold", or null for all formats.', $location);

            return null;
        }
        if (trim($label) === '') {
            $this->errors[] = sprintf('%s has an empty #[Label].', $location);

            return null;
        }

        /** @var list<string>|null $allowedFormats */
        return [
            'content' => $kind !== null || $content,
            'url' => $url,
            'kind' => match ($kind) {
                'PlainText' => 'plain',
                'RichText' => 'rich',
                'Image' => 'image',
                default => null,
            },
            'allowedFormats' => $allowedFormats,
            'label' => $label,
            'hidden' => $hidden,
        ];
    }

    /**
     * A label from a parameter name: "imageId" becomes "Image id".
     */
    private static function humanize(string $name): string
    {
        return ucfirst(strtolower((string) preg_replace('/(?<=[a-z0-9])([A-Z])/', ' $1', $name)));
    }

    /**
     * @param list<EditorField> $fields
     * @return list<EditorField>
     */
    private static function prefixFields(string $prefix, array $fields): array
    {
        return array_map(
            static fn (array $field): array => ['path' => $field['path'] === '' ? $prefix : $prefix . '.' . $field['path']] + $field,
            $fields,
        );
    }

    /**
     * @return class-string|null|false The item class, null without #[ListOf], false if #[ListOf] is invalid.
     */
    private function listOf(ReflectionParameter $parameter, string $location): string|null|false
    {
        $attributes = $parameter->getAttributes(ListOf::class);
        if ($attributes === []) {
            return null;
        }

        try {
            return $attributes[0]->newInstance()->class;
        } catch (Throwable $exception) {
            $this->errors[] = sprintf('Invalid #[ListOf] on %s: %s', $location, $exception->getMessage());

            return false;
        }
    }

    /**
     * A backed enum or a value class.
     *
     * @return Field|null
     */
    private function classField(string $class, bool $nullable, string $location): ?array
    {
        if (enum_exists($class)) {
            if (!is_subclass_of($class, BackedEnum::class)) {
                $this->errors[] = sprintf('%s: enum %s is not backed; use a backed enum so its values can be stored.', $location, $class);

                return null;
            }
            if (!isset($this->classes[$class])) {
                $this->errors[] = sprintf('%s: enum %s is outside the scanned PSR-4 directories of the theme and the framework.', $location, $class);

                return null;
            }

            $values = array_map(static fn (BackedEnum $case): int|string => $case->value, $class::cases());
            $backing = (string) new ReflectionEnum($class)->getBackingType() === 'int' ? 'integer' : 'string';
            $options = $this->enumOptions($class, $location);
            if ($options === null) {
                return null;
            }

            return [
                'schema' => ['type' => self::type($backing, $nullable), 'enum' => $nullable ? [...$values, null] : $values],
                'value' => ['type' => 'enum', 'class' => $class, 'nullable' => $nullable],
                'content' => false,
                'fields' => [],
                'control' => ['type' => 'enum', 'nullable' => $nullable, 'options' => $options],
            ];
        }

        if (!class_exists($class)) {
            $this->errors[] = sprintf('%s: %s is not a class or backed enum.', $location, $class);

            return null;
        }

        if (is_subclass_of($class, Labeled::class)) {
            $this->errors[] = sprintf('%s: value class %s implements %s, which only labels the cases of backed enums.', $location, $class, Labeled::class);

            return null;
        }

        $fields = $this->objectFields($class, $location);
        if ($fields === null) {
            return null;
        }

        $properties = [];
        $content = false;
        $editorFields = [];
        $controls = [];
        foreach ($this->classes[$class]->getConstructor()?->getParameters() ?? [] as $parameter) {
            $field = $fields[$parameter->getName()];
            $schema = $field['schema'];
            if (!$parameter->isDefaultValueAvailable()) {
                $schema['required'] = true;
            } elseif ($parameter->getDefaultValue() !== null) {
                $schema['default'] = self::export($parameter->getDefaultValue());
            }
            $properties[$parameter->getName()] = $schema;
            $content = $content || $field['content'];
            array_push($editorFields, ...self::prefixFields($parameter->getName(), $field['fields']));
            if ($field['control'] !== null) {
                $controls[$parameter->getName()] = $field['control'];
            }
        }

        /** @var class-string $class */
        return [
            'schema' => ['type' => self::type('object', $nullable), 'properties' => $properties],
            'value' => ['type' => 'object', 'class' => $class, 'nullable' => $nullable],
            'content' => $content,
            'fields' => $editorFields,
            'control' => ['type' => 'object', 'nullable' => $nullable, 'fields' => $controls],
        ];
    }

    /**
     * Value and label of each case; the label comes from Labeled::label() or is the value.
     *
     * @param class-string<BackedEnum> $class
     * @return list<array{value: int|string, label: string}>|null
     */
    private function enumOptions(string $class, string $location): ?array
    {
        $options = [];
        foreach ($class::cases() as $case) {
            $label = (string) $case->value;
            if ($case instanceof Labeled) {
                try {
                    $label = $case->label();
                } catch (Throwable $exception) {
                    $this->errors[] = sprintf('%s: %s::%s->label() failed: %s', $location, $class, $case->name, $exception->getMessage());

                    return null;
                }
                if (trim($label) === '') {
                    $this->errors[] = sprintf('%s: %s::%s->label() returned an empty label.', $location, $class, $case->name);

                    return null;
                }
            }
            $options[] = ['value' => $case->value, 'label' => $label];
        }

        return $options;
    }

    /**
     * @param ReflectionClass<object> $class
     */
    private function valueClassError(ReflectionClass $class): ?string
    {
        $name = $class->getName();

        if ($class->isInterface() || $class->isTrait() || $class->isAbstract() || $class->isEnum()) {
            return sprintf('%s cannot be a value class: it must be a concrete readonly class.', $name);
        }
        if (!$class->isReadOnly()) {
            return sprintf('value class %s must be declared readonly.', $name);
        }

        $constructor = $class->getConstructor();
        if ($constructor !== null && !$constructor->isPublic()) {
            return sprintf('value class %s needs a public constructor.', $name);
        }

        $promoted = [];
        foreach ($constructor?->getParameters() ?? [] as $parameter) {
            if (!$parameter->isPromoted()) {
                return sprintf('parameter $%s of value class %s::__construct() must be a promoted property.', $parameter->getName(), $name);
            }
            if (!$class->getProperty($parameter->getName())->isPublic()) {
                return sprintf('property $%s of value class %s must be public.', $parameter->getName(), $name);
            }
            $promoted[] = $parameter->getName();
        }

        foreach ($class->getProperties() as $property) {
            if (!in_array($property->getName(), $promoted, true)) {
                return sprintf('value class %s declares the property $%s outside the constructor; all values must be promoted constructor parameters.', $name, $property->getName());
            }
        }

        return null;
    }

    /**
     * @return string|list<string>
     */
    private static function type(string $type, bool $nullable): string|array
    {
        return $nullable ? [$type, 'null'] : $type;
    }
}
