<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Editor;

use Closure;
use Throwable;
use WebCodingCat\WpAppFramework\DependencyInjection\NoService;
use WP_Block;
use WP_Block_Type_Registry;
use WP_Error;
use WP_Post;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Batch render route of one framework instance for the block editor:
 * POST /wp-app-framework/v1/{slug}/render with `{blocks: [{name, attributes, postId?, context?}]}`.
 *
 * Renders like WordPress' block renderer (render_block() with empty inner
 * blocks, so block supports apply), but many blocks per request and with the
 * editor flag of this instance set. Each entry gets its own result – `{html}`
 * or `{error: {code, message}}` – so one failing block does not fail the batch.
 *
 * Preview route of the same instance for the preview of view scripts in the
 * editor: POST /wp-app-framework/v1/{slug}/preview with `{content, postId?}`,
 * the serialized block with its inner blocks. It renders the block as on the
 * front end (without the editor flag, inner blocks included) and answers
 * `{html}`.
 *
 * Both routes take the block context the editor provides (`context`): only
 * names the block uses (`usesContext`); values of blocks of this instance are
 * validated and sanitized against the schema of the providing attribute,
 * values of other blocks are passed as they are. They are added to the
 * context of the rendered block (`render_block_context`) while it renders.
 *
 * @internal
 *
 * @phpstan-type RenderEntry array{name: string, attributes?: array<string, mixed>, postId?: int, context?: array<string, mixed>}
 */
#[NoService]
final readonly class RenderController
{
    public const string NAMESPACE = 'wp-app-framework/v1';

    /** Maximum number of blocks per request. */
    public const int MAX_BLOCKS = 100;

    /**
     * @param string       $slug     Slug of the framework instance (the theme's template).
     * @param list<string> $blocks   Names of the blocks of this instance; the route renders only these.
     * @param array<string, array<string, array<string, mixed>>> $contexts Schemas of the context values the blocks of
     *                                                                   this instance provide, by block and context name.
     */
    public function __construct(
        private string $slug,
        private array $blocks,
        private RenderContext $context,
        private bool $debug,
        private array $contexts = [],
    ) {}

    /**
     * @return non-falsy-string
     */
    public static function route(string $slug): string
    {
        return '/' . $slug . '/render';
    }

    /**
     * @return non-falsy-string
     */
    public static function previewRoute(string $slug): string
    {
        return '/' . $slug . '/preview';
    }

    public function register(): void
    {
        register_rest_route(self::NAMESPACE, self::previewRoute($this->slug), [
            'methods' => 'POST',
            'permission_callback' => static fn(): bool => current_user_can('edit_posts'),
            'callback' => $this->preview(...),
            'args' => [
                'content' => ['type' => 'string', 'required' => true],
                'postId' => ['type' => 'integer', 'minimum' => 1],
                'context' => ['type' => 'object', 'default' => []],
            ],
        ]);
        register_rest_route(self::NAMESPACE, self::route($this->slug), [
            'methods' => 'POST',
            'permission_callback' => static fn(): bool => current_user_can('edit_posts'),
            'callback' => $this->handle(...),
            'args' => [
                'blocks' => [
                    'type' => 'array',
                    'required' => true,
                    'maxItems' => self::MAX_BLOCKS,
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => ['type' => 'string', 'required' => true],
                            'attributes' => ['type' => 'object', 'default' => []],
                            'postId' => ['type' => 'integer', 'minimum' => 1],
                            'context' => ['type' => 'object', 'default' => []],
                        ],
                        'additionalProperties' => false,
                    ],
                ],
            ],
        ]);
    }

    public function handle(WP_REST_Request $request): WP_REST_Response
    {
        /** @var list<RenderEntry> $entries Validated and sanitized by WordPress against the route schema. */
        $entries = $request->get_param('blocks');
        $results = [];
        foreach ($entries as $entry) {
            $results[] = $this->renderEntry($entry);
        }

        return new WP_REST_Response(['results' => $results]);
    }

    public function preview(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        /** @var string $content Validated by WordPress against the route schema. */
        $content = $request->get_param('content');
        /** @var int|null $postId */
        $postId = $request->get_param('postId');

        $blocks = array_values(array_filter(parse_blocks($content), static fn(array $block): bool => $block['blockName'] !== null));
        if (count($blocks) !== 1 || !in_array($blocks[0]['blockName'], $this->blocks, true)) {
            return new WP_Error('wp_app_framework_invalid_preview', sprintf('The preview renders exactly one block of %s.', $this->slug), ['status' => 400]);
        }
        $block = $blocks[0];

        $post = $this->post($postId);
        if (isset($post['error'])) {
            return new WP_Error($post['error']['code'], $post['error']['message'], ['status' => $post['error']['code'] === 'wp_app_framework_forbidden' ? 403 : 404]);
        }
        /** @var array<string, mixed>|null $values Validated by WordPress against the route schema; null without its default. */
        $values = $request->get_param('context');
        $context = $this->contextValues((string) $block['blockName'], $values ?? []);
        if (isset($context['error'])) {
            return new WP_Error($context['error']['code'], $context['error']['message'], ['status' => 400]);
        }

        try {
            // Rendered like the front end, but never from or into the cache: the preview shows the current attributes.
            return new WP_REST_Response(['html' => self::withPost($post['post'], fn(): string => $this->context->renderForPreview(
                static fn(): string => self::withContext($context['values'], static fn(): string => render_block($block)),
            ))]);
        } catch (Throwable $exception) {
            $error = $this->renderError((string) $block['blockName'], $exception, 'preview');

            return new WP_Error($error['error']['code'], $error['error']['message'], ['status' => 500, ...($error['error']['data'] ?? [])]);
        }
    }

    /**
     * The post to render a block for, checked against the permissions of the current user: a post they may edit, or
     * one that is public anyway (the previews of a repeater show posts of others).
     *
     * @return array{post: WP_Post|null}|array{error: array{code: string, message: string}}
     */
    private function post(?int $postId): array
    {
        if ($postId === null) {
            return ['post' => null];
        }
        $post = get_post($postId);
        if ($post === null) {
            return self::error('wp_app_framework_post_not_found', sprintf(/* translators: %d: ID of the post */ __('Post %d does not exist.', 'wp-app-framework'), $postId));
        }
        if (!current_user_can('edit_post', $postId) && !is_post_publicly_viewable($post)) {
            return self::error('wp_app_framework_forbidden', sprintf(/* translators: %d: ID of the post */ __('You may not edit post %d, and it is not public.', 'wp-app-framework'), $postId));
        }

        return ['post' => $post];
    }

    /**
     * Renders with the post as global post (template tags, the postId context), restoring the previous post afterwards.
     *
     * @param Closure(): string $render
     */
    private static function withPost(?WP_Post $post, Closure $render): string
    {
        if ($post === null) {
            return $render();
        }
        $hadPost = array_key_exists('post', $GLOBALS);
        $previousPost = $GLOBALS['post'] ?? null;
        try {
            $GLOBALS['post'] = $post;
            setup_postdata($post);

            return $render();
        } finally {
            if ($hadPost) {
                $GLOBALS['post'] = $previousPost;
            } else {
                unset($GLOBALS['post']);
            }
            if (is_object($previousPost)) {
                setup_postdata($previousPost);
            }
        }
    }

    /**
     * Logs a failed render and describes it, with details only when debugging.
     *
     * @return array{error: array{code: string, message: string, data?: array<string, mixed>}}
     */
    private function renderError(string $name, Throwable $exception, string $kind): array
    {
        error_log(sprintf(
            'wp-app-framework: editor %s of block %s failed: %s: %s in %s:%d',
            $kind,
            $name,
            $exception::class,
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
        ));

        return $this->debug
            ? self::error('wp_app_framework_render_failed', $exception->getMessage(), [
                'exception' => $exception::class,
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ])
            : self::error('wp_app_framework_render_failed', sprintf(/* translators: %s: name of the block */ __('Block %s could not be rendered.', 'wp-app-framework'), $name));
    }

    /**
     * @param RenderEntry $entry
     * @return array{html: string}|array{error: array{code: string, message: string, data?: array<string, mixed>}}
     */
    private function renderEntry(array $entry): array
    {
        $name = $entry['name'];
        $postId = $entry['postId'] ?? null;
        $attributes = $entry['attributes'] ?? [];

        $blockType = in_array($name, $this->blocks, true) ? WP_Block_Type_Registry::get_instance()->get_registered($name) : null;
        if ($blockType === null) {
            return self::error('wp_app_framework_unknown_block', sprintf('Block %s is not a block of %s.', $name, $this->slug));
        }

        $post = $this->post($postId);
        if (isset($post['error'])) {
            return $post;
        }
        $context = $this->contextValues($name, $entry['context'] ?? []);
        if (isset($context['error'])) {
            return $context;
        }

        $schema = ['type' => 'object', 'properties' => $blockType->get_attributes(), 'additionalProperties' => false];
        $valid = rest_validate_value_from_schema($attributes, $schema, 'attributes');
        if ($valid instanceof WP_Error) {
            return self::error('wp_app_framework_invalid_attributes', $valid->get_error_message());
        }
        /** @var array<string, mixed> $attributes */
        $attributes = rest_sanitize_value_from_schema($attributes, $schema, 'attributes');

        // A value stored in the post content (#[RichText(postContent: true)]) is the block's inner HTML,
        // as on the front end.
        $innerHtml = '';
        foreach ($blockType->get_attributes() as $attribute => $attributeSchema) {
            if (is_array($attributeSchema) && ($attributeSchema['source'] ?? null) === 'html' && array_key_exists($attribute, $attributes)) {
                $innerHtml = is_string($attributes[$attribute]) ? $attributes[$attribute] : '';
                unset($attributes[$attribute]);
            }
        }

        try {
            return ['html' => self::withPost($post['post'], fn(): string => $this->context->renderForEditor(static fn(): string => self::withContext($context['values'], static fn(): string => render_block([
                'blockName' => $name,
                'attrs' => $attributes,
                'innerBlocks' => [],
                'innerHTML' => $innerHtml,
                'innerContent' => $innerHtml === '' ? [] : [$innerHtml],
            ]))))];
        } catch (Throwable $exception) {
            return $this->renderError($name, $exception, 'render');
        }
    }

    /**
     * Checks the context the editor sent for a block: only names the block uses, values of this instance against
     * the schema of the providing attribute.
     *
     * @param array<string, mixed> $context
     * @return array{values: array<string, mixed>}|array{error: array{code: string, message: string}}
     */
    private function contextValues(string $name, array $context): array
    {
        $uses = WP_Block_Type_Registry::get_instance()->get_registered($name)?->get_uses_context() ?? [];
        $values = [];
        foreach ($context as $key => $value) {
            $key = (string) $key;
            if (!in_array($key, $uses, true)) {
                return self::error('wp_app_framework_invalid_context', sprintf('Block %s does not use the context "%s".', $name, $key));
            }
            $schema = $this->contexts[$name][$key] ?? null;
            if ($schema !== null) {
                $valid = rest_validate_value_from_schema($value, $schema, $key);
                if ($valid instanceof WP_Error) {
                    return self::error('wp_app_framework_invalid_context', $valid->get_error_message());
                }
                $value = rest_sanitize_value_from_schema($value, $schema, $key);
            }
            $values[$key] = $value;
        }

        return ['values' => $values];
    }

    /**
     * Renders with additional context values for the rendered block (not for blocks nested in it, which receive
     * them through their ancestors).
     *
     * @param array<string, mixed> $values
     * @param Closure(): string    $render
     */
    private static function withContext(array $values, Closure $render): string
    {
        if ($values === []) {
            return $render();
        }
        $filter = static fn(array $context, array $parsedBlock, ?WP_Block $parent = null): array => $parent === null ? [...$context, ...$values] : $context;
        add_filter('render_block_context', $filter, 10, 3);
        try {
            return $render();
        } finally {
            remove_filter('render_block_context', $filter, 10);
        }
    }

    /**
     * @param array<string, mixed>|null $data
     * @return array{error: array{code: string, message: string, data?: array<string, mixed>}}
     */
    private static function error(string $code, string $message, ?array $data = null): array
    {
        $error = ['code' => $code, 'message' => $message];
        if ($data !== null) {
            $error['data'] = $data;
        }

        return ['error' => $error];
    }
}
