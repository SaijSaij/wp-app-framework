<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Post;

use WebCodingCat\WpAppFramework\Editor\RenderContext;
use WebCodingCat\WpAppFramework\Twig\TemplateFunction;
use WP_Post;

/**
 * Post objects in templates: `post(postId)` returns the object of the post class of
 * the post's type, e.g. an Event, with getters as attributes (`post.title`,
 * `post.url`) and meta objects by name (`post.meta('details').capacity`).
 */
final readonly class PostTemplateFunctions
{
    public function __construct(
        private PostStore $store,
        private RenderContext $context,
    ) {}

    /**
     * The post object of a post, for a post type of this theme or plugin, `post` or `page`: an instance of its post
     * class (e.g. an `Event`), with getters as attributes and meta classes by their `#[PostMeta]` name. Returns null
     * if the ID is null, the post does not exist, its type has no post class of this theme or plugin, or its status
     * is not public, e.g. a draft (renders for the block editor return posts in every status). Whether the post type
     * is public does not matter, so posts of data types are returned too; the `wp_post_*` functions also check the
     * post type. The values are
     * escaped like any value; print rich text with `|raw`.
     *
     * ```twig
     * {% set event = post(postId) %}
     * {% if event %}
     *     <h2>{{ event.title }}</h2>
     *     <p>{{ event.meta('details').capacity }} seats</p>
     * {% endif %}
     * ```
     *
     * @param int|null $id ID of the post, e.g. from a reference attribute.
     */
    #[TemplateFunction('post')]
    public function post(?int $id): ?AbstractPost
    {
        $post = $id === null ? null : get_post($id);
        if (!$post instanceof WP_Post) {
            return null;
        }
        $class = $this->store->postClass($post->post_type);
        if ($class === null || (!$this->context->isEditor() && !is_post_status_viewable($post->post_status))) {
            return null;
        }

        return $class::fromWpPost($post, $this->store);
    }
}
