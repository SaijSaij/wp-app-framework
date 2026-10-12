<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Block\Markup;

use Saij\WpAppFramework\Block\BlockDefinition;
use Saij\WpAppFramework\DependencyInjection\NoService;

/**
 * Checks the block templates, template parts and patterns of a theme or child theme against the blocks of the
 * same instance. Blocks of other instances, of plugins and of WordPress are not checked: their schemas are not
 * part of the build. A block name in a namespace of the instance's own blocks must be one of them.
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

    /** @var array<string, true> */
    private array $namespaces = [];

    /** @var list<string> */
    private array $errors = [];

    /**
     * @param list<BlockDefinition> $blocks
     */
    public function __construct(array $blocks)
    {
        foreach ($blocks as $block) {
            $this->blocks[$block->getName()] = $block;
            $this->namespaces[strstr($block->getName(), '/', true) ?: $block->getName()] = true;
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
                $this->block($file, $section, $block);
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
     * @param string|null $section What makes the file a contentOnly section in the editor, if it is one.
     */
    private function block(string $file, ?string $section, ParsedBlock $block): void
    {
        $definition = $this->blocks[$block->getName()] ?? null;
        $prefix = sprintf('%s:%d: block %s: ', $file, $block->getLine(), $block->getName());

        if ($definition === null) {
            if (isset($this->namespaces[strstr($block->getName(), '/', true) ?: ''])) {
                $this->errors[] = $prefix . 'unknown block; the namespace is used by the blocks of this theme.';
            } elseif ($block->getAttributes() === null && !$block->hasPhp()) {
                $this->errors[] = $prefix . 'invalid JSON in the block comment; WordPress ignores all attributes.';
            }
            foreach ($block->getInnerBlocks() as $inner) {
                $this->block($file, $section, $inner);
            }

            return;
        }

        $editor = $definition->getEditor();
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
                        '%s:%d: block %s: not in allowedBlocks of %s (%s).',
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

        foreach ($inner as $child) {
            $this->block($file, $section, $child);
        }
    }

    private function attribute(string $prefix, BlockDefinition $definition, string $name, mixed $value): void
    {
        $args = $definition->getArgs();
        $supports = is_array($args['supports'] ?? null) ? $args['supports'] : [];

        if ($name === $definition->getEditor()['postContentField']) {
            $this->errors[] = $prefix . sprintf('attribute "%s" is stored as HTML between the block comments, not in the JSON.', $name);

            return;
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
            $this->errors[] = $prefix . sprintf('unknown attribute "%s".', $name);

            return;
        }

        foreach (AttributeValidator::validate($value, $schema, $name) as $error) {
            $this->errors[] = $prefix . $error;
        }
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
