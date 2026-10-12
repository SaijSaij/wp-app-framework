<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Block;

use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use Saij\WpAppFramework\Asset\ScriptKind;
use Saij\WpAppFramework\Block\Support\SupportCollector;
use Saij\WpAppFramework\Block\Support\UseSupport;
use Saij\WpAppFramework\DependencyInjection\NoService;
use Saij\WpAppFramework\DependencyInjection\ServiceResolver;
use Saij\WpAppFramework\Manifest\EntryPointInspector;
use Saij\WpAppFramework\Value\ValueSchemaBuilder;
use Throwable;

/**
 * Collects and validates `#[Block]` classes during the build, derives the
 * attribute schemas from their attribute classes and resolves the services.
 *
 * @phpstan-import-type BlockArgument from BlockDefinition
 * @phpstan-import-type BlockEditor from BlockDefinition
 * @phpstan-import-type BlockField from BlockDefinition
 * @phpstan-import-type BlockSupportEntry from BlockDefinition
 * @phpstan-import-type Control from ValueSchemaBuilder
 * @phpstan-import-type EditorField from ValueSchemaBuilder
 */
#[NoService]
final class BlockCollector
{
    /** Same rule as WP_Block_Type_Registry::register(). */
    private const string NAME_PATTERN = '~^[a-z0-9-]+/[a-z0-9-]+$~';

    private const string BLOCK_CLASS = 'WP_Block';

    /**
     * Attributes WordPress adds itself: `lock` and `metadata` to every block
     * (WP_Block_Type::GLOBAL_ATTRIBUTES), `className`, `anchor` and `align`
     * with the supports of the same name, `style` with `customCSS` (enabled by
     * default) and the design supports. Reserved regardless of the declared
     * supports, so enabling a support later never clashes with an attribute.
     */
    public const array RESERVED_ATTRIBUTES = ['lock', 'metadata', 'className', 'anchor', 'align', 'style'];

    /** @var list<string> */
    private array $errors = [];

    /** @var array<string, class-string> Block name => class, to find duplicates. */
    private array $names = [];

    private readonly EntryPointInspector $inspector;

    /**
     * @param string      $frameworkDirectory Source directory of the framework, which must not declare blocks.
     * @param string|null $scriptDirectory    Directory the consumer's build compiles scripts from (its `src/`);
     *                                        editor and view scripts must be below it. Null if the consumer has none.
     */
    public function __construct(
        private readonly ServiceResolver $resolver,
        private readonly ValueSchemaBuilder $values,
        private readonly SupportCollector $supports,
        private readonly string $frameworkDirectory,
        private readonly ?string $scriptDirectory = null,
    ) {
        $this->inspector = new EntryPointInspector($resolver);
    }

    /**
     * @param array<class-string, ReflectionClass<object>> $classes All scanned classes.
     * @return list<BlockDefinition>
     */
    public function collect(array $classes): array
    {
        $blocks = [];

        foreach ($classes as $name => $class) {
            $attributes = $class->getAttributes(Block::class);
            if ($attributes === []) {
                if ($class->getAttributes(Supports::class) !== []) {
                    $this->errors[] = sprintf('%s has #[Supports], but no #[Block].', $name);
                }
                if ($class->getAttributes(UseSupport::class) !== []) {
                    $this->errors[] = sprintf('%s has #[UseSupport], but no #[Block].', $name);
                }
                continue;
            }

            $definition = $this->block($class, $attributes[0]);
            if ($definition !== null) {
                $blocks[] = $definition;
                $this->resolver->resolve($name);
            }
        }

        return $blocks;
    }

    /**
     * @return list<string>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * @param ReflectionClass<object>    $class
     * @param ReflectionAttribute<Block> $attribute
     */
    private function block(ReflectionClass $class, ReflectionAttribute $attribute): ?BlockDefinition
    {
        $name = $class->getName();

        try {
            $block = $attribute->newInstance();
            $supports = ($class->getAttributes(Supports::class)[0] ?? null)?->newInstance() ?? new Supports();
        } catch (Throwable $exception) {
            $this->errors[] = sprintf('Invalid #[Block] or #[Supports] on %s: %s', $name, $exception->getMessage());

            return null;
        }

        $file = (string) realpath((string) $class->getFileName());
        if (str_starts_with($file, $this->frameworkDirectory . '/')) {
            $this->errors[] = sprintf('%s has #[Block], but the framework registers no blocks; blocks belong to the theme or plugin.', $name);

            return null;
        }

        $error = $this->inspector->serviceError($name, $name, '#[Block]')
            ?? $this->nameError($block, $name)
            ?? $this->listError($block->keywords, 'keywords', $name)
            ?? $this->listError($block->usesContext, 'usesContext', $name)
            ?? ($block->allowedBlocks === null ? null : $this->listError($block->allowedBlocks, 'allowedBlocks', $name));
        if ($error !== null) {
            $this->errors[] = $error;

            return null;
        }

        $alignments = $this->alignments($supports, $name);
        $render = $this->render($class);
        if ($alignments === null || $render === null) {
            return null;
        }

        [$arguments, $attributeSchemas, $fields, $controls, $postContentField] = $render;
        $blockSupports = $this->supports->blockSupports($class, array_keys($attributeSchemas));
        if ($blockSupports === null) {
            return null;
        }
        $supportEntries = [];
        foreach ($blockSupports as ['definition' => $definition, 'default' => $default]) {
            $schema = $definition->getSchema();
            if ($default !== null) {
                $schema['default'] = ValueSchemaBuilder::export($default);
            }
            $attributeSchemas[$definition->getAttribute()] = $schema;
            $controls[$definition->getAttribute()] = [...$definition->getControl(), 'content' => false, 'group' => $definition->getGroup()->value];
            $supportEntries[] = [
                'attribute' => $definition->getAttribute(),
                'class' => $definition->getClass(),
                'value' => $definition->getValue(),
                'editorScript' => $definition->getEditorScript(),
                'viewScript' => $definition->getViewScript(),
            ];
        }
        $innerBlocks = in_array('content', array_column($arguments, 'kind'), true);
        $excerptFields = self::excerptFields($fields);
        $error = match (true) {
            $block->allowedBlocks !== null && !$innerBlocks => sprintf('#[Block] on %s has allowedBlocks, but %s::render() accepts no string $content, so the block has no inner blocks.', $name, $name),
            $block->excerptWrapper && !$innerBlocks => sprintf('#[Block] on %s has excerptWrapper, but %s::render() accepts no string $content, so the block has no inner blocks to include in excerpts.', $name, $name),
            $postContentField !== null && $innerBlocks => sprintf(
                'Attribute "%s" of block %s has #[RichText(postContent: true)], but %s::render() accepts string $content, so the block has inner blocks; inner blocks and a value stored in the post content cannot share the block content.',
                $postContentField,
                $name,
                $name,
            ),
            $excerptFields !== [] && $innerBlocks => sprintf(
                'Block %s has inner blocks (%s::render() accepts string $content), but marks %s with excerpt: true; WordPress never renders blocks with inner blocks for excerpts, use #[Block(excerptWrapper: true)] to include the inner blocks instead.',
                $name,
                $name,
                implode(', ', array_keys($excerptFields)),
            ),
            default => null,
        };
        if ($error !== null) {
            $this->errors[] = $error;

            return null;
        }
        [$editorScript, $editorError] = ScriptKind::Editor->locate($file, $name, $this->scriptDirectory);
        [$viewScript, $viewError] = ScriptKind::View->locate($file, $name, $this->scriptDirectory);
        if ($editorError !== null || $viewError !== null) {
            array_push($this->errors, ...array_filter([$editorError, $viewError]));

            return null;
        }
        $this->names[$block->name] = $name;

        $args = [
            'api_version' => 3,
            'title' => $block->title,
            'category' => $block->category->value,
        ];
        if ($block->icon !== '') {
            $args['icon'] = $block->icon;
        }
        if ($block->description !== '') {
            $args['description'] = $block->description;
        }
        if ($block->keywords !== []) {
            $args['keywords'] = $block->keywords;
        }
        if ($block->usesContext !== []) {
            $args['uses_context'] = $block->usesContext;
        }
        if ($block->allowedBlocks !== null) {
            $args['allowed_blocks'] = $block->allowedBlocks;
        }
        $args['supports'] = self::supports($supports, $alignments);
        $args['attributes'] = $attributeSchemas;

        return new BlockDefinition($block->name, $args, $name, $arguments, [
            'fields' => $fields,
            'controls' => $controls,
            'innerBlocks' => $innerBlocks,
            'postContentField' => $postContentField,
            'editorScript' => $editorScript,
            'supports' => array_column($supportEntries, 'attribute'),
        ], [
            'fields' => $excerptFields,
            'wrapper' => $block->excerptWrapper,
        ], $supportEntries, $viewScript);
    }

    /**
     * Kinds of the text fields marked with excerpt: true, by path in declaration order.
     *
     * @param array<string, BlockField> $fields
     * @return array<string, 'plain'|'rich'>
     */
    private static function excerptFields(array $fields): array
    {
        $excerpt = [];
        foreach ($fields as $path => $field) {
            if (($field['excerpt'] ?? false) && $field['kind'] !== 'image') {
                $excerpt[$path] = $field['kind'];
            }
        }

        return $excerpt;
    }

    private function nameError(Block $block, string $class): ?string
    {
        if (preg_match(self::NAME_PATTERN, $block->name) !== 1) {
            return sprintf('#[Block] on %s has the invalid name "%s": use "namespace/name" with lowercase letters, digits and "-", e.g. "sections/hero".', $class, $block->name);
        }
        if (str_starts_with($block->name, 'core/')) {
            return sprintf('#[Block] on %s uses the name "%s"; the namespace "core" belongs to WordPress.', $class, $block->name);
        }
        if (isset($this->names[$block->name])) {
            return sprintf('#[Block] on %s uses the name "%s", which %s already declares.', $class, $block->name, $this->names[$block->name]);
        }
        if (trim($block->title) === '') {
            return sprintf('#[Block] on %s has an empty title.', $class);
        }

        return null;
    }

    /**
     * @param array<mixed> $values
     */
    private function listError(array $values, string $argument, string $class): ?string
    {
        if (!array_is_list($values)) {
            return sprintf('#[Block] on %s: %s must be a list of strings.', $class, $argument);
        }
        foreach ($values as $value) {
            if (!is_string($value) || $value === '') {
                return sprintf('#[Block] on %s: %s must contain non-empty strings only.', $class, $argument);
            }
        }

        return null;
    }

    /**
     * @return list<string>|null
     */
    private function alignments(Supports $supports, string $class): ?array
    {
        $values = [];
        foreach ($supports->align as $align) {
            if (!$align instanceof Align) {
                $this->errors[] = sprintf('#[Supports] on %s: align must be a list of Align cases, %s given.', $class, get_debug_type($align));

                return null;
            }
            if (in_array($align->value, $values, true)) {
                $this->errors[] = sprintf('#[Supports] on %s lists the alignment %s twice.', $class, $align->name);

                return null;
            }
            $values[] = $align->value;
        }

        return $values;
    }

    /**
     * Supports for register_block_type(): `html` is always off, all others only if they differ from the WordPress default.
     *
     * @param list<string> $alignments
     * @return array<string, mixed>
     */
    private static function supports(Supports $supports, array $alignments): array
    {
        $defaults = new Supports();
        $result = ['html' => false];

        foreach (['contentRole', 'anchor', 'customClassName', 'multiple', 'reusable', 'inserter', 'lock', 'renaming', 'customCSS'] as $support) {
            if ($supports->{$support} !== $defaults->{$support}) {
                $result[$support] = $supports->{$support};
            }
        }
        if ($alignments !== []) {
            $result['align'] = $alignments;
        }

        return $result;
    }

    /**
     * Checks render() and derives the attribute schemas, inline fields, inspector controls and the attribute stored in the post content.
     *
     * @param ReflectionClass<object> $class
     * @return array{list<BlockArgument>, array<string, array<string, mixed>>, array<string, BlockField>, array<string, Control>, string|null}|null
     */
    private function render(ReflectionClass $class): ?array
    {
        $name = $class->getName();
        if (!$class->hasMethod('render')) {
            $this->errors[] = sprintf('%s has #[Block], but no render() method returning the block HTML.', $name);

            return null;
        }

        $method = $class->getMethod('render');
        $location = sprintf('%s::render()', $name);
        $returnType = $method->getReturnType();
        $error = (!$method->isPublic() || $method->isStatic() ? sprintf('%s of a #[Block] class must be public and non-static.', $location) : null)
            ?? (!$returnType instanceof ReflectionNamedType || $returnType->getName() !== 'string' || $returnType->allowsNull()
                ? sprintf('%s of a #[Block] class must return string.', $location)
                : null);
        if ($error !== null) {
            $this->errors[] = $error;

            return null;
        }

        return $this->renderParameters($method, $location);
    }

    /**
     * @return array{list<BlockArgument>, array<string, array<string, mixed>>, array<string, BlockField>, array<string, Control>, string|null}|null
     */
    private function renderParameters(ReflectionMethod $method, string $location): ?array
    {
        $arguments = [];
        $schemas = [];
        $fields = [];
        $controls = [];
        $postContentField = null;
        $kinds = [];
        $valid = true;

        foreach ($method->getParameters() as $parameter) {
            $parameterLocation = sprintf('parameter $%s of %s', $parameter->getName(), $location);
            $type = $parameter->getType();
            $typeName = $type instanceof ReflectionNamedType && !$type->allowsNull() ? $type->getName() : null;

            $kind = match (true) {
                $typeName === null => null,
                $typeName === 'string' => 'content',
                $typeName === self::BLOCK_CLASS => 'block',
                !$type->isBuiltin() => 'attributes',
                default => null,
            };
            if ($kind === null || $parameter->isVariadic() || $parameter->isPassedByReference()) {
                $this->errors[] = sprintf(
                    '%s is not supported: render() accepts the block attribute class, string $content and WP_Block $block, each at most once and not nullable; inject services through the constructor.',
                    $parameterLocation,
                );
                $valid = false;
                continue;
            }
            if (in_array($kind, $kinds, true)) {
                $this->errors[] = sprintf('%s: render() accepts only one %s parameter.', $parameterLocation, match ($kind) {
                    'content' => 'string $content',
                    'block' => 'WP_Block',
                    default => 'block attribute class',
                });
                $valid = false;
                continue;
            }
            $kinds[] = $kind;

            if ($kind !== 'attributes') {
                $arguments[] = ['param' => $parameter->getName(), 'kind' => $kind];
                continue;
            }

            // A concrete class is also a service candidate; as render() parameter it is the attribute class.
            /** @var class-string $attributesClass */
            $attributesClass = $typeName;
            $attributeSchemas = $this->attributeSchemas($attributesClass, $parameterLocation);
            if ($attributeSchemas === null) {
                $valid = false;
                continue;
            }
            [$schemas, $fields, $controls, $postContentField] = $attributeSchemas;
            $arguments[] = ['param' => $parameter->getName(), 'kind' => 'attributes', 'class' => $attributesClass];
        }

        return $valid ? [$arguments, $schemas, $fields, $controls, $postContentField] : null;
    }

    /**
     * Schemas by attribute name, inline fields by path (e.g. "buttons.*.label"), inspector controls by attribute name
     * and the attribute stored in the post content (#[RichText(postContent: true)]).
     *
     * @param class-string $class
     * @return array{array<string, array<string, mixed>>, array<string, BlockField>, array<string, Control>, string|null}|null
     */
    private function attributeSchemas(string $class, string $location): ?array
    {
        $fields = $this->values->objectFields($class, $location);
        // Checked even if the class is invalid otherwise, so all errors are reported at once.
        $parameters = $this->values->hasClass($class) ? $this->values->getClass($class)->getConstructor()?->getParameters() ?? [] : [];

        $schemas = [];
        $editorFields = [];
        $controls = [];
        $postContentField = null;
        $valid = $fields !== null;
        foreach ($parameters as $parameter) {
            $name = $parameter->getName();
            $attributeLocation = sprintf('parameter $%s of block attribute class %s::__construct()', $name, $class);

            if (in_array($name, self::RESERVED_ATTRIBUTES, true)) {
                $this->errors[] = sprintf('%s uses the attribute name "%s", which WordPress reserves (%s).', $attributeLocation, $name, implode(', ', self::RESERVED_ATTRIBUTES));
                $valid = false;
                continue;
            }
            if (!$parameter->isDefaultValueAvailable()) {
                $this->errors[] = sprintf('%s needs a default value: a block attribute can always be missing in the content.', $attributeLocation);
                $valid = false;
                continue;
            }

            $field = $fields[$name] ?? null;
            if ($field === null) {
                continue;
            }
            $schema = $field['schema'];
            $default = $parameter->getDefaultValue();
            if ($default !== null) {
                $schema['default'] = ValueSchemaBuilder::export($default);
            }
            if ($field['content']) {
                $schema['role'] = 'content';
            }
            $schemas[$name] = $schema;

            foreach ($field['fields'] as $editorField) {
                // ValueSchemaBuilder rejects postContent in value classes and list items, so this is the attribute itself.
                if ($editorField['postContent'] ?? false) {
                    $error = match (true) {
                        $postContentField !== null => sprintf(
                            '%s has #[RichText(postContent: true)], but the attribute "%s" already does; a block stores at most one value in the post content.',
                            $attributeLocation,
                            $postContentField,
                        ),
                        $default !== '' => sprintf(
                            "%s has #[RichText(postContent: true)] and must default to '': the editor stores an empty value as empty block content, which WordPress parses as ''.",
                            $attributeLocation,
                        ),
                        default => null,
                    };
                    if ($error !== null) {
                        $this->errors[] = $error;
                        $valid = false;
                        continue;
                    }
                    $postContentField = $name;
                }
                $path = $editorField['path'] === '' ? $name : $name . '.' . $editorField['path'];
                unset($editorField['path']);
                $editorFields[$path] = $editorField;
            }
            if ($field['control'] !== null) {
                // The inspector shows content controls in the "content" group, which stays visible in contentOnly mode.
                $controls[$name] = [...$field['control'], 'content' => $field['content']];
            }
        }

        return $valid ? [$schemas, $editorFields, $controls, $postContentField] : null;
    }
}
