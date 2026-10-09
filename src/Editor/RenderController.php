<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Editor;

use Saij\WpAppFramework\DependencyInjection\NoService;
use Throwable;
use WP_Block_Type_Registry;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Batch render route of one framework instance for the block editor:
 * POST /wp-app-framework/v1/{slug}/render with `{blocks: [{name, attributes, postId?}]}`.
 *
 * Renders like WordPress' block renderer (render_block() with empty inner
 * blocks, so block supports apply), but many blocks per request and with the
 * editor flag of this instance set. Each entry gets its own result – `{html}`
 * or `{error: {code, message}}` – so one failing block does not fail the batch.
 *
 * @internal
 */
#[NoService]
final readonly class RenderController
{
    public const string NAMESPACE = 'wp-app-framework/v1';

    /** Maximum number of blocks per request. */
    public const int MAX_BLOCKS = 100;

    /**
     * @param string       $slug   Slug of the framework instance (the theme's template).
     * @param list<string> $blocks Names of the blocks of this instance; the route renders only these.
     */
    public function __construct(
        private string $slug,
        private array $blocks,
        private RenderContext $context,
        private bool $debug,
    ) {
    }

    public static function route(string $slug): string
    {
        return '/' . $slug . '/render';
    }

    public function register(): void
    {
        register_rest_route(self::NAMESPACE, self::route($this->slug), [
            'methods' => 'POST',
            'permission_callback' => static fn (): bool => current_user_can('edit_posts'),
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
                        ],
                        'additionalProperties' => false,
                    ],
                ],
            ],
        ]);
    }

    public function handle(WP_REST_Request $request): WP_REST_Response
    {
        $results = [];
        foreach ((array) $request->get_param('blocks') as $entry) {
            $results[] = $this->renderEntry((array) $entry);
        }

        return new WP_REST_Response(['results' => $results]);
    }

    /**
     * @param array<string, mixed> $entry
     * @return array{html: string}|array{error: array{code: string, message: string, data?: array<string, mixed>}}
     */
    private function renderEntry(array $entry): array
    {
        $name = (string) ($entry['name'] ?? '');
        $postId = isset($entry['postId']) ? (int) $entry['postId'] : null;
        $attributes = (array) ($entry['attributes'] ?? []);

        $blockType = in_array($name, $this->blocks, true) ? WP_Block_Type_Registry::get_instance()->get_registered($name) : null;
        if ($blockType === null) {
            return self::error('wp_app_framework_unknown_block', sprintf('Block %s is not a block of %s.', $name, $this->slug));
        }

        $post = null;
        if ($postId !== null) {
            $post = get_post($postId);
            if ($post === null) {
                return self::error('wp_app_framework_post_not_found', sprintf('Post %d does not exist.', $postId));
            }
            if (!current_user_can('edit_post', $postId)) {
                return self::error('wp_app_framework_forbidden', sprintf('You may not edit post %d.', $postId));
            }
        }

        $schema = ['type' => 'object', 'properties' => $blockType->get_attributes(), 'additionalProperties' => false];
        $valid = rest_validate_value_from_schema($attributes, $schema, 'attributes');
        if ($valid instanceof WP_Error) {
            return self::error('wp_app_framework_invalid_attributes', $valid->get_error_message());
        }
        /** @var array<string, mixed> $attributes */
        $attributes = rest_sanitize_value_from_schema($attributes, $schema, 'attributes');

        $hadPost = array_key_exists('post', $GLOBALS);
        $previousPost = $GLOBALS['post'] ?? null;
        try {
            if ($post !== null) {
                $GLOBALS['post'] = $post;
                setup_postdata($post);
            }

            return ['html' => $this->context->renderForEditor(static fn (): string => render_block([
                'blockName' => $name,
                'attrs' => $attributes,
                'innerBlocks' => [],
                'innerHTML' => '',
                'innerContent' => [],
            ]))];
        } catch (Throwable $exception) {
            error_log(sprintf(
                'wp-app-framework: editor render of block %s failed: %s: %s in %s:%d',
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
                : self::error('wp_app_framework_render_failed', sprintf('Block %s could not be rendered.', $name));
        } finally {
            if ($post !== null) {
                if ($hadPost) {
                    $GLOBALS['post'] = $previousPost;
                } else {
                    unset($GLOBALS['post']);
                }
                if ($previousPost !== null) {
                    setup_postdata($previousPost);
                }
            }
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
