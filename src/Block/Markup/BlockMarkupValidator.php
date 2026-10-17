<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Block\Markup;

use WebCodingCat\WpAppFramework\Block\BlockDefinition;
use WebCodingCat\WpAppFramework\DependencyInjection\NoService;

/**
 * Checks the block templates, template parts and patterns of a theme or child theme against the blocks of the
 * same instance. Blocks of other instances, of plugins and of WordPress are not checked: their schemas are not
 * part of the build. A block name in the namespace of the instance must be one of its blocks.
 */
#[NoService]
final class BlockMarkupValidator
{
    /**
     * Attributes the block editor adds to every block (`core/lock/addAttribute`, `core/metadata/addMetaAttribute`).
     */
    private const array EDITOR_ATTRIBUTES = ['lock' => ['type' => 'object'], 'metadata' => ['type' => 'object']];

    /** @var array<string, BlockDefinition> */
    private array $blocks = [];

    /** @var list<string> */
    private array $errors = [];

    /**
     * @param non-empty-string      $namespace Namespace of the instance (`extra.wp-app-framework.namespace`).
     * @param list<BlockDefinition> $blocks
     */
    public function __construct(
        private readonly string $namespace,
        array $blocks,
    ) {
        foreach ($blocks as $block) {
            $this->blocks[$block->getName()] = $block;
        }
    }

    public function validate(MarkupFiles $files): void
    {
        foreach ($files->getDeprecatedDirectories() as $directory) {
            $this->errors[] = sprintf(
                '%s/: WordPress reads block templates and template parts from the deprecated directories %s instead of templates/ and parts/; move the files to templates/ and parts/.',
                $directory,
                implode(' and ', array_map(static fn(string $name): string => $name . '/', MarkupFiles::DEPRECATED_DIRECTORIES)),
            );
        }

        foreach ($files->getFiles() as $file => $kind) {
            $content = file_get_contents($files->getProjectDirectory() . '/' . $file);
            if ($content === false) {
                $this->errors[] = sprintf('%s: cannot read the file.', $file);
                continue;
            }

            $parser = new BlockMarkupParser($kind === MarkupKind::Pattern ? PatternSource::toMarkup($content) : $content);
            foreach ($parser->getErrors() as [$line, $message]) {
                $this->errors[] = sprintf('%s:%d: %s', $file, $line, $message);
            }
            $blocks = $parser->getBlocks();
            // The block editor edits template parts and inserted patterns with a single top-level block as
            // contentOnly sections (isSectionBlockCandidate(), parsePattern() in the block editor).
            $section = match ($kind) {
                MarkupKind::Part => 'template part',
                MarkupKind::Pattern => count($blocks) === 1 && !$parser->hasFreeformHtml() ? 'pattern' : null,
                MarkupKind::Template => null,
            };
            foreach ($blocks as $block) {
                $this->block($file, $section, $block, []);
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
     * @param string|null  $section   What makes the file a contentOnly section in the editor, if it is one.
     * @param list<string> $ancestors Names of the enclosing blocks, the parent last.
     */
    private function block(string $file, ?string $section, ParsedBlock $block, array $ancestors): void
    {
        $definition = $this->blocks[$block->getName()] ?? null;
        $prefix = sprintf('%s:%d: block %s: ', $file, $block->getLine(), $block->getName());

        if ($definition === null) {
            if (str_starts_with($block->getName(), $this->namespace . '/')) {
                $this->errors[] = $prefix . sprintf('unknown block; "%s" is the namespace of this theme (extra.wp-app-framework.namespace).', $this->namespace);
            } elseif ($block->getAttributes() === null && !$block->hasPhp()) {
                $this->errors[] = $prefix . 'invalid JSON in the block comment; WordPress ignores all attributes.';
            }
            foreach ($block->getInnerBlocks() as $inner) {
                $this->block($file, $section, $inner, [...$ancestors, $block->getName()]);
            }

            return;
        }

        $editor = $definition->getEditor();
        $args = $definition->getArgs();
        $parent = is_array($args['parent'] ?? null) ? array_filter($args['parent'], is_string(...)) : null;
        if ($parent !== null && !in_array($ancestors === [] ? null : $ancestors[array_key_last($ancestors)], $parent, true)) {
            $this->errors[] = $prefix . sprintf('the block can only be placed directly in %s (parent).', implode(', ', $parent));
        }
        $ancestor = is_array($args['ancestor'] ?? null) ? array_filter($args['ancestor'], is_string(...)) : null;
        if ($ancestor !== null && array_intersect($ancestors, $ancestor) === []) {
            $this->errors[] = $prefix . sprintf('the block can only be placed inside %s (ancestor).', implode(', ', $ancestor));
        }
        if ($block->hasPhp()) {
            $this->errors[] = $prefix . 'PHP code in the block comment; the build cannot check attributes computed at run time. Write the attributes as JSON.';
        } elseif ($block->getAttributes() === null) {
            $this->errors[] = $prefix . 'invalid JSON in the block comment; WordPress ignores all attributes.';
        } else {
            foreach ($block->getAttributes() as $name => $value) {
                $this->attribute($prefix, $definition, (string) $name, $value);
            }
        }

        if (trim($block->getInnerHtml()) !== '' && $editor['postContentField'] === null) {
            $this->errors[] = $prefix . 'HTML between the block comments; only a block with a #[RichText(postContent: true)] field stores HTML in the post content.';
        }

        $inner = $block->getInnerBlocks();
        $allowed = $definition->getArgs()['allowed_blocks'] ?? null;
        $allowed = is_array($allowed) ? array_values(array_filter($allowed, is_string(...))) : null;
        if ($inner !== [] && !$editor['innerBlocks']) {
            $this->errors[] = $prefix . 'inner blocks, but the block has none (its render() takes no $content).';
        } elseif ($allowed !== null) {
            foreach ($inner as $child) {
                if (!in_array($child->getName(), $allowed, true)) {
                    $this->errors[] = sprintf(
                        '%s:%d: block %s: not in #[InnerBlocks(allowed)] of %s (%s).',
                        $file,
                        $child->getLine(),
                        $child->getName(),
                        $block->getName(),
                        implode(', ', $allowed),
                    );
                }
            }
        }

        if ($section !== null && $editor['innerBlocks'] && !self::isContentBlock($definition)) {
            foreach ($inner as $child) {
                $childDefinition = $this->blocks[$child->getName()] ?? null;
                if ($childDefinition !== null && self::isContentBlock($childDefinition)) {
                    $this->errors[] = $prefix . sprintf(
                        'in a %s it is edited in contentOnly mode, where blocks can only be inserted into containers that are content blocks; set #[Supports(contentRole: true)] or give it a content attribute.',
                        $section,
                    );
                    break;
                }
            }
        }

        $options = $editor['innerBlockOptions'];
        $template = $options['template'] ?? null;
        if (is_array($template) && in_array($options['templateLock'] ?? null, ['all', 'contentOnly'], true)) {
            $difference = self::templateDifference($inner, $template, $block->getName());
            if ($difference !== null) {
                $this->errors[] = $prefix . sprintf(
                    'the inner blocks differ from the template of the block (%s), which is locked (templateLock %s): the editor replaces them with the template.',
                    $difference,
                    $options['templateLock'],
                );
            }
        }

        foreach ($inner as $child) {
            $this->block($file, $section, $child, [...$ancestors, $block->getName()]);
        }
    }

    /**
     * Where the inner blocks differ from a locked template, compared by name like synchronizeBlocksWithTemplate() of
     * the block editor; null if they match.
     *
     * @param list<ParsedBlock> $blocks
     * @param array<mixed>      $template Template nodes (name, attributes, innerBlocks).
     */
    private static function templateDifference(array $blocks, array $template, string $path): ?string
    {
        $expected = array_values(array_map(static fn(mixed $node): string => is_array($node) && is_string($node['name'] ?? null) ? $node['name'] : '', $template));
        $actual = array_map(static fn(ParsedBlock $block): string => $block->getName(), $blocks);
        if ($expected !== $actual) {
            return sprintf('%s: expected [%s], found [%s]', $path, implode(', ', $expected), implode(', ', $actual));
        }
        foreach ($blocks as $index => $block) {
            $node = $template[$index] ?? null;
            $innerTemplate = is_array($node) && is_array($node['innerBlocks'] ?? null) ? $node['innerBlocks'] : [];
            // A template block without inner blocks leaves the inner blocks of the block alone.
            if ($innerTemplate === []) {
                continue;
            }
            $difference = self::templateDifference($block->getInnerBlocks(), $innerTemplate, $path . ' > ' . $block->getName());
            if ($difference !== null) {
                return $difference;
            }
        }

        return null;
    }

    private function attribute(string $prefix, BlockDefinition $definition, string $name, mixed $value): void
    {
        foreach (self::attributeErrors($definition, $name, $value, false) as $error) {
            $this->errors[] = $prefix . $error;
        }
    }

    /**
     * Checks an attribute value of a block of the instance: attributes of its class, of the block supports it
     * enables and those the editor adds to every block.
     *
     * @param bool $postContent Whether the attribute stored in the post content (#[RichText(postContent: true)]) may
     *                          be given, as in templates of the editor, which serializes it as HTML.
     * @return list<string>
     */
    public static function attributeErrors(BlockDefinition $definition, string $name, mixed $value, bool $postContent): array
    {
        $args = $definition->getArgs();
        $supports = is_array($args['supports'] ?? null) ? $args['supports'] : [];

        if ($name === $definition->getEditor()['postContentField'] && !$postContent) {
            return [sprintf('attribute "%s" is stored as HTML between the block comments, not in the JSON.', $name)];
        }

        $schema = $args['attributes'][$name] ?? self::EDITOR_ATTRIBUTES[$name] ?? match (true) {
            // Attributes of WordPress' block supports, registered with the support (block-supports/*.php).
            $name === 'className' && ($supports['customClassName'] ?? true) === true => ['type' => 'string'],
            $name === 'anchor' && ($supports['anchor'] ?? false) === true => ['type' => 'string'],
            $name === 'align' && is_array($supports['align'] ?? null) => ['type' => 'string', 'enum' => [...$supports['align'], '']],
            $name === 'style' && ($supports['customCSS'] ?? true) === true => ['type' => 'object'],
            default => null,
        };

        if ($schema === null) {
            return [sprintf('unknown attribute "%s".', $name)];
        }

        return AttributeValidator::validate($value, $schema, $name);
    }

    /**
     * A content block for the block editor: an attribute with `role: content` or `supports.contentRole`
     * (`isContentBlock()` in the blocks package).
     */
    private static function isContentBlock(BlockDefinition $definition): bool
    {
        $args = $definition->getArgs();
        $supports = $args['supports'] ?? null;
        if (is_array($supports) && ($supports['contentRole'] ?? false) === true) {
            return true;
        }

        foreach ($args['attributes'] as $schema) {
            if (($schema['role'] ?? null) === 'content') {
                return true;
            }
        }

        return false;
    }
}
