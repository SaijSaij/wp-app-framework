<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Value;

use Saij\WpAppFramework\Editor\RenderContext;
use Saij\WpAppFramework\Twig\TemplateFunction;
use WP_Post;
use WP_Term;
use WP_User;

/**
 * Template functions for the IDs of references (`#[PostReference]`,
 * `#[TermReference]`, `#[UserReference]`): titles, names and URLs as text
 * (escaped by Twig). They return '' for null and for objects that do not
 * exist; posts that are not publicly viewable count as missing, except in
 * renders for the block editor.
 */
final readonly class ReferenceTemplateFunctions
{
    public function __construct(private RenderContext $context) {}

    #[TemplateFunction('post_url')]
    public function postUrl(?int $id): string
    {
        $post = $this->post($id);

        return $post === null ? '' : (string) get_permalink($post);
    }

    #[TemplateFunction('post_title')]
    public function postTitle(?int $id): string
    {
        return $this->post($id)->post_title ?? '';
    }

    #[TemplateFunction('term_url')]
    public function termUrl(?int $id): string
    {
        $term = self::term($id);
        $link = $term === null ? '' : get_term_link($term);

        return is_string($link) ? $link : '';
    }

    #[TemplateFunction('term_name')]
    public function termName(?int $id): string
    {
        // WordPress stores term names with HTML special characters escaped (pre_term_name).
        return wp_specialchars_decode(self::term($id)->name ?? '', ENT_QUOTES);
    }

    #[TemplateFunction('user_name')]
    public function userName(?int $id): string
    {
        $user = $id === null ? false : get_userdata($id);

        // Stored escaped like term names (pre_user_display_name).
        return $user instanceof WP_User ? wp_specialchars_decode($user->display_name, ENT_QUOTES) : '';
    }

    private function post(?int $id): ?WP_Post
    {
        $post = $id === null ? null : get_post($id);
        if (!$post instanceof WP_Post || (!$this->context->isEditor() && !is_post_publicly_viewable($post))) {
            return null;
        }

        return $post;
    }

    private static function term(?int $id): ?WP_Term
    {
        $term = $id === null ? null : get_term($id);

        return $term instanceof WP_Term ? $term : null;
    }
}
