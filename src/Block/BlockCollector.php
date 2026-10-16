<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Block;

use BackedEnum;
use LogicException;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use Saij\WpAppFramework\Asset\ScriptKind;
use Saij\WpAppFramework\Block\Markup\BlockMarkupValidator;
use Saij\WpAppFramework\Block\Support\SupportCollector;
use Saij\WpAppFramework\Block\Support\UseSupport;
use Saij\WpAppFramework\Cache\CacheCollector;
use Saij\WpAppFramework\DependencyInjection\NoService;
use Saij\WpAppFramework\DependencyInjection\ServiceResolver;
use Saij\WpAppFramework\Manifest\EntryPointInspector;
use Saij\WpAppFramework\Post\AbstractPost;
use Saij\WpAppFramework\Table\AbstractRow;
use Saij\WpAppFramework\Table\TableNames;
use Saij\WpAppFramework\Value\ValueSchemaBuilder;
use Throwable;

/**
 * Collects and validates `#[Block]` classes during the build, derives the
 * attribute schemas from their attribute classes and resolves the services.
 *
 * Blocks refer to each other (`#[InnerBlocks]`, `parent`, `ancestor`,
 * `#[Context]`, templates, examples and variations); these references are
 * resolved in a second pass once all blocks are known.
 *
 * @phpstan-import-type BlockArgument from BlockDefinition
 * @phpstan-import-type BlockEditor from BlockDefinition
 * @phpstan-import-type BlockField from BlockDefinition
 * @phpstan-import-type BlockSupportEntry from BlockDefinition
 * @phpstan-import-type Control from ValueSchemaBuilder
 * @phpstan-import-type EditorField from ValueSchemaBuilder
 * @phpstan-import-type Field from ValueSchemaBuilder
 * @phpstan-import-type Value from \Saij\WpAppFramework\Value\ValueFactory
 * @phpstan-type ContextArgument array{param: string, kind: 'context', name: string, schema: array<string, mixed>, value: Value, hasDefault: bool}
 * @phpstan-type Pending array{
 *     definition: BlockDefinition,
 *     block: Block,
 *     inner: InnerBlocks|null,
 *     example: Example|null,
 *     variations: list<Variation>,
 *     contexts: list<array{ReflectionParameter, Context}>,
 *     fields: array<string, Field>,
 *     types: array<string, string>,
 *     provided: list<string>
 * }
 * @phpstan-type TemplateNode array{name: string, attributes: array<string, mixed>, innerBlocks: list<mixed>}
 * @phpstan-type Category array{slug: string, title: string, icon: string|null}
 * @phpstan-type Render array{
 *     arguments: list<BlockArgument>,
 *     schemas: array<string, array<string, mixed>>,
 *     fields: array<string, BlockField>,
 *     controls: array<string, Control>,
 *     postContentField: string|null,
 *     appenders: list<string>,
 *     contexts: list<array{ReflectionParameter, Context}>,
 *     attributeFields: array<string, Field>,
 *     types: array<string, string>,
 *     provided: list<string>
 * }
 */
#[NoService]
final class BlockCollector
{
    /** The part after the namespace; same rule as WP_Block_Type_Registry::register(). */
    private const string NAME_PATTERN = '~^[a-z0-9-]+$~';

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

    /** @var array<string, class-string> Full block name => class, to find duplicates. */
    private array $names = [];

    /** @var array<class-string, Pending> Valid blocks of the first pass by class. */
    private array $pending = [];

    /** @var array<class-string, true> Classes with #[Block] that are invalid; references to them report no further error. */
    private array $failed = [];

    /** @var array<class-string, list<string>|null|false> Resolved #[InnerBlocks(allowed)] by block class; false if invalid. */
    private array $allowed = [];

    /** @var array<class-string<BackedEnum&CustomBlockCategory>, true> Enums of custom block categories. */
    private array $categoryEnums = [];

    /** @var list<Category> */
    private array $categories = [];

    private readonly EntryPointInspector $inspector;

    /**
     * @param non-empty-string $namespace     Namespace of the project; blocks are registered as `{namespace}/{name}`.
     * @param string      $frameworkDirectory Source directory of the framework, which must not declare blocks.
     * @param string|null $scriptDirectory    Directory the consumer's build compiles scripts from (its `src/`);
     *                                        editor and view scripts must be below it. Null if the consumer has none.
     * @param array<class-string<AbstractRow>, non-empty-string> $tables Row classes of the project and the names of
     *                                        their tables, for `#[Item]` parameters of a row class.
     */
    public function __construct(
        private readonly ServiceResolver $resolver,
        private readonly ValueSchemaBuilder $values,
        private readonly SupportCollector $supports,
        private readonly string $namespace,
        private readonly string $frameworkDirectory,
        private readonly ?string $scriptDirectory = null,
        private readonly ?CacheCollector $cache = null,
        private readonly array $tables = [],
    ) {
        $this->inspector = new EntryPointInspector($resolver);
    }

    /**
     * @param array<class-string, ReflectionClass<object>> $classes All scanned classes.
     * @return list<BlockDefinition>
     */
    public function collect(array $classes): array
    {
        foreach ($classes as $name => $class) {
            $attributes = $class->getAttributes(Block::class);
            if ($attributes === []) {
                foreach ([Supports::class, UseSupport::class, InnerBlocks::class, Example::class, Variation::class] as $attribute) {
                    if ($class->getAttributes($attribute) !== []) {
                        $this->errors[] = sprintf('%s has #[%s], but no #[Block].', $name, self::shortName($attribute));
                    }
                }
                continue;
            }

            $pending = $this->block($class, $attributes[0]);
            if ($pending === null) {
                $this->failed[$name] = true;
                continue;
            }
            $this->pending[$name] = $pending;
            $this->resolver->resolve($name);
        }

        $this->markers($classes);
        $this->categories = $this->collectCategories();

        $blocks = [];
        foreach ($this->pending as $pending) {
            $definition = $this->resolve($pending);
            if ($definition !== null) {
                $blocks[] = $definition;
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
     * The custom block categories (`CustomBlockCategory`) by enum class name, each in the order of its cases.
     *
     * @return list<Category>
     */
    public function categories(): array
    {
        return $this->categories;
    }

    /**
     * First pass: everything that does not depend on other blocks.
     *
     * @param ReflectionClass<object>    $class
     * @param ReflectionAttribute<Block> $attribute
     * @return Pending|null
     */
    private function block(ReflectionClass $class, ReflectionAttribute $attribute): ?array
    {
        $name = $class->getName();

        try {
            $block = $attribute->newInstance();
            $supports = ($class->getAttributes(Supports::class)[0] ?? null)?->newInstance() ?? new Supports();
            $inner = ($class->getAttributes(InnerBlocks::class)[0] ?? null)?->newInstance();
            $example = ($class->getAttributes(Example::class)[0] ?? null)?->newInstance();
            $variations = array_map(static fn(ReflectionAttribute $variation): Variation => $variation->newInstance(), $class->getAttributes(Variation::class));
        } catch (Throwable $exception) {
            $this->errors[] = sprintf('Invalid #[Block], #[Supports], #[InnerBlocks], #[Example] or #[Variation] on %s: %s', $name, $exception->getMessage());

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
            ?? $this->usesContextError($block, $name)
            ?? $this->categoryError($block->category, $name);
        if ($error !== null) {
            $this->errors[] = $error;

            return null;
        }

        $alignments = $this->alignments($supports, $name);
        $render = $this->render($class);
        $cache = $this->cache?->entry($class, $name);
        if ($alignments === null || $render === null || $cache === false) {
            return null;
        }

        $arguments = $render['arguments'];
        $attributeSchemas = $render['schemas'];
        $controls = $render['controls'];
        $fields = $render['fields'];
        $postContentField = $render['postContentField'];
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
        $kinds = array_column($arguments, 'kind');
        $repeater = in_array('repeater', $kinds, true);
        $innerBlocks = $repeater || in_array('content', $kinds, true);
        $excerptFields = self::excerptFields($fields);
        $error = match (true) {
            $repeater && in_array('content', $kinds, true) => sprintf('%s::render() accepts string $content and Repeater; a block either renders its inner blocks once (string $content) or once per item (Repeater).', $name),
            $repeater && $block->excerptWrapper => sprintf('#[Block] on %s has excerptWrapper, but %s::render() accepts Repeater; WordPress includes the inner blocks of an excerpt wrapper once, not per item.', $name, $name),
            $repeater && $postContentField !== null => sprintf(
                'Attribute "%s" of block %s has #[RichText(postContent: true)], but %s::render() accepts Repeater, so the block has inner blocks; inner blocks and a value stored in the post content cannot share the block content.',
                $postContentField,
                $name,
                $name,
            ),
            $inner !== null && !$innerBlocks => sprintf('%s has #[InnerBlocks], but %s::render() accepts no string $content, so the block has no inner blocks.', $name, $name),
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
        $blockName = $this->namespace . '/' . $block->name;
        [$editorScript, $editorError] = ScriptKind::Editor->locate($file, $name, $this->scriptDirectory);
        [$viewScript, $viewError] = ScriptKind::View->locate($file, $name, $this->scriptDirectory);
        if ($editorError !== null || $viewError !== null) {
            array_push($this->errors, ...array_filter([$editorError, $viewError]));

            return null;
        }
        $this->names[$blockName] = $name;

        $category = $block->category;
        if ($category instanceof CustomBlockCategory && $category instanceof BackedEnum) {
            $this->categoryEnums[$category::class] = true;
        }
        $args = [
            'api_version' => 3,
            'title' => $block->title,
            'category' => $this->categorySlug($category),
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
        $usesContext = $block->usesContext;
        foreach ($arguments as $argument) {
            if ($argument['kind'] === 'item') {
                array_push($usesContext, ...array_diff($argument['context'], $usesContext));
            }
        }
        if ($usesContext !== []) {
            $args['uses_context'] = $usesContext;
        }
        if ($repeater) {
            // Like core/post-template: WordPress does not render the inner blocks once; the repeater renders them per item.
            $args['skip_inner_blocks'] = true;
        }
        if ($render['provided'] !== []) {
            $args['provides_context'] = [];
            foreach ($render['provided'] as $attributeName) {
                $args['provides_context'][$blockName . '/' . $attributeName] = $attributeName;
            }
        }
        $args['supports'] = self::supports($supports, $alignments);
        $args['attributes'] = $attributeSchemas;

        $definition = new BlockDefinition($blockName, $args, $name, $arguments, [
            'fields' => $fields,
            'controls' => $controls,
            'innerBlocks' => $innerBlocks,
            'repeater' => $repeater,
            'innerBlockOptions' => [],
            'postContentField' => $postContentField,
            'appenders' => $render['appenders'],
            'editorScript' => $editorScript,
            'supports' => array_column($supportEntries, 'attribute'),
        ], [
            'fields' => $excerptFields,
            'wrapper' => $block->excerptWrapper,
        ], $supportEntries, $viewScript, $cache);

        return [
            'definition' => $definition,
            'block' => $block,
            'inner' => $inner,
            'example' => $example,
            'variations' => $variations,
            'contexts' => $render['contexts'],
            'fields' => $render['attributeFields'],
            'types' => $render['types'],
            'provided' => $render['provided'],
        ];
    }

    /**
     * Second pass: references to other blocks, inner block options, context, example and variations.
     *
     * @param Pending $pending
     */
    private function resolve(array $pending): ?BlockDefinition
    {
        $definition = $pending['definition'];
        $class = $definition->getClass();
        $block = $pending['block'];
        $args = $definition->getArgs();
        $arguments = $definition->getArguments();
        $editor = $definition->getEditor();
        $valid = true;

        foreach (['parent' => $block->parent, 'ancestor' => $block->ancestor] as $key => $list) {
            if ($list === null) {
                continue;
            }
            $names = $this->references($list, sprintf('#[Block] on %s: %s', $class, $key));
            if ($names === null) {
                $valid = false;
                continue;
            }
            $args[$key] = $names;
            if ($key === 'parent') {
                $valid = $this->parentError($definition, $names) && $valid;
            }
        }

        $allowed = $this->allowedOf($class);
        if ($allowed === false) {
            $valid = false;
            $allowed = null;
        } elseif ($allowed !== null) {
            $args['allowed_blocks'] = $allowed;
        }

        $template = null;
        $inner = $pending['inner'];
        if ($inner !== null) {
            $options = $this->innerBlockOptions($inner, $class, $allowed);
            if ($options === null) {
                $valid = false;
            } else {
                $editor['innerBlockOptions'] = $options;
                /** @var list<TemplateNode>|null $template Set by innerBlockOptions(). */
                $template = $options['template'] ?? null;
            }
        }

        $example = $this->example($pending, $allowed, $template);
        if ($example === false) {
            $valid = false;
        } elseif ($example !== null) {
            $args['example'] = $example;
        }

        $variations = $this->variations($pending, $allowed);
        if ($variations === null) {
            $valid = false;
        } elseif ($variations !== []) {
            $args['variations'] = $variations;
        }

        $context = $this->contextArguments($pending);
        if ($context === null) {
            $valid = false;
        } elseif ($context !== []) {
            array_push($arguments, ...$context);
            $usesContext = is_array($args['uses_context'] ?? null) ? $args['uses_context'] : [];
            foreach ($context as $argument) {
                if (!in_array($argument['name'], $usesContext, true)) {
                    $usesContext[] = $argument['name'];
                }
            }
            $args['uses_context'] = $usesContext;
        }

        return $valid ? $definition->withResolved($args, $arguments, $editor) : null;
    }

    /**
     * The resolved #[InnerBlocks(allowed)] of a block; null if all blocks are allowed, false if it is invalid.
     * Resolved once, so its errors are reported once.
     *
     * @param class-string $class
     * @return list<string>|null|false
     */
    private function allowedOf(string $class): array|false|null
    {
        if (!array_key_exists($class, $this->allowed)) {
            $list = $this->pending[$class]['inner']->allowed ?? null;
            $this->allowed[$class] = $list === null ? null : ($this->references($list, sprintf('#[InnerBlocks] on %s: allowed', $class)) ?? false);
        }

        return $this->allowed[$class];
    }

    /**
     * @param list<string>|null $allowed
     * @return array<string, mixed>|null Options for useInnerBlocksProps(); null if invalid.
     */
    private function innerBlockOptions(InnerBlocks $inner, string $class, ?array $allowed): ?array
    {
        $location = sprintf('#[InnerBlocks] on %s', $class);
        $options = [];
        $valid = true;

        if ($inner->template !== null) {
            $template = $this->templates($inner->template, $location . ': template', $allowed);
            if ($template === null) {
                $valid = false;
            } else {
                $options['template'] = $template;
            }
        }
        if ($inner->templateLock !== null) {
            $options['templateLock'] = $inner->templateLock === TemplateLock::Unlocked ? false : $inner->templateLock->value;
        }
        if ($inner->orientation !== null) {
            $options['orientation'] = $inner->orientation->value;
        }
        if ($inner->defaultBlock !== null) {
            $default = $this->templates([$inner->defaultBlock], $location . ': defaultBlock', $allowed);
            if ($default === null) {
                $valid = false;
            } elseif ($default[0]['innerBlocks'] !== []) {
                $this->errors[] = sprintf('%s: defaultBlock has inner blocks; the editor inserts the default block with its attributes only.', $location);
                $valid = false;
            } else {
                $options['defaultBlock'] = $default[0]['attributes'] === [] ? ['name' => $default[0]['name']] : ['name' => $default[0]['name'], 'attributes' => $default[0]['attributes']];
            }
        }
        if ($inner->directInsert) {
            if ($inner->defaultBlock === null) {
                $this->errors[] = sprintf('%s: directInsert needs a defaultBlock, the block the appender inserts.', $location);
                $valid = false;
            }
            $options['directInsert'] = true;
        }
        if ($inner->prioritized !== []) {
            $prioritized = $this->references($inner->prioritized, $location . ': prioritized');
            if ($prioritized === null) {
                $valid = false;
            } else {
                foreach ($prioritized as $name) {
                    if ($allowed !== null && !in_array($name, $allowed, true)) {
                        $this->errors[] = sprintf('%s: prioritized contains %s, which is not in allowed.', $location, $name);
                        $valid = false;
                    }
                }
                $options['prioritizedInserterBlocks'] = $prioritized;
            }
        }
        if ($inner->appender !== Appender::Default) {
            $options['appender'] = $inner->appender->value;
        }

        return $valid ? $options : null;
    }

    /**
     * The example for the inserter preview: #[Example], otherwise the default attributes with the template.
     *
     * @param Pending                $pending
     * @param list<string>|null      $allowed
     * @param list<TemplateNode>|null $template
     * @return array<string, mixed>|false|null Null for no preview, false if invalid.
     */
    private function example(array $pending, ?array $allowed, ?array $template): array|false|null
    {
        $definition = $pending['definition'];
        $class = $definition->getClass();
        $example = $pending['example'];
        if ($example === null) {
            // Without attributes the editor creates the block with its defaults (getBlockFromExample()).
            return ['innerBlocks' => $template ?? []];
        }

        $location = sprintf('#[Example] on %s', $class);
        if ($example->attributes === false) {
            if ($example->innerBlocks !== [] || $example->viewportWidth !== null) {
                $this->errors[] = sprintf('%s: #[Example(false)] shows no preview and takes no innerBlocks or viewportWidth.', $location);

                return false;
            }

            return null;
        }

        $valid = true;
        $result = [];
        $attributes = $this->attributeValues($definition, $example->attributes, $location);
        if ($attributes === null) {
            $valid = false;
        } elseif ($attributes !== []) {
            $result['attributes'] = $attributes;
        }
        if ($example->innerBlocks !== [] && !$definition->getEditor()['innerBlocks']) {
            $this->errors[] = sprintf('%s has innerBlocks, but %s::render() accepts no string $content, so the block has no inner blocks.', $location, $class);
            $valid = false;
        } else {
            $innerBlocks = $this->templates($example->innerBlocks, $location . ': innerBlocks', $allowed);
            if ($innerBlocks === null) {
                $valid = false;
            } else {
                $result['innerBlocks'] = $innerBlocks;
            }
        }
        if ($example->viewportWidth !== null) {
            if ($example->viewportWidth < 1) {
                $this->errors[] = sprintf('%s: viewportWidth must be at least 1.', $location);
                $valid = false;
            }
            $result['viewportWidth'] = $example->viewportWidth;
        }

        return $valid ? $result : false;
    }

    /**
     * @param Pending           $pending
     * @param list<string>|null $allowed
     * @return list<array<string, mixed>>|null Null if invalid.
     */
    private function variations(array $pending, ?array $allowed): ?array
    {
        $definition = $pending['definition'];
        $class = $definition->getClass();
        $variations = [];
        $names = [];
        $valid = true;

        foreach ($pending['variations'] as $variation) {
            $location = sprintf('#[Variation("%s")] on %s', $variation->name, $class);
            $error = match (true) {
                preg_match(self::NAME_PATTERN, $variation->name) !== 1 => sprintf('%s: the name must consist of lowercase letters, digits and "-".', $location),
                in_array($variation->name, $names, true) => sprintf('%s: the name is used by another variation of the block.', $location),
                trim($variation->title) === '' => sprintf('%s has an empty title.', $location),
                default => $this->listError($variation->keywords, 'keywords', $class, $location)
                    ?? $this->listError($variation->isActive, 'isActive', $class, $location)
                    ?? $this->scopeError($variation, $location),
            };
            if ($error !== null) {
                $this->errors[] = $error;
                $valid = false;
                continue;
            }
            $names[] = $variation->name;

            $entry = ['name' => $variation->name, 'title' => $variation->title];
            foreach (['description', 'icon'] as $key) {
                if ($variation->{$key} !== '') {
                    $entry[$key] = $variation->{$key};
                }
            }
            if ($variation->keywords !== []) {
                $entry['keywords'] = $variation->keywords;
            }
            $attributes = $this->attributeValues($definition, $variation->attributes, $location);
            if ($attributes === null) {
                $valid = false;
                continue;
            }
            if ($attributes !== []) {
                $entry['attributes'] = $attributes;
            }
            if ($variation->innerBlocks !== []) {
                if (!$definition->getEditor()['innerBlocks']) {
                    $this->errors[] = sprintf('%s has innerBlocks, but %s::render() accepts no string $content, so the block has no inner blocks.', $location, $class);
                    $valid = false;
                    continue;
                }
                $innerBlocks = $this->templates($variation->innerBlocks, $location . ': innerBlocks', $allowed);
                if ($innerBlocks === null) {
                    $valid = false;
                    continue;
                }
                $entry['innerBlocks'] = $innerBlocks;
            }
            $entry['scope'] = array_map(static fn(VariationScope $scope): string => $scope->value, $variation->scope);
            if ($variation->isDefault) {
                $entry['isDefault'] = true;
            }
            if ($variation->isActive !== []) {
                foreach ($variation->isActive as $attribute) {
                    if (!array_key_exists($attribute, $attributes)) {
                        $this->errors[] = sprintf('%s: isActive names "%s", which the variation does not preset in attributes; the editor compares the attributes of a block with the preset values.', $location, $attribute);
                        $valid = false;
                    }
                }
                $entry['isActive'] = $variation->isActive;
            }
            $variations[] = $entry;
        }

        return $valid ? $variations : null;
    }

    private function scopeError(Variation $variation, string $location): ?string
    {
        if ($variation->scope === [] || !array_is_list($variation->scope)) {
            return sprintf('%s: scope must be a non-empty list of VariationScope cases.', $location);
        }
        foreach ($variation->scope as $scope) {
            if (!$scope instanceof VariationScope) {
                return sprintf('%s: scope must contain VariationScope cases only, %s given.', $location, get_debug_type($scope));
            }
        }

        return null;
    }

    /**
     * The render() arguments of #[Context] parameters.
     *
     * @param Pending $pending
     * @return list<ContextArgument>|null Null if invalid.
     */
    private function contextArguments(array $pending): ?array
    {
        $class = $pending['definition']->getClass();
        $arguments = [];
        $valid = true;

        foreach ($pending['contexts'] as [$parameter, $context]) {
            $location = sprintf('parameter $%s of %s::render()', $parameter->getName(), $class);
            $provider = $this->pending[$context->block] ?? null;
            if ($provider === null) {
                if (!isset($this->failed[$context->block])) {
                    $this->errors[] = sprintf('%s has #[Context(%s)], but %s is no block class of this theme or plugin (a class with #[Block]).', $location, $context->block, $context->block);
                }
                $valid = false;
                continue;
            }

            $providerClass = $provider['definition']->getClass();
            $attribute = $context->attribute;
            $field = $provider['fields'][$attribute] ?? null;
            $error = match (true) {
                $field === null => sprintf('%s has #[Context(%s, "%s")], but block %s has no attribute "%s".', $location, $providerClass, $attribute, $providerClass, $attribute),
                !in_array($attribute, $provider['provided'], true) => sprintf(
                    '%s has #[Context(%s, "%s")], but attribute "%s" of %s is not marked with #[ProvidesContext] (marked: %s).',
                    $location,
                    $providerClass,
                    $attribute,
                    $attribute,
                    $providerClass,
                    $provider['provided'] === [] ? 'none' : implode(', ', $provider['provided']),
                ),
                !$parameter->isDefaultValueAvailable() && !$parameter->allowsNull() => sprintf(
                    '%s needs a default value or a nullable type: the value is missing if no %s block surrounds the block.',
                    $location,
                    $providerClass,
                ),
                default => null,
            };
            if ($error !== null || $field === null) {
                $this->errors[] = (string) $error;
                $valid = false;
                continue;
            }

            $parameterField = $this->values->parameter($parameter, $location);
            if ($parameterField === null) {
                $valid = false;
                continue;
            }
            if (!self::sameValue($parameterField['value'], $field['value']) || ($field['value']['nullable'] && !$parameterField['value']['nullable'])) {
                $this->errors[] = sprintf(
                    '%s has the type %s, but attribute "%s" of %s has the type %s.',
                    $location,
                    $parameter->getType(),
                    $attribute,
                    $providerClass,
                    $provider['types'][$attribute] ?? 'another type',
                );
                $valid = false;
                continue;
            }

            $providerName = $provider['definition']->getName();
            $schema = $provider['definition']->getArgs()['attributes'][$attribute];
            unset($schema['default'], $schema['role']);
            $arguments[] = [
                'param' => $parameter->getName(),
                'kind' => 'context',
                'name' => $providerName . '/' . $attribute,
                'schema' => $schema,
                'value' => $parameterField['value'],
                'hasDefault' => $parameter->isDefaultValueAvailable(),
            ];
        }

        return $valid ? $arguments : null;
    }

    /**
     * Whether two value descriptions describe the same type apart from nullability.
     *
     * @param array<string, mixed> $parameter
     * @param array<string, mixed> $attribute
     */
    private static function sameValue(array $parameter, array $attribute): bool
    {
        return array_replace($parameter, ['nullable' => false]) == array_replace($attribute, ['nullable' => false]);
    }

    /**
     * A block with `parent` must be allowed in each of its parents of the same theme or plugin.
     *
     * @param list<string> $parents
     */
    private function parentError(BlockDefinition $definition, array $parents): bool
    {
        $valid = true;
        foreach ($parents as $parent) {
            $parentClass = $this->names[$parent] ?? null;
            if ($parentClass === null || !isset($this->pending[$parentClass])) {
                continue;
            }
            $allowed = $this->allowedOf($parentClass);
            if (is_array($allowed) && !in_array($definition->getName(), $allowed, true)) {
                $this->errors[] = sprintf(
                    '#[Block] on %s: parent names %s, whose #[InnerBlocks(allowed)] does not contain %s; the block could never be inserted.',
                    $definition->getClass(),
                    $parentClass,
                    $definition->getClass(),
                );
                $valid = false;
            }
        }

        return $valid;
    }

    /**
     * Resolves block references: block classes of the project, CoreBlock cases and full names of other blocks.
     *
     * @param array<mixed> $list
     * @return list<string>|null Full block names; null if one is invalid.
     */
    private function references(array $list, string $location): ?array
    {
        if (!array_is_list($list)) {
            $this->errors[] = sprintf('%s must be a list.', $location);

            return null;
        }
        $names = [];
        $valid = true;
        foreach ($list as $reference) {
            $name = $this->reference($reference, $location);
            if ($name === null) {
                $valid = false;
                continue;
            }
            if (in_array($name, $names, true)) {
                $this->errors[] = sprintf('%s names %s twice.', $location, $name);
                $valid = false;
                continue;
            }
            $names[] = $name;
        }

        return $valid ? $names : null;
    }

    /**
     * @return string|null The full block name; null if the reference is invalid (the error is recorded, unless it
     *                     names an invalid block, which reports its own errors).
     */
    private function reference(mixed $reference, string $location): ?string
    {
        if ($reference instanceof CoreBlock) {
            return $reference->value;
        }
        if (!is_string($reference) || $reference === '') {
            $this->errors[] = sprintf('%s must contain block classes, CoreBlock cases and full block names, %s given.', $location, get_debug_type($reference));

            return null;
        }
        if (str_contains($reference, '/')) {
            $core = CoreBlock::tryFrom($reference);
            $error = match (true) {
                $core !== null => sprintf('%s names "%s"; use CoreBlock::%s.', $location, $reference, $core->name),
                str_starts_with($reference, $this->namespace . '/') => sprintf(
                    '%s names "%s" in the namespace of this theme or plugin; name its blocks by class%s.',
                    $location,
                    $reference,
                    isset($this->names[$reference]) ? sprintf(' (%s::class)', $this->names[$reference]) : '',
                ),
                default => null,
            };
            if ($error !== null) {
                $this->errors[] = $error;

                return null;
            }

            return $reference;
        }

        if (isset($this->pending[$reference])) {
            return $this->pending[$reference]['definition']->getName();
        }
        if (!isset($this->failed[$reference])) {
            $this->errors[] = sprintf(
                '%s names %s, which is no block class of this theme or plugin (a class with #[Block]); name core blocks by CoreBlock and other blocks in full, e.g. "acme/slider".',
                $location,
                $reference,
            );
        }

        return null;
    }

    /**
     * Resolves and checks template blocks: references, attributes of blocks of the project, inner blocks.
     *
     * @param array<mixed>      $templates
     * @param list<string>|null $allowed Blocks the surrounding container allows; null for all.
     * @return list<TemplateNode>|null Null if invalid.
     */
    private function templates(array $templates, string $location, ?array $allowed): ?array
    {
        if (!array_is_list($templates)) {
            $this->errors[] = sprintf('%s must be a list of TemplateBlock objects.', $location);

            return null;
        }
        $nodes = [];
        $valid = true;
        foreach ($templates as $index => $template) {
            $itemLocation = sprintf('%s[%d]', $location, $index);
            if (!$template instanceof TemplateBlock) {
                $this->errors[] = sprintf('%s must be a TemplateBlock, %s given.', $itemLocation, get_debug_type($template));
                $valid = false;
                continue;
            }
            $name = $this->reference($template->getBlock(), $itemLocation);
            if ($name === null) {
                $valid = false;
                continue;
            }
            if ($allowed !== null && !in_array($name, $allowed, true)) {
                $this->errors[] = sprintf('%s: %s is not allowed in the container (allowed: %s).', $itemLocation, $name, implode(', ', $allowed));
                $valid = false;
                continue;
            }

            $ownClass = $this->names[$name] ?? null;
            $own = $ownClass !== null ? ($this->pending[$ownClass] ?? null) : null;
            if ($own !== null) {
                $attributes = $this->attributeValues($own['definition'], $template->getAttributes(), sprintf('%s (%s)', $itemLocation, $name));
                $innerAllowed = $this->allowedOf($own['definition']->getClass());
                if ($template->getInnerBlocks() !== [] && !$own['definition']->getEditor()['innerBlocks']) {
                    $this->errors[] = sprintf('%s has inner blocks, but block %s has none.', $itemLocation, $name);
                    $valid = false;
                    continue;
                }
            } else {
                $attributes = self::exportAttributes($template->getAttributes());
                if ($attributes === null) {
                    $this->errors[] = sprintf('%s: attributes must be an array with attribute names as keys.', $itemLocation);
                }
                $innerAllowed = null;
            }
            if ($attributes === null || $innerAllowed === false) {
                $valid = false;
                continue;
            }
            $innerBlocks = $this->templates($template->getInnerBlocks(), $itemLocation . ': innerBlocks', $innerAllowed);
            if ($innerBlocks === null) {
                $valid = false;
                continue;
            }
            $nodes[] = ['name' => $name, 'attributes' => $attributes, 'innerBlocks' => $innerBlocks];
        }

        return $valid ? $nodes : null;
    }

    /**
     * Exports attribute values (enums become their value) and checks them against the block's attributes.
     *
     * @param array<mixed> $attributes
     * @return array<string, mixed>|null Null if invalid.
     */
    private function attributeValues(BlockDefinition $definition, array $attributes, string $location): ?array
    {
        $values = self::exportAttributes($attributes);
        if ($values === null) {
            $this->errors[] = sprintf('%s: attributes must be an array with attribute names as keys.', $location);

            return null;
        }
        $valid = true;
        foreach ($values as $name => $value) {
            foreach (BlockMarkupValidator::attributeErrors($definition, $name, $value, true) as $error) {
                $this->errors[] = sprintf('%s: %s', $location, $error);
                $valid = false;
            }
        }

        return $valid ? $values : null;
    }

    /**
     * @param array<mixed> $attributes
     * @return array<string, mixed>|null
     */
    private static function exportAttributes(array $attributes): ?array
    {
        $values = [];
        foreach ($attributes as $name => $value) {
            if (!is_string($name)) {
                return null;
            }
            $values[$name] = ValueSchemaBuilder::export($value);
        }

        return $values;
    }

    /**
     * #[ProvidesContext] marks attributes of block attribute classes only, #[Context] parameters of render() only.
     *
     * @param array<class-string, ReflectionClass<object>> $classes
     */
    private function markers(array $classes): void
    {
        $attributeClasses = [];
        foreach ($this->pending as $pending) {
            foreach ($pending['definition']->getArguments() as $argument) {
                if ($argument['kind'] === 'attributes') {
                    $attributeClasses[$argument['class']] = true;
                }
            }
        }

        foreach ($classes as $name => $class) {
            foreach ($class->getConstructor()?->getParameters() ?? [] as $parameter) {
                if ($parameter->getAttributes(ProvidesContext::class) !== [] && !isset($attributeClasses[$name]) && !isset($this->failed[$name])) {
                    $this->errors[] = sprintf(
                        'Parameter $%s of %s::__construct() has #[ProvidesContext], which only marks attributes of block attribute classes (the class of a render() parameter of a #[Block] class).',
                        $parameter->getName(),
                        $name,
                    );
                }
            }
            $isBlock = $class->getAttributes(Block::class) !== [];
            foreach ($class->getMethods() as $method) {
                if ($method->getDeclaringClass()->getName() !== $name || ($isBlock && $method->getName() === 'render')) {
                    continue;
                }
                foreach ($method->getParameters() as $parameter) {
                    if ($parameter->getAttributes(Context::class) !== []) {
                        $this->errors[] = sprintf(
                            'Parameter $%s of %s::%s() has #[Context], which only marks parameters of render() of a #[Block] class.',
                            $parameter->getName(),
                            $name,
                            $method->getName(),
                        );
                    }
                }
            }
        }
    }

    /**
     * @return list<Category>
     */
    private function collectCategories(): array
    {
        $enums = array_keys($this->categoryEnums);
        sort($enums);
        $categories = [];
        foreach ($enums as $enum) {
            foreach ($enum::cases() as $case) {
                $location = sprintf('Block category %s::%s', $enum, $case->name);
                try {
                    $label = $case->label();
                    $icon = $case->icon();
                } catch (Throwable $exception) {
                    $this->errors[] = sprintf('%s: label() or icon() failed: %s', $location, $exception->getMessage());
                    continue;
                }
                $error = match (true) {
                    // Checked for the cases blocks use in categoryError(); the others are registered as well.
                    !is_string($case->value) || preg_match(self::NAME_PATTERN, $case->value) !== 1 => sprintf('%s: the value must consist of lowercase letters, digits and "-".', $location),
                    trim($label) === '' => sprintf('%s: label() returned an empty label.', $location),
                    $icon !== null && trim($icon) === '' => sprintf('%s: icon() returned an empty string; return null for no icon.', $location),
                    default => null,
                };
                if ($error !== null) {
                    $this->errors[] = $error;
                    continue;
                }
                $categories[] = ['slug' => $this->categorySlug($case), 'title' => $label, 'icon' => $icon];
            }
        }

        return $categories;
    }

    private function categorySlug(BlockCategory|CustomBlockCategory $category): string
    {
        if ($category instanceof BlockCategory) {
            return $category->value;
        }
        if (!$category instanceof BackedEnum) {
            throw new LogicException(sprintf('Block category %s is no backed enum; categoryError() reports this.', $category::class));
        }

        return $this->namespace . '-' . $category->value;
    }

    private function categoryError(BlockCategory|CustomBlockCategory $category, string $class): ?string
    {
        if ($category instanceof BlockCategory) {
            return null;
        }
        if (!$category instanceof BackedEnum || !is_string($category->value)) {
            return sprintf('#[Block] on %s: the category must be a case of a string backed enum that implements CustomBlockCategory, %s given.', $class, get_debug_type($category));
        }

        return null;
    }

    /**
     * A render() parameter marked with #[Item]: where its value comes from and which context it reads.
     *
     * @return array{param: string, kind: 'item', source: 'post'|'postClass'|'term'|'comment'|'user'|'row', class: class-string|null, context: list<string>}|null
     */
    private function itemArgument(ReflectionParameter $parameter, string $location): ?array
    {
        $type = $parameter->getType();
        $name = $type instanceof ReflectionNamedType ? $type->getName() : null;
        $source = match (true) {
            $name === null => null,
            $name === 'WP_Post' => 'post',
            $name === 'WP_Term' => 'term',
            $name === 'WP_Comment' => 'comment',
            $name === 'WP_User' => 'user',
            is_subclass_of($name, AbstractPost::class) && $this->values->isPostClass($name) => 'postClass',
            isset($this->tables[$name]) => 'row',
            default => null,
        };
        $error = match (true) {
            $parameter->getAttributes(Context::class) !== [] => 'has #[Item] and #[Context]; a parameter receives either the current item of a repeater or a value of an ancestor',
            $parameter->isVariadic() || $parameter->isPassedByReference() => 'with #[Item] must be neither variadic nor passed by reference',
            $source === null => sprintf(
                'has #[Item], but its type %s is no item of a repeater; use a post class of the theme or plugin, WP_Post, WP_Term, WP_Comment, WP_User or a row class (#[Table]) of the theme or plugin',
                $type === null ? '(none)' : (string) $type,
            ),
            !$type instanceof ReflectionNamedType || !$type->allowsNull() => sprintf('has #[Item], so it must be nullable (?%s): outside an item of its kind it is null', $name),
            default => null,
        };
        if ($error !== null || $name === null || $source === null) {
            $this->errors[] = sprintf('%s %s.', $location, $error);

            return null;
        }

        /** @var class-string $name */
        return [
            'param' => $parameter->getName(),
            'kind' => 'item',
            'source' => $source,
            'class' => in_array($source, ['postClass', 'row'], true) ? $name : null,
            'context' => match ($source) {
                'post', 'postClass' => ['postId', 'postType'],
                'term' => ['termId', 'taxonomy'],
                'comment' => ['commentId'],
                'user' => ['userId'],
                'row' => [TableNames::context($this->namespace, $this->tables[$name])],
            },
        ];
    }

    private function usesContextError(Block $block, string $class): ?string
    {
        foreach ($block->usesContext as $name) {
            if (str_starts_with($name, $this->namespace . '/')) {
                return sprintf(
                    '#[Block] on %s: usesContext contains "%s" of this theme or plugin; read values of its blocks with a render() parameter marked #[Context(Block::class, \'attribute\')].',
                    $class,
                    $name,
                );
            }
        }

        return null;
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
        if (str_contains($block->name, '/')) {
            return sprintf(
                '#[Block] on %s has the name "%s" with a namespace; declare the name without it, e.g. "hero": the block is registered in the namespace of the theme or plugin (extra.wp-app-framework.namespace, here "%s").',
                $class,
                $block->name,
                $this->namespace,
            );
        }
        if (preg_match(self::NAME_PATTERN, $block->name) !== 1) {
            return sprintf('#[Block] on %s has the invalid name "%s": use lowercase letters, digits and "-", e.g. "hero".', $class, $block->name);
        }
        $blockName = $this->namespace . '/' . $block->name;
        if (isset($this->names[$blockName])) {
            return sprintf('#[Block] on %s uses the name "%s", which %s already declares.', $class, $block->name, $this->names[$blockName]);
        }
        if (trim($block->title) === '') {
            return sprintf('#[Block] on %s has an empty title.', $class);
        }

        return null;
    }

    /**
     * @param array<mixed> $values
     * @param string|null  $location Where the list is declared; default "#[Block] on {class}".
     */
    private function listError(array $values, string $argument, string $class, ?string $location = null): ?string
    {
        $location ??= sprintf('#[Block] on %s', $class);
        if (!array_is_list($values)) {
            return sprintf('%s: %s must be a list of strings.', $location, $argument);
        }
        foreach ($values as $value) {
            if (!is_string($value) || $value === '') {
                return sprintf('%s: %s must contain non-empty strings only.', $location, $argument);
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
     * Checks render() and derives the attribute schemas, inline fields, inspector controls, the attribute stored in
     * the post content and the context the block reads and provides.
     *
     * @param ReflectionClass<object> $class
     * @return Render|null
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
     * @return Render|null
     */
    private function renderParameters(ReflectionMethod $method, string $location): ?array
    {
        $render = [
            'arguments' => [],
            'schemas' => [],
            'fields' => [],
            'controls' => [],
            'postContentField' => null,
            'appenders' => [],
            'contexts' => [],
            'attributeFields' => [],
            'types' => [],
            'provided' => [],
        ];
        $kinds = [];
        $valid = true;

        foreach ($method->getParameters() as $parameter) {
            $parameterLocation = sprintf('parameter $%s of %s', $parameter->getName(), $location);

            if ($parameter->getAttributes(Item::class) !== []) {
                $item = $this->itemArgument($parameter, $parameterLocation);
                if ($item === null) {
                    $valid = false;
                    continue;
                }
                $render['arguments'][] = $item;
                continue;
            }

            $contexts = $parameter->getAttributes(Context::class);
            if ($contexts !== []) {
                try {
                    $context = $contexts[0]->newInstance();
                } catch (Throwable $exception) {
                    $this->errors[] = sprintf('Invalid #[Context] on %s: %s', $parameterLocation, $exception->getMessage());
                    $valid = false;
                    continue;
                }
                if ($parameter->isVariadic() || $parameter->isPassedByReference()) {
                    $this->errors[] = sprintf('%s with #[Context] must be neither variadic nor passed by reference.', $parameterLocation);
                    $valid = false;
                    continue;
                }
                $render['contexts'][] = [$parameter, $context];
                continue;
            }

            $type = $parameter->getType();
            $typeName = $type instanceof ReflectionNamedType && !$type->allowsNull() ? $type->getName() : null;

            $kind = match (true) {
                $typeName === null => null,
                $typeName === 'string' => 'content',
                $typeName === self::BLOCK_CLASS => 'block',
                $typeName === Repeater::class => 'repeater',
                !$type->isBuiltin() => 'attributes',
                default => null,
            };
            if ($kind === null || $parameter->isVariadic() || $parameter->isPassedByReference()) {
                $this->errors[] = sprintf(
                    '%s is not supported: render() accepts the block attribute class, string $content or Repeater $repeater, WP_Block $block, each at most once and not nullable, values of ancestors marked with #[Context] and the current item of a repeater marked with #[Item]; inject services through the constructor.',
                    $parameterLocation,
                );
                $valid = false;
                continue;
            }
            if (in_array($kind, $kinds, true)) {
                $this->errors[] = sprintf('%s: render() accepts only one %s parameter.', $parameterLocation, match ($kind) {
                    'content' => 'string $content',
                    'block' => 'WP_Block',
                    'repeater' => 'Repeater',
                    default => 'block attribute class',
                });
                $valid = false;
                continue;
            }
            $kinds[] = $kind;

            if ($kind !== 'attributes') {
                $render['arguments'][] = ['param' => $parameter->getName(), 'kind' => $kind];
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
            $render = [...$render, ...$attributeSchemas];
            $render['arguments'][] = ['param' => $parameter->getName(), 'kind' => 'attributes', 'class' => $attributesClass];
        }

        return $valid ? $render : null;
    }

    /**
     * Schemas by attribute name, inline fields by path (e.g. "buttons.*.label"), inspector controls by attribute name,
     * the attribute stored in the post content (#[RichText(postContent: true)]), the fields and types of the attributes
     * and the attributes marked with #[ProvidesContext].
     *
     * @param class-string $class
     * @return array{schemas: array<string, array<string, mixed>>, fields: array<string, BlockField>, controls: array<string, Control>, postContentField: string|null, appenders: list<string>, attributeFields: array<string, Field>, types: array<string, string>, provided: list<string>}|null
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
        $appenders = [];
        $types = [];
        $provided = [];
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
            $types[$name] = (string) $parameter->getType();
            if ($parameter->getAttributes(ProvidesContext::class) !== []) {
                $provided[] = $name;
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
            foreach (self::appenderPaths($field['control'], $name) as $path) {
                if (str_contains($path, '*')) {
                    $this->errors[] = sprintf('%s: the list "%s" has appender in #[ListOf], but is in a list item; only lists outside of list items get a new item in the canvas.', $attributeLocation, $path);
                    $valid = false;
                    continue;
                }
                $appenders[] = $path;
            }
            if ($field['control'] !== null) {
                // The inspector shows content controls in the "content" group, which stays visible in contentOnly mode.
                $controls[$name] = [...$field['control'], 'content' => $field['content']];
            }
        }

        return $valid && $fields !== null ? [
            'schemas' => $schemas,
            'fields' => $editorFields,
            'controls' => $controls,
            'postContentField' => $postContentField,
            'appenders' => $appenders,
            'attributeFields' => $fields,
            'types' => $types,
            'provided' => $provided,
        ] : null;
    }

    /**
     * Paths of the lists with appender (#[ListOf]) in a control, list items as `*`.
     *
     * @param array<string, mixed>|null $control
     * @return list<string>
     */
    private static function appenderPaths(?array $control, string $path): array
    {
        $paths = [];
        if (($control['type'] ?? null) === 'list') {
            if (($control['appender'] ?? false) === true) {
                $paths[] = $path;
            }
            /** @var array<string, mixed>|null $item */
            $item = $control['item'] ?? null;
            array_push($paths, ...self::appenderPaths($item, $path . '.*'));
        } elseif (($control['type'] ?? null) === 'object') {
            /** @var array<string, array<string, mixed>> $fields */
            $fields = $control['fields'] ?? [];
            foreach ($fields as $name => $field) {
                array_push($paths, ...self::appenderPaths($field, $path . '.' . $name));
            }
        }

        return $paths;
    }

    private static function shortName(string $class): string
    {
        return substr($class, (int) strrpos($class, '\\') + 1);
    }
}
