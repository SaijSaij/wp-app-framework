<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Block;

use LogicException;
use Saij\WpAppFramework\Cache\CacheStore;
use Saij\WpAppFramework\DependencyInjection\Container;
use Saij\WpAppFramework\DependencyInjection\NoService;
use Saij\WpAppFramework\Editor\RenderContext;
use Saij\WpAppFramework\Manifest\Manifest;
use Saij\WpAppFramework\Value\ValueFactory;
use Saij\WpAppFramework\View\ViewAssets;
use Saij\WpAppFramework\View\ViewData;
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
 * Custom block supports (#[UseSupport]) are evaluated before the block
 * renders: the value of each support attribute passes through the value
 * factory, the support service returns the class names, and
 * block_wrapper_attributes() adds them to the root element. Invalid class
 * names and a template without block_wrapper_attributes() are errors.
 *
 * Blocks with view scripts (front-end scripts next to the block class or the
 * classes of its block supports) mark their root element through
 * block_wrapper_attributes() and enqueue the scripts when they render; not
 * in editor renders and not for excerpts, so a page only loads the scripts of
 * blocks it shows. WordPress dequeues them again if the block renders nothing
 * (WP_Block::render()). A template without block_wrapper_attributes() is an
 * error.
 *
 * A block with #[Cache] renders from the cache store, except in renders for
 * the block editor and its preview: the entry holds the HTML, the scripts,
 * styles and script modules render() enqueued and the view data it set; a
 * cache hit enqueues them and the view scripts again and sets the view data
 * again. Errors are not cached.
 *
 * Values of ancestors (#[Context] parameters) come from the block context:
 * sanitized against the schema of the providing attribute and created like
 * attributes; without a providing ancestor the parameter keeps its default.
 *
 * Custom block categories are added before those of WordPress
 * (`block_categories_all`).
 *
 * A block name another framework instance or plugin already registered is an
 * error (WordPress itself only reports it as "doing it wrong"): with WP_DEBUG
 * it is thrown, otherwise logged and the block is skipped.
 *
 * @internal
 *
 * @phpstan-import-type BlockEntry from Manifest
 * @phpstan-import-type BlockCategoryEntry from Manifest
 * @phpstan-import-type Queues from RenderedAssets
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
        private ViewAssets $views,
        private ?CacheStore $cache = null,
        private ?ViewData $viewData = null,
    ) {}

    /**
     * @param list<BlockEntry>         $blocks
     * @param list<BlockCategoryEntry> $categories Custom block categories, added before those of WordPress.
     */
    public function register(array $blocks, array $categories = []): void
    {
        if ($categories !== []) {
            add_filter('block_categories_all', static function (array $existing) use ($categories): array {
                $added = [];
                foreach ($categories as $category) {
                    $added[] = ['slug' => $category['slug'], 'title' => $category['title'], 'icon' => $category['icon']];
                }

                return [...$added, ...$existing];
            });
        }

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

            $cache = $block['cache'];
            if ($cache !== null && $this->cache !== null && !$this->context->isEditor() && !$this->context->isPreview()) {
                $vary = array_column($cache['vary'], 'type');
                [$entry, $hit] = $this->cache->fetch(
                    'block',
                    $block['name'],
                    [$attributes, $content, self::contextValues($block, $instance)],
                    $cache,
                    fn(): array => [$this->renderRecorded($block, $attributes, $content, $instance, $vary), true],
                );
                /** @var array{html: string, assets: Queues, viewData?: array<string, mixed>} $entry Stored by renderRecorded(). */
                if ($hit) {
                    if (ViewAssets::hasViews($block)) {
                        $this->views->enqueue($block);
                    }
                    RenderedAssets::enqueue($entry['assets'], $block['name']);
                    $this->viewData?->replay($entry['viewData'] ?? []);
                }

                return $entry['html'];
            }

            return $this->renderTemplate($block, $attributes, $content, $instance, null);
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
     * Runs render() with the block as current block of the render context and enqueues its view scripts.
     *
     * @param BlockEntry           $block
     * @param array<string, mixed> $attributes
     * @param list<string>|null    $cached Types of the variants if the output is cached.
     */
    private function renderTemplate(array $block, array $attributes, string $content, ?WP_Block $instance, ?array $cached): string
    {
        $named = [];
        foreach ($block['arguments'] as $argument) {
            if ($argument['kind'] === 'context') {
                if ($instance !== null && array_key_exists($argument['name'], $instance->context)) {
                    $raw = $instance->context[$argument['name']];
                    if ($raw !== null) {
                        $raw = rest_sanitize_value_from_schema($raw, $argument['schema'], $argument['param']);
                    }
                    $named[$argument['param']] = $this->values->create($argument['value'], $raw, $argument['param']);
                } elseif (!$argument['hasDefault']) {
                    // Without a providing ancestor the parameter keeps its default; the build requires one or null.
                    $named[$argument['param']] = null;
                }
                continue;
            }
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

        ['classes' => $classes, 'values' => $supportValues] = $this->supportValues($block, $attributes);
        $views = ViewAssets::hasViews($block) && !$this->context->isEditor();
        $html = $this->context->renderBlock(
            $block['name'],
            $block['editor']['fields'],
            $block['editor']['innerBlocks'],
            // The build checked that the block class has a public render(): string.
            // @phpstan-ignore method.notFound, return.type
            fn(): string => $this->container->get($block['class'])->render(...$named),
            $classes,
            self::missingWrapper($block),
            $views ? $this->views->marker($block, $supportValues) : [],
            $cached,
            $block['editor']['appenders'],
        );
        if ($views) {
            $this->views->enqueue($block);
        }

        return $html;
    }

    /**
     * Renders the block for its cache entry: the HTML, what render() enqueued besides the view scripts and the view
     * data it set.
     *
     * @param BlockEntry           $block
     * @param array<string, mixed> $attributes
     * @param list<string>         $vary
     * @return array{html: string, assets: Queues, viewData: array<string, mixed>}
     */
    private function renderRecorded(array $block, array $attributes, string $content, ?WP_Block $instance, array $vary): array
    {
        $before = RenderedAssets::snapshot();
        $this->viewData?->startRecording();
        try {
            $html = $this->renderTemplate($block, $attributes, $content, $instance, $vary);
        } finally {
            $viewData = $this->viewData?->stopRecording() ?? [];
        }

        return [
            'html' => $html,
            'assets' => RenderedAssets::added($before, RenderedAssets::snapshot(), $this->views->handles($block)),
            'viewData' => $viewData,
        ];
    }

    /**
     * The values of the context the block uses (`usesContext`), part of its cache key.
     *
     * @param BlockEntry $block
     * @return array<string, mixed>
     */
    private static function contextValues(array $block, ?WP_Block $instance): array
    {
        $names = $block['args']['uses_context'] ?? [];
        $values = [];
        foreach (is_array($names) ? $names : [] as $name) {
            if (is_string($name)) {
                $values[$name] = $instance?->context[$name] ?? null;
            }
        }

        return $values;
    }

    /**
     * The error if the block renders without block_wrapper_attributes(); null if it may.
     *
     * @param BlockEntry $block
     */
    private static function missingWrapper(array $block): ?string
    {
        if (ViewAssets::hasViews($block)) {
            return sprintf(
                'Block %s has view scripts, but did not print its root element with block_wrapper_attributes(), so its views cannot be mounted%s.',
                $block['name'],
                $block['supports'] !== [] ? ' and the class names of its block supports (#[UseSupport]) are missing' : '',
            );
        }
        if ($block['supports'] !== []) {
            return sprintf(
                'Block %s uses block supports (#[UseSupport]), but did not print its root element with block_wrapper_attributes(), so the class names of the supports are missing.',
                $block['name'],
            );
        }

        return null;
    }

    /**
     * Class names of the block supports for the given attributes and the sanitized value of each support attribute.
     *
     * @param BlockEntry           $block
     * @param array<string, mixed> $attributes
     * @return array{classes: list<string>, values: array<string, mixed>}
     */
    private function supportValues(array $block, array $attributes): array
    {
        $classes = [];
        $values = [];
        foreach ($block['supports'] as $support) {
            $attribute = $support['attribute'];
            $raw = $attributes[$attribute] ?? null;
            if ($raw !== null) {
                $raw = rest_sanitize_value_from_schema($raw, $block['args']['attributes'][$attribute], $attribute);
            }
            $values[$attribute] = $raw;
            $value = $this->values->create($support['value'], $raw, $attribute);
            // The build checked that the support class has a public className(): ?string with one parameter.
            /** @var string|null $result */
            // @phpstan-ignore method.notFound
            $result = $this->container->get($support['class'])->className($value);
            foreach (preg_split('/\s+/', (string) $result, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $class) {
                if (sanitize_html_class($class) !== $class) {
                    throw new LogicException(sprintf(
                        'Block support %s::className() returned the invalid class name "%s" for the value %s of attribute "%s" of block %s; use letters, digits, "-" and "_".',
                        $support['class'],
                        $class,
                        var_export($raw, true),
                        $attribute,
                        $block['name'],
                    ));
                }
                $classes[] = $class;
            }
        }

        return ['classes' => $classes, 'values' => $values];
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
