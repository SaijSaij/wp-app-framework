<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Post;

use Saij\WpAppFramework\Editor\RenderContext;
use Saij\WpAppFramework\Twig\TemplateFunction;
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
     * The post object, null if the post does not exist, its type has no post class of this theme or plugin, or it
     * is not published (renders for the block editor show posts in every status).
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
