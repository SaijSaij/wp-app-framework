<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Block;

use LogicException;
use Saij\WpAppFramework\DependencyInjection\Container;
use Saij\WpAppFramework\DependencyInjection\NoService;
use Saij\WpAppFramework\Editor\RenderContext;
use Saij\WpAppFramework\Manifest\Manifest;
use Saij\WpAppFramework\Value\ValueFactory;
use Throwable;
use WP_Block;
use WP_Block_Type_Registry;

/**
 * Registers the blocks of the manifest with WordPress (on init).
 *
 * The block service is resolved only when a block is rendered. The attributes
 * pass through the value factory. While rendering, the block is the current
 * block of the render context (for field() and inner_blocks()). Errors are rethrown when debugging is
 * enabled; otherwise they are logged and the block renders nothing.
 *
 * A value marked with #[RichText(postContent: true)] is the block's inner HTML;
 * WordPress never parses sourced attributes on the server, so it is taken from
 * the block content before the attributes are created.
 *
 * Blocks with text fields marked excerpt: true are added to
 * `excerpt_allowed_blocks`, blocks with `excerptWrapper` to
 * `excerpt_allowed_wrapper_blocks`. WordPress renders allowed blocks for
 * excerpts while the `get_the_excerpt` filter runs (wp_trim_excerpt(),
 * excerpt_remove_blocks()) and offers no other signal; during that filter the
 * block renders only its excerpt fields, one paragraph each, without running
 * render() or its template.
 *
 * A block name another framework instance or plugin already registered is an
 * error (WordPress itself only reports it as "doing it wrong"): with WP_DEBUG
 * it is thrown, otherwise logged and the block is skipped.
 *
 * @internal
 *
 * @phpstan-import-type BlockEntry from Manifest
 */
#[NoService]
final readonly class BlockRegistrar
{
    public function __construct(
        private Container $container,
        private ValueFactory $values,
        private RenderContext $context,
        private bool $debug,
        private string $slug,
    ) {}

    /**
     * @param list<BlockEntry> $blocks
     */
    public function register(array $blocks): void
    {
        $registry = WP_Block_Type_Registry::get_instance();
        $registered = [];
        foreach ($blocks as $block) {
            if ($registry->is_registered($block['name'])) {
                $message = sprintf(
                    'Block %s of %s cannot be registered: a block with this name is already registered (by another plugin, theme or framework instance). Block names must be unique; use the namespace of the theme or plugin.',
                    $block['name'],
                    $this->slug,
                );
                if ($this->debug) {
                    throw new LogicException($message);
                }
                error_log('wp-app-framework: ' . $message);
                continue;
            }

            register_block_type($block['name'], [
                ...$block['args'],
                // WP_Block_Type::render() calls render callbacks without the block instance.
                'render_callback' => function (array $attributes, string $content = '', ?WP_Block $instance = null) use ($block): string {
                    /** @var array<string, mixed> $attributes Prepared by WP_Block_Type::prepare_attributes_for_render(). */
                    return $this->render($block, $attributes, $content, $instance);
                },
            ]);
            $registered[] = $block;
        }

        $excerptBlocks = [];
        $excerptWrappers = [];
        foreach ($registered as $block) {
            if ($block['excerpt']['fields'] !== []) {
                $excerptBlocks[] = $block['name'];
            }
            if ($block['excerpt']['wrapper']) {
                $excerptWrappers[] = $block['name'];
            }
        }
        if ($excerptBlocks !== []) {
            add_filter('excerpt_allowed_blocks', static fn(array $allowed): array => [...$allowed, ...$excerptBlocks]);
        }
        if ($excerptWrappers !== []) {
            add_filter('excerpt_allowed_wrapper_blocks', static fn(array $allowed): array => [...$allowed, ...$excerptWrappers]);
        }
    }

    /**
     * @param BlockEntry           $block
     * @param array<string, mixed> $attributes Attributes as WordPress prepared them: validated, defaults applied.
     */
    public function render(array $block, array $attributes, string $content, ?WP_Block $instance): string
    {
        try {
            $postContentField = $block['editor']['postContentField'];
            if ($postContentField !== null) {
                // The JS serializer wraps the inner HTML in line breaks.
                $attributes[$postContentField] = trim($content);
            }

            if ($block['excerpt']['fields'] !== [] && doing_filter('get_the_excerpt')) {
                return $this->renderExcerpt($block, $attributes);
            }

            $named = [];
            foreach ($block['arguments'] as $argument) {
                $named[$argument['param']] = match ($argument['kind']) {
                    'attributes' => $this->values->createAttributes($argument['class'], $attributes, $block['args']['attributes']),
                    'content' => $content,
                    'block' => $instance ?? throw new LogicException(sprintf(
                        'Block %s was rendered without a WP_Block instance, but %s::render() needs one.',
                        $block['name'],
                        $block['class'],
                    )),
                };
            }

            return $this->context->renderBlock(
                $block['name'],
                $block['editor']['fields'],
                $block['editor']['innerBlocks'],
                // The build checked that the block class has a public render(): string.
                // @phpstan-ignore method.notFound, return.type
                fn(): string => $this->container->get($block['class'])->render(...$named),
            );
        } catch (Throwable $exception) {
            if ($this->debug) {
                throw $exception;
            }

            error_log(sprintf(
                'wp-app-framework: block %s (%s::render()) failed: %s: %s in %s:%d',
                $block['name'],
                $block['class'],
                $exception::class,
                $exception->getMessage(),
                $exception->getFile(),
                $exception->getLine(),
            ));

            return '';
        }
    }

    /**
     * The excerpt fields as paragraphs; rich text as stored, plain text escaped. wp_trim_excerpt() strips the tags.
     *
     * @param BlockEntry           $block
     * @param array<string, mixed> $attributes
     */
    private function renderExcerpt(array $block, array $attributes): string
    {
        $class = null;
        foreach ($block['arguments'] as $argument) {
            if ($argument['kind'] === 'attributes') {
                $class = $argument['class'];
            }
        }
        if ($class === null) {
            throw new LogicException(sprintf('Block %s has excerpt fields, but %s::render() accepts no attribute class.', $block['name'], $block['class']));
        }
        $values = $this->values->createAttributes($class, $attributes, $block['args']['attributes']);

        $html = '';
        foreach ($block['excerpt']['fields'] as $path => $kind) {
            foreach (self::valuesAt($values, explode('.', $path)) as $value) {
                if (!is_string($value) || trim($value) === '') {
                    continue;
                }
                $html .= '<p>' . ($kind === 'rich' ? $value : esc_html($value)) . "</p>\n";
            }
        }

        return $html;
    }

    /**
     * The values at a field path; "*" matches every item of a list.
     *
     * @param list<string> $segments
     * @return list<mixed>
     */
    private static function valuesAt(mixed $value, array $segments): array
    {
        if ($segments === []) {
            return [$value];
        }
        $segment = array_shift($segments);
        if ($segment === '*') {
            return is_array($value) ? array_merge(...array_map(static fn(mixed $item): array => self::valuesAt($item, $segments), array_values($value))) : [];
        }
        if (!is_object($value) || !property_exists($value, $segment)) {
            return [];
        }

        return self::valuesAt($value->{$segment}, $segments);
    }
}
