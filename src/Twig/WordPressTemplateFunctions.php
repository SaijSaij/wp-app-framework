<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Twig;

use Saij\WpAppFramework\Editor\RenderContext;
use WP_Post;
use WP_Term;
use WP_User;

/**
 * Template functions for WordPress functions, named `wp_` + object + property
 * without `get_`/`the_` (`wp_` only once: `wp_date` keeps its name).
 *
 * They return '' for null IDs and for objects that do not exist; posts that
 * are not publicly viewable count as missing, except in renders for the block
 * editor. Text is escaped by Twig; values WordPress stores or returns escaped
 * are decoded first, so they are not escaped twice.
 */
final readonly class WordPressTemplateFunctions
{
    public function __construct(private RenderContext $context) {}

    /**
     * An image from the media library, as returned by wp_get_attachment_image().
     *
     * @param array<string, string> $attributes Additional attributes of the img element.
     */
    #[TemplateFunction('wp_attachment_image', html: true)]
    public function attachmentImage(?int $id, string $size = 'full', array $attributes = []): string
    {
        return $id === null ? '' : wp_get_attachment_image($id, $size, false, $attributes);
    }

    #[TemplateFunction('wp_attachment_image_url')]
    public function attachmentImageUrl(?int $id, string $size = 'full'): string
    {
        return $id === null ? '' : (string) wp_get_attachment_image_url($id, $size);
    }

    #[TemplateFunction('wp_attachment_url')]
    public function attachmentUrl(?int $id): string
    {
        return $id === null ? '' : (string) wp_get_attachment_url($id);
    }

    /**
     * The caption as stored: it may contain HTML, which WordPress filters with
     * kses when saving (like the caption of the core image block).
     */
    #[TemplateFunction('wp_attachment_caption', html: true)]
    public function attachmentCaption(?int $id): string
    {
        return $id === null ? '' : (string) wp_get_attachment_caption($id);
    }

    #[TemplateFunction('wp_attachment_alt')]
    public function attachmentAlt(?int $id): string
    {
        $alt = $id === null ? '' : get_post_meta($id, '_wp_attachment_image_alt', true);

        return is_string($alt) ? $alt : '';
    }

    #[TemplateFunction('wp_post_url')]
    public function postUrl(?int $id): string
    {
        $post = $this->post($id);

        return $post === null ? '' : (string) get_permalink($post);
    }

    #[TemplateFunction('wp_post_title')]
    public function postTitle(?int $id): string
    {
        return $this->post($id)->post_title ?? '';
    }

    /**
     * The excerpt as plain text: get_the_excerpt() returns a manual excerpt
     * as stored (possibly with HTML) and an automatic one with entities.
     */
    #[TemplateFunction('wp_post_excerpt')]
    public function postExcerpt(?int $id): string
    {
        $post = $this->post($id);

        return $post === null ? '' : html_entity_decode(wp_strip_all_tags(get_the_excerpt($post)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * @param string $format PHP date format; '' uses the date format of the site.
     */
    #[TemplateFunction('wp_post_date')]
    public function postDate(?int $id, string $format = ''): string
    {
        $post = $this->post($id);
        $date = $post === null ? false : get_the_date($format, $post);

        return $date === false ? '' : (string) $date;
    }

    /**
     * The featured image, as returned by get_the_post_thumbnail().
     *
     * @param array<string, string> $attributes Additional attributes of the img element.
     */
    #[TemplateFunction('wp_post_thumbnail', html: true)]
    public function postThumbnail(?int $id, string $size = 'post-thumbnail', array $attributes = []): string
    {
        $post = $this->post($id);

        return $post === null ? '' : get_the_post_thumbnail($post, $size, $attributes);
    }

    #[TemplateFunction('wp_term_url')]
    public function termUrl(?int $id): string
    {
        $term = self::term($id);
        $link = $term === null ? '' : get_term_link($term);

        return is_string($link) ? $link : '';
    }

    #[TemplateFunction('wp_term_name')]
    public function termName(?int $id): string
    {
        // WordPress stores term names with HTML special characters escaped (pre_term_name).
        return wp_specialchars_decode(self::term($id)->name ?? '', ENT_QUOTES);
    }

    #[TemplateFunction('wp_user_name')]
    public function userName(?int $id): string
    {
        // Stored escaped like term names (pre_user_display_name).
        return wp_specialchars_decode(self::user($id)->display_name ?? '', ENT_QUOTES);
    }

    /**
     * The URL of the user's author archive.
     */
    #[TemplateFunction('wp_user_url')]
    public function userUrl(?int $id): string
    {
        $user = self::user($id);

        return $user === null ? '' : get_author_posts_url($user->ID);
    }

    /**
     * The user's avatar, as returned by get_avatar(); empty if avatars are turned off.
     */
    #[TemplateFunction('wp_avatar', html: true)]
    public function avatar(?int $id, int $size = 96): string
    {
        $user = self::user($id);

        return $user === null ? '' : (string) get_avatar($user->ID, $size);
    }

    #[TemplateFunction('wp_home_url')]
    public function homeUrl(string $path = ''): string
    {
        return home_url($path);
    }

    #[TemplateFunction('wp_site_name')]
    public function siteName(): string
    {
        // Stored with esc_html() (sanitize_option).
        return wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
    }

    #[TemplateFunction('wp_site_description')]
    public function siteDescription(): string
    {
        return wp_specialchars_decode(get_bloginfo('description'), ENT_QUOTES);
    }

    #[TemplateFunction('wp_login_url')]
    public function loginUrl(string $redirect = ''): string
    {
        return wp_login_url($redirect);
    }

    #[TemplateFunction('wp_logout_url')]
    public function logoutUrl(string $redirect = ''): string
    {
        $this->context->checkCached('wp_logout_url', [], 'the session of the user (its nonce)');

        // wp_nonce_url() escapes the URL with esc_html().
        return wp_specialchars_decode(wp_logout_url($redirect), ENT_QUOTES);
    }

    #[TemplateFunction('wp_is_user_logged_in')]
    public function isUserLoggedIn(): bool
    {
        $this->context->checkCached('wp_is_user_logged_in', ['logged_in', 'role', 'user'], 'whether the visitor is logged in');

        return is_user_logged_in();
    }

    /**
     * A Unix timestamp in the site's time zone and language, as returned by wp_date().
     *
     * @param int|null $timestamp Null for now.
     */
    #[TemplateFunction('wp_date')]
    public function date(string $format, ?int $timestamp = null): string
    {
        if ($timestamp === null) {
            $this->context->checkCached('wp_date', [], 'the current time without a timestamp');
        }

        return (string) wp_date($format, $timestamp);
    }

    /**
     * The difference between two Unix timestamps in words, e.g. "5 minutes".
     *
     * @param int $to 0 for now.
     */
    #[TemplateFunction('wp_human_time_diff')]
    public function humanTimeDiff(int $from, int $to = 0): string
    {
        if ($to === 0) {
            $this->context->checkCached('wp_human_time_diff', [], 'the current time without a second timestamp');
        }

        return human_time_diff($from, $to);
    }

    #[TemplateFunction('wp_number_format')]
    public function numberFormat(float $number, int $decimals = 0): string
    {
        return number_format_i18n($number, $decimals);
    }

    #[TemplateFunction('wp_size_format')]
    public function sizeFormat(int $bytes, int $decimals = 0): string
    {
        return (string) size_format($bytes, $decimals);
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

    private static function user(?int $id): ?WP_User
    {
        $user = $id === null ? false : get_userdata($id);

        return $user instanceof WP_User ? $user : null;
    }
}
