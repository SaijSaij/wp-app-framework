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
use Saij\WpAppFramework\Post\AbstractPost;
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
 * References (`#[PostReference]`, `#[TermReference]`, `#[UserReference]`) are
 * IDs on `?int` (one) or `array` (a list of IDs) with a search control; in
 * blocks they count as content.
 *
 * A parameter whose type is a post class (a subclass of AbstractPost: a class with
 * `#[PostType]`, Post or Page) is a reference to a post of that type: stored as ID,
 * created as the post object. `#[PostReference]` and `#[TermReference]` also take
 * post and taxonomy classes instead of names.
 *
 * A `Link` is a value class of the framework with a control of its own (the
 * link control of the block editor, a search form elsewhere); it is never
 * null and counts as content in blocks.
 *
 * Settings groups use the same derivation in settings mode: there is no canvas,
 * so `#[PlainText]`, `#[RichText]` and `#[Image]` get a control on the settings
 * page (rich text with the formats the classic editor offers); `#[Content]` and
 * `postContent` mean nothing there and are errors. Value classes are checked
 * per mode, so one class can be used by blocks and settings.
 *
 * @phpstan-import-type ClassValue from ValueFactory
 * @phpstan-import-type Value from ValueFactory
 * @phpstan-import-type ValueField from ValueFactory
 * @phpstan-type EditorField array{path: string, kind: 'plain'|'rich'|'image', label: string, allowedFormats?: list<string>, inspector?: true, postContent?: true, excerpt?: true, multiline?: true}
 * @phpstan-type Control array<string, mixed>
 * @phpstan-type Field array{schema: array<string, mixed>, value: Value, content: bool, fields: list<EditorField>, control: Control|null}
 * @phpstan-type ClassField array{schema: array<string, mixed>, value: ClassValue, content: bool, fields: list<EditorField>, control: Control|null}
 * @phpstan-import-type Reference from ValueFactory
 * @phpstan-type PostClass array{name: non-empty-string, restBase: non-empty-string}
 * @phpstan-type Markers array{content: bool, url: bool, kind: 'plain'|'rich'|'image'|null, allowedFormats: list<string>|null, inspector: bool, postContent: bool, excerpt: bool, multiline: bool, label: string, hidden: bool, reference: Reference|null}
 */
#[NoService]
final class ValueSchemaBuilder
{
    public const string SUPPORTED = 'use int, float, string, bool, a backed enum, a value class or array with #[ListOf]';

    private const array SCALAR_TYPES = ['int' => 'integer', 'float' => 'number', 'string' => 'string', 'bool' => 'boolean'];

    /** Rich text formats the settings page offers (classic editor): the allowedFormats of #[RichText] in settings groups. */
    public const array SETTINGS_FORMATS = ['core/bold', 'core/italic', 'core/link', 'core/strikethrough', 'core/subscript', 'core/superscript'];

    /** Post type and taxonomy keys, as register_post_type() and register_taxonomy() expect them (sanitize_key()). */
    private const string SLUG_PATTERN = '~^[a-z0-9_-]+$~';

    /** @var array<class-string, array<string, Field>|null> Fields of checked value classes; null if a class is invalid. */
    private array $objects = [];

    /** @var array<class-string, array<string, Field>|null> The same for settings groups (settings mode). */
    private array $settingsObjects = [];

    /** @var array<class-string, array<string, Field>|null> Fields of checked meta classes (#[PostMeta]). */
    private array $metaObjects = [];

    /** @var list<class-string> Value classes being checked, to detect recursion. */
    private array $pending = [];

    /** @var list<string> */
    private array $errors = [];

    /**
     * @param array<class-string, ReflectionClass<object>> $classes    All scanned classes.
     * @param array<class-string, PostClass>               $posts      Post classes (#[PostType], Post, Page) with the name
     *                                                                 and REST base of their post type.
     * @param array<class-string, non-empty-string>        $taxonomies Taxonomy classes (#[Taxonomy]) with their name.
     */
    public function __construct(
        private readonly array $classes,
        private readonly array $posts = [],
        private readonly array $taxonomies = [],
    ) {}

    /**
     * Schema, runtime description and content flag of a typed parameter.
     *
     * The schema contains the type keywords only (`type`, `enum`, `format`,
     * `properties`, `items`); callers add `required`, `default` or `role`.
     *
     * @param bool $settings Settings mode (settings groups): images get a control, block content markers are errors.
     * @return Field|null Null if the parameter is invalid; the error is recorded.
     */
    public function parameter(ReflectionParameter $parameter, string $location, bool $settings = false): ?array
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

        $markers = $this->markers($parameter, $type, $location, $settings);
        if ($markers === null) {
            return null;
        }

        $nullable = $type->allowsNull();
        $name = $type->getName();
        if ($name === Link::class && $nullable) {
            $this->errors[] = sprintf('%s: a link is never null; use Link $%s = new Link(), an empty URL means no link.', $location, $parameter->getName());

            return null;
        }
        $listOf = $this->listOf($parameter, $location);
        if ($listOf === false) {
            return null;
        }

        if ($markers['reference'] !== null) {
            return $this->referenceField($markers, $name, $nullable, $listOf, $location);
        }

        if ($name === 'array') {
            if ($listOf === null) {
                $this->errors[] = sprintf('%s is an array and needs #[ListOf(Item::class)] with a backed enum or a value class.', $location);

                return null;
            }
            if (is_subclass_of($listOf, AbstractPost::class)) {
                $item = $this->postClassField($listOf, false, $markers, sprintf('%s: #[ListOf]', $location), true);
                if ($item === null || !isset($item['value']['reference'])) {
                    return null;
                }

                return [
                    'schema' => ['type' => self::type('array', $nullable), 'items' => ['type' => 'integer']],
                    'value' => ['type' => 'list', 'items' => ['type' => 'int', 'nullable' => false, 'reference' => $item['value']['reference']], 'nullable' => $nullable],
                    'content' => true,
                    'fields' => [],
                    'control' => $markers['hidden'] ? null : ['type' => 'references', 'label' => $markers['label'], 'nullable' => $nullable] + array_diff_key((array) $item['control'], ['type' => true, 'label' => true, 'nullable' => true]),
                ];
            }
            $item = $this->classField($listOf, false, sprintf('%s: #[ListOf]', $location), $settings);
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
            if ($markers['postContent']) {
                // Without selector, WordPress parses the whole inner HTML of the block into the value.
                $schema['source'] = 'html';
            }

            $fields = [];
            $control = ['type' => $name, 'label' => $markers['label'], 'nullable' => $nullable];
            if ($markers['kind'] !== null) {
                $field = ['path' => '', 'kind' => $markers['kind'], 'label' => $markers['label']];
                $control['type'] = $markers['kind'];
                if ($markers['allowedFormats'] !== null) {
                    $field['allowedFormats'] = $markers['allowedFormats'];
                    $control['allowedFormats'] = $markers['allowedFormats'];
                }
                if ($markers['inspector']) {
                    $field['inspector'] = true;
                }
                if ($markers['postContent']) {
                    $field['postContent'] = true;
                }
                if ($markers['excerpt']) {
                    $field['excerpt'] = true;
                }
                if ($markers['multiline']) {
                    $field['multiline'] = true;
                    $control['multiline'] = true;
                }
                $fields[] = $field;
            }
            if ($markers['url']) {
                $control['url'] = true;
            }

            return [
                'schema' => $schema,
                'value' => ['type' => $name, 'nullable' => $nullable],
                'content' => $markers['content'],
                'fields' => $fields,
                // Canvas fields have no inspector control; fields with inspector: true and images in settings have one instead.
                'control' => ($markers['kind'] !== null && !$markers['inspector'] && !$settings) || $markers['hidden'] ? null : $control,
            ];
        }

        if ($type->isBuiltin()) {
            $this->errors[] = sprintf('%s: type %s is not supported; %s.', $location, $name, self::SUPPORTED);

            return null;
        }

        if (is_subclass_of($name, AbstractPost::class)) {
            return $this->postClassField($name, $nullable, $markers, $location);
        }

        $field = $this->classField($name, $nullable, $location, $settings);
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
     * @param bool         $settings Settings mode (settings groups).
     * @return array<string, Field>|null Null if the class is invalid; the errors are recorded.
     */
    public function objectFields(string $class, string $location, bool $settings = false): ?array
    {
        $checked = $settings ? $this->settingsObjects : $this->objects;
        if (array_key_exists($class, $checked)) {
            return $checked[$class];
        }

        if (in_array($class, $this->pending, true)) {
            $this->errors[] = sprintf('%s: value class %s contains itself (%s); recursive value classes are not supported.', $location, $class, implode(' -> ', [...$this->pending, $class]));

            return null;
        }

        $reflection = $this->classes[$class] ?? null;
        if ($reflection === null) {
            $this->errors[] = sprintf('%s: %s is outside the scanned PSR-4 directories of the project and the framework.', $location, $class);

            return $this->remember($class, null, $settings);
        }

        $error = $this->valueClassError($reflection);
        if ($error !== null) {
            $this->errors[] = sprintf('%s: %s', $location, $error);

            return $this->remember($class, null, $settings);
        }

        $this->pending[] = $class;
        $fields = [];
        $valid = true;
        foreach ($reflection->getConstructor()?->getParameters() ?? [] as $parameter) {
            $field = $this->parameter($parameter, sprintf('parameter $%s of %s::__construct()', $parameter->getName(), $class), $settings);
            if ($field === null) {
                $valid = false;
                continue;
            }
            $fields[$parameter->getName()] = $field;
        }
        array_pop($this->pending);

        return $this->remember($class, $valid ? $fields : null, $settings);
    }

    /**
     * The fields of a meta class (#[PostMeta]), by parameter name, derived like a settings group (form mode). Unlike a
     * value class, a meta class is not readonly and its promoted parameters may be private or protected: the post
     * object changes the values through the class's own setters.
     *
     * @param class-string $class
     * @return array<string, Field>|null Null if the class is invalid; the errors are recorded.
     */
    public function metaFields(string $class, string $location): ?array
    {
        if (array_key_exists($class, $this->metaObjects)) {
            return $this->metaObjects[$class];
        }
        $reflection = $this->classes[$class];
        $error = self::metaClassError($reflection);
        if ($error !== null) {
            $this->errors[] = sprintf('%s: %s', $location, $error);

            return $this->metaObjects[$class] = null;
        }

        $fields = [];
        $valid = true;
        foreach ($reflection->getConstructor()?->getParameters() ?? [] as $parameter) {
            $field = $this->parameter($parameter, sprintf('parameter $%s of meta class %s::__construct()', $parameter->getName(), $class), true);
            if ($field === null) {
                $valid = false;
                continue;
            }
            $fields[$parameter->getName()] = $field;
        }

        return $this->metaObjects[$class] = $valid ? $fields : null;
    }

    /**
     * @param ReflectionClass<object> $class
     */
    private static function metaClassError(ReflectionClass $class): ?string
    {
        $name = $class->getName();
        if ($class->isInterface() || $class->isTrait() || $class->isAbstract() || $class->isEnum()) {
            return sprintf('meta class %s must be a concrete class.', $name);
        }
        if ($class->isReadOnly()) {
            return sprintf('meta class %s must not be readonly: the post object changes its values, and save() stores them.', $name);
        }
        $constructor = $class->getConstructor();
        if ($constructor !== null && !$constructor->isPublic()) {
            return sprintf('meta class %s needs a public constructor.', $name);
        }

        $promoted = [];
        foreach ($constructor?->getParameters() ?? [] as $parameter) {
            if (!$parameter->isPromoted()) {
                return sprintf('parameter $%s of meta class %s::__construct() must be a promoted property.', $parameter->getName(), $name);
            }
            $promoted[] = $parameter->getName();
        }
        foreach ($class->getProperties() as $property) {
            if (!$property->isStatic() && !in_array($property->getName(), $promoted, true)) {
                return sprintf('meta class %s declares the property $%s outside the constructor; it would not be stored. All fields are promoted constructor parameters.', $name, $property->getName());
            }
        }

        return null;
    }

    /**
     * A reference to a post of a post class: stored as ID, created as the post object.
     *
     * @param class-string<AbstractPost> $class
     * @param Markers      $markers
     * @param bool         $item     An item of a list (never null; a missing post is left out).
     * @return Field|null
     */
    private function postClassField(string $class, bool $nullable, array $markers, string $location, bool $item = false): ?array
    {
        $post = $this->posts[$class] ?? null;
        $error = match (true) {
            $post === null => sprintf('%s: %s is no post class of this theme or plugin; post classes have #[PostType] (or are Post or Page) and extend AbstractPost.', $location, $class),
            !$nullable && !$item => sprintf('%s: a reference to a %s must be nullable (null without post).', $location, self::shortName($class)),
            $markers['reference'] !== null || $markers['kind'] !== null || $markers['content'] || $markers['url'] => sprintf('%s: a reference to a %s takes no #[PostReference], #[TermReference], #[UserReference], #[PlainText], #[RichText], #[Image], #[Content] or #[Url].', $location, self::shortName($class)),
            default => null,
        };
        if ($error !== null || $post === null) {
            $this->errors[] = (string) $error;

            return null;
        }

        $reference = ['target' => 'post', 'subtypes' => [$post['name']], 'class' => $class];

        return [
            'schema' => ['type' => $item ? 'integer' : ['integer', 'null']],
            'value' => ['type' => 'int', 'nullable' => !$item, 'reference' => $reference],
            'content' => true,
            'fields' => [],
            'control' => $markers['hidden'] ? null : [
                'type' => 'reference',
                'label' => $markers['label'],
                'nullable' => true,
                'target' => 'post',
                'subtypes' => [$post['name']],
                'restBase' => $post['restBase'],
            ],
        ];
    }

    /**
     * @param class-string              $class
     * @param array<string, Field>|null $fields
     * @return array<string, Field>|null
     */
    private function remember(string $class, ?array $fields, bool $settings): ?array
    {
        if ($settings) {
            return $this->settingsObjects[$class] = $fields;
        }

        return $this->objects[$class] = $fields;
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
        // The value descriptions do not depend on the mode.
        foreach ([...$this->metaObjects, ...$this->settingsObjects, ...$this->objects] as $class => $fields) {
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
        // A value class used by blocks and settings reports its errors once.
        return array_values(array_unique($this->errors));
    }

    /**
     * @return Markers|null
     */
    private function markers(ReflectionParameter $parameter, ReflectionNamedType $type, string $location, bool $settings): ?array
    {
        $plainAttributes = $parameter->getAttributes(PlainText::class);
        $plain = $plainAttributes !== [];
        $richAttributes = $parameter->getAttributes(RichText::class);
        $rich = $richAttributes !== [];
        $imageAttributes = $parameter->getAttributes(Image::class);
        $image = $imageAttributes !== [];
        $content = $parameter->getAttributes(Content::class) !== [];
        $url = $parameter->getAttributes(Url::class) !== [];
        $hidden = $parameter->getAttributes(Hidden::class) !== [];
        $labelAttributes = $parameter->getAttributes(Label::class);
        $referenceAttributes = [
            ...$parameter->getAttributes(PostReference::class),
            ...$parameter->getAttributes(TermReference::class),
            ...$parameter->getAttributes(UserReference::class),
        ];
        $isString = $type->getName() === 'string';
        $kinds = array_keys(array_filter(['PlainText' => $plain, 'RichText' => $rich, 'Image' => $image]));
        $kind = $kinds[0] ?? null;

        $error = match (true) {
            count($referenceAttributes) > 1 => 'has several references; use one of #[PostReference], #[TermReference] and #[UserReference]',
            $type->getName() === Link::class && ($referenceAttributes !== [] || $kind !== null || $content || $url) => sprintf(
                'is a Link and has #[%s]; a link is content with its own control',
                $referenceAttributes !== [] ? self::shortName($referenceAttributes[0]->getName()) : ($kind ?? ($content ? 'Content' : 'Url')),
            ),
            $referenceAttributes !== [] && ($kind !== null || $content || $url) => sprintf(
                'has #[%s] together with #[%s]; a reference is an ID with its own control and counts as content in blocks',
                self::shortName($referenceAttributes[0]->getName()),
                $kind ?? ($content ? 'Content' : 'Url'),
            ),
            $settings && $content => 'has #[Content], which marks the content of blocks; settings groups and post meta have no block content',
            count($kinds) > 1 => sprintf('has #[%s]; use only one of #[PlainText], #[RichText] and #[Image]', implode('] and #[', $kinds)),
            $kind !== null && $content => sprintf('has #[%s] and #[Content]; #[%1$s] already marks the field as content', $kind),
            ($plain || $rich) && !$isString => sprintf('has #[%s], which is only allowed on strings', $kind),
            $image && ($type->getName() !== 'int' || !$type->allowsNull()) => 'has #[Image], which is only allowed on ?int (the attachment ID, null without image)',
            $url && !$isString => 'has #[Url], which is only allowed on strings',
            $url && ($plain || $rich) => sprintf('has #[Url] and #[%s]; a URL is not inline text, mark it with #[Content] instead', $kind),
            $hidden && $kind !== null => sprintf('has #[Hidden] and #[%s]; the field is edited in the canvas or, with inspector: true, in the inspector, so it cannot be hidden', $kind),
            default => null,
        };
        if ($error !== null) {
            $this->errors[] = sprintf('%s %s.', $location, $error);

            return null;
        }

        try {
            $reference = $referenceAttributes === [] ? null : $this->reference($referenceAttributes[0]->newInstance(), $location);
            if ($reference === false) {
                return null;
            }
            $kindAttribute = ($plainAttributes[0] ?? $richAttributes[0] ?? $imageAttributes[0] ?? null)?->newInstance();
            $allowedFormats = $kindAttribute instanceof RichText ? $kindAttribute->allowedFormats : null;
            $inspector = $kindAttribute->inspector ?? false;
            $postContent = $kindAttribute instanceof RichText && $kindAttribute->postContent;
            $excerpt = ($kindAttribute instanceof RichText || $kindAttribute instanceof PlainText) && $kindAttribute->excerpt;
            $multiline = $kindAttribute instanceof PlainText && $kindAttribute->multiline;
            $label = $labelAttributes !== [] ? $labelAttributes[0]->newInstance()->label : self::humanize($parameter->getName());
        } catch (Throwable $exception) {
            $this->errors[] = sprintf('Invalid #[RichText], #[Label] or reference on %s: %s', $location, $exception->getMessage());

            return null;
        }

        if ($allowedFormats !== null && (!array_is_list($allowedFormats) || array_filter($allowedFormats, static fn(mixed $format): bool => !is_string($format) || $format === '') !== [])) {
            $this->errors[] = sprintf('%s: allowedFormats of #[RichText] must be a list of format names such as "core/bold", or null for all formats.', $location);

            return null;
        }
        if (trim($label) === '') {
            $this->errors[] = sprintf('%s has an empty #[Label].', $location);

            return null;
        }
        $invalidSlugs = $reference !== null && $reference['subtypes'] !== null && (
            $reference['subtypes'] === []
            || !array_is_list($reference['subtypes'])
            || array_filter($reference['subtypes'], static fn(mixed $slug): bool => !is_string($slug) || preg_match(self::SLUG_PATTERN, $slug) !== 1) !== []
        );
        $unknownFormats = $settings && $allowedFormats !== null ? array_diff($allowedFormats, self::SETTINGS_FORMATS) : [];
        $error = match (true) {
            $invalidSlugs && $reference['target'] === 'post' => sprintf('%s: postTypes of #[PostReference] must be a non-empty list of post type keys (lowercase letters, digits, "-" and "_"), or null for all.', $location),
            $invalidSlugs => sprintf('%s: taxonomies of #[TermReference] must be a non-empty list of taxonomy keys (lowercase letters, digits, "-" and "_"), or null for all.', $location),
            $settings && $postContent => sprintf('%s has #[RichText(postContent: true)], which stores the value in the post content of a block; settings groups and post meta have no post content.', $location),
            $unknownFormats !== [] => sprintf(
                '%s: allowedFormats of #[RichText] in a settings group or post meta may only contain the formats of the classic editor (%s), not %s.',
                $location,
                implode(', ', self::SETTINGS_FORMATS),
                implode(', ', $unknownFormats),
            ),
            default => null,
        };
        if ($error !== null) {
            $this->errors[] = $error;

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
            'inspector' => $inspector,
            'postContent' => $postContent,
            'excerpt' => $excerpt,
            'multiline' => $multiline,
            'label' => $label,
            'hidden' => $hidden,
            'reference' => $reference,
        ];
    }

    /**
     * The reference of an attribute; post and taxonomy classes in its list become their names.
     *
     * @return Reference|false False if the list names a class that is no post or taxonomy class; the error is recorded.
     */
    private function reference(PostReference|TermReference|UserReference $attribute, string $location): array|false
    {
        [$target, $subtypes, $classes, $argument] = match (true) {
            $attribute instanceof PostReference => ['post', $attribute->postTypes, $this->posts, 'postTypes of #[PostReference]'],
            $attribute instanceof TermReference => ['term', $attribute->taxonomies, $this->taxonomies, 'taxonomies of #[TermReference]'],
            default => ['user', null, [], ''],
        };
        if (is_array($subtypes)) {
            foreach ($subtypes as $index => $subtype) {
                if (!is_string($subtype) || !str_contains($subtype, '\\')) {
                    continue;
                }
                $known = $classes[$subtype] ?? null;
                if ($known === null) {
                    $this->errors[] = sprintf(
                        '%s: %s contains %s, which is no %s of this theme or plugin.',
                        $location,
                        $argument,
                        $subtype,
                        $target === 'post' ? 'post class (#[PostType], Post or Page)' : 'taxonomy class (#[Taxonomy], Category or Tag)',
                    );

                    return false;
                }
                $subtypes[$index] = is_array($known) ? $known['name'] : $known;
            }
        }

        /** @var Reference */
        return ['target' => $target, 'subtypes' => $subtypes];
    }

    private static function shortName(string $class): string
    {
        return substr($class, (int) strrpos($class, '\\') + 1);
    }

    /**
     * A reference: one ID on ?int, a list of IDs on array.
     *
     * @param Markers $markers
     * @return Field|null
     */
    private function referenceField(array $markers, string $type, bool $nullable, ?string $listOf, string $location): ?array
    {
        /** @var Reference $reference */
        $reference = $markers['reference'];
        $attribute = match ($reference['target']) {
            'post' => '#[PostReference]',
            'term' => '#[TermReference]',
            'user' => '#[UserReference]',
        };
        $error = match (true) {
            $listOf !== null => sprintf('%s has %s and #[ListOf]; a reference on an array is a list of IDs without #[ListOf].', $location, $attribute),
            $type === 'int' && !$nullable => sprintf('%s has %s on int; use ?int (null without reference) or array (a list of IDs).', $location, $attribute),
            $type !== 'int' && $type !== 'array' => sprintf('%s has %s, which is only allowed on ?int (one ID) or array (a list of IDs).', $location, $attribute),
            default => null,
        };
        if ($error !== null) {
            $this->errors[] = $error;

            return null;
        }

        $control = ['label' => $markers['label'], 'nullable' => $nullable, 'target' => $reference['target'], 'subtypes' => $reference['subtypes']];
        if ($type === 'int') {
            return [
                'schema' => ['type' => ['integer', 'null']],
                'value' => ['type' => 'int', 'nullable' => true, 'reference' => $reference],
                'content' => true,
                'fields' => [],
                'control' => $markers['hidden'] ? null : ['type' => 'reference', ...$control],
            ];
        }

        return [
            'schema' => ['type' => self::type('array', $nullable), 'items' => ['type' => 'integer']],
            'value' => ['type' => 'list', 'items' => ['type' => 'int', 'nullable' => false, 'reference' => $reference], 'nullable' => $nullable],
            'content' => true,
            'fields' => [],
            'control' => $markers['hidden'] ? null : ['type' => 'references', ...$control],
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
            static fn(array $field): array => ['path' => $field['path'] === '' ? $prefix : $prefix . '.' . $field['path']] + $field,
            $fields,
        );
    }

    /**
     * @return class-string|null|false The item class, null without #[ListOf], false if #[ListOf] is invalid.
     */
    private function listOf(ReflectionParameter $parameter, string $location): string|false|null
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
     * @return ClassField|null
     */
    private function classField(string $class, bool $nullable, string $location, bool $settings): ?array
    {
        if (enum_exists($class)) {
            if (!is_subclass_of($class, BackedEnum::class)) {
                $this->errors[] = sprintf('%s: enum %s is not backed; use a backed enum so its values can be stored.', $location, $class);

                return null;
            }
            if (!isset($this->classes[$class])) {
                $this->errors[] = sprintf('%s: enum %s is outside the scanned PSR-4 directories of the project and the framework.', $location, $class);

                return null;
            }

            $values = array_map(static fn(BackedEnum $case): int|string => $case->value, $class::cases());
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

        $fields = $this->objectFields($class, $location, $settings);
        if ($fields === null) {
            return null;
        }

        $properties = [];
        $content = false;
        $editorFields = [];
        $controls = [];
        foreach ($this->classes[$class]->getConstructor()?->getParameters() ?? [] as $parameter) {
            $field = $fields[$parameter->getName()];
            if (array_filter($field['fields'], static fn(array $editorField): bool => $editorField['path'] === '' && ($editorField['postContent'] ?? false)) !== []) {
                $this->errors[] = sprintf(
                    '%s: parameter $%s of %s::__construct() has #[RichText(postContent: true)], which is only allowed on a parameter of the block attribute class itself, not in value classes or list items.',
                    $location,
                    $parameter->getName(),
                    $class,
                );

                return null;
            }
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
            // A link is content with a control of its own instead of the controls of its values.
            'content' => $content || $class === Link::class,
            'fields' => $editorFields,
            'control' => $class === Link::class
                ? ['type' => 'link', 'nullable' => false]
                : ['type' => 'object', 'nullable' => $nullable, 'fields' => $controls],
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
