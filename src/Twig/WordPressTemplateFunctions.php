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
     * An image from the media library as an `img` element, as returned by
     * `wp_get_attachment_image()`. The output is HTML and is printed
     * unescaped. Returns `''` if the ID is null, the attachment does not
     * exist or has no image.
     *
     * ```twig
     * {{ wp_attachment_image(attrs.imageId, 'large', { class: 'rounded' }) }}
     * ```
     *
     * @param int|null              $id         ID of the attachment, e.g. from an `#[Image]` attribute.
     * @param string                $size       Registered image size, e.g. `thumbnail`, `medium`, `large` or `full`.
     * @param array<string, string> $attributes Additional attributes of the img element.
     */
    #[TemplateFunction('wp_attachment_image', html: true)]
    public function attachmentImage(?int $id, string $size = 'full', array $attributes = []): string
    {
        return $id === null ? '' : wp_get_attachment_image($id, $size, false, $attributes);
    }

    /**
     * The URL of an image from the media library in the given size, as
     * returned by `wp_get_attachment_image_url()`. Returns `''` if the ID is
     * null, the attachment does not exist or has no image.
     *
     * ```twig
     * <meta property="og:image" content="{{ wp_attachment_image_url(attrs.imageId, 'large') }}">
     * ```
     *
     * @param int|null $id   ID of the attachment.
     * @param string   $size Registered image size, e.g. `thumbnail`, `medium`, `large` or `full`.
     */
    #[TemplateFunction('wp_attachment_image_url')]
    public function attachmentImageUrl(?int $id, string $size = 'full'): string
    {
        return $id === null ? '' : (string) wp_get_attachment_image_url($id, $size);
    }

    /**
     * The URL of the file of an attachment (any type, e.g. a PDF), as
     * returned by `wp_get_attachment_url()`. Returns `''` if the ID is null
     * or the attachment does not exist.
     *
     * ```twig
     * <a href="{{ wp_attachment_url(attrs.fileId) }}" download>Download</a>
     * ```
     *
     * @param int|null $id ID of the attachment.
     */
    #[TemplateFunction('wp_attachment_url')]
    public function attachmentUrl(?int $id): string
    {
        return $id === null ? '' : (string) wp_get_attachment_url($id);
    }

    /**
     * The caption of an attachment, as returned by `wp_get_attachment_caption()`.
     * The caption is returned as stored: it may contain HTML, which WordPress
     * filters with kses when a user without the `unfiltered_html` capability
     * saves it (like the caption of the core image block). The output is HTML
     * and is printed unescaped. Returns `''` if the ID is null or the
     * attachment does not exist.
     *
     * ```twig
     * <figcaption>{{ wp_attachment_caption(attrs.imageId) }}</figcaption>
     * ```
     *
     * @param int|null $id ID of the attachment.
     */
    #[TemplateFunction('wp_attachment_caption', html: true)]
    public function attachmentCaption(?int $id): string
    {
        return $id === null ? '' : (string) wp_get_attachment_caption($id);
    }

    /**
     * The alternative text of an image from the media library (the meta
     * field `_wp_attachment_image_alt`), as text. Returns `''` if the ID is
     * null, the attachment does not exist or has no alternative text.
     *
     * ```twig
     * <img src="{{ wp_attachment_image_url(attrs.imageId) }}" alt="{{ wp_attachment_alt(attrs.imageId) }}">
     * ```
     *
     * @param int|null $id ID of the attachment.
     */
    #[TemplateFunction('wp_attachment_alt')]
    public function attachmentAlt(?int $id): string
    {
        $alt = $id === null ? '' : get_post_meta($id, '_wp_attachment_image_alt', true);

        return is_string($alt) ? $alt : '';
    }

    /**
     * The URL of a post, page or entry of another post type, as returned by
     * `get_permalink()`. Returns `''` if the ID is null, the post does not
     * exist or is not publicly viewable (except when the block editor renders
     * the block).
     *
     * ```twig
     * <a href="{{ wp_post_url(attrs.target) }}">{{ wp_post_title(attrs.target) }}</a>
     * ```
     *
     * @param int|null $id ID of the post, e.g. from a reference attribute.
     */
    #[TemplateFunction('wp_post_url')]
    public function postUrl(?int $id): string
    {
        $post = $this->post($id);

        return $post === null ? '' : (string) get_permalink($post);
    }

    /**
     * The title of a post, page or entry of another post type (its
     * `post_title`), as text. Returns `''` if the ID is null, the post does
     * not exist or is not publicly viewable (except when the block editor
     * renders the block).
     *
     * ```twig
     * <h2>{{ wp_post_title(attrs.target) }}</h2>
     * ```
     *
     * @param int|null $id ID of the post, e.g. from a reference attribute.
     */
    #[TemplateFunction('wp_post_title')]
    public function postTitle(?int $id): string
    {
        return $this->post($id)->post_title ?? '';
    }

    /**
     * The excerpt of a post as plain text, from `get_the_excerpt()`: a manual
     * excerpt or one generated from the content, with HTML tags removed and
     * entities decoded. Returns `''` if the ID is null, the post does not
     * exist or is not publicly viewable (except when the block editor renders
     * the block).
     *
     * ```twig
     * <p>{{ wp_post_excerpt(attrs.target) }}</p>
     * ```
     *
     * @param int|null $id ID of the post, e.g. from a reference attribute.
     */
    #[TemplateFunction('wp_post_excerpt')]
    public function postExcerpt(?int $id): string
    {
        $post = $this->post($id);

        return $post === null ? '' : html_entity_decode(wp_strip_all_tags(get_the_excerpt($post)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * The publication date of a post, formatted as returned by
     * `get_the_date()`, as text. Returns `''` if the ID is null, the post
     * does not exist or is not publicly viewable (except when the block
     * editor renders the block).
     *
     * ```twig
     * <time>{{ wp_post_date(attrs.target, 'j. F Y') }}</time>
     * ```
     *
     * @param int|null $id     ID of the post, e.g. from a reference attribute.
     * @param string   $format PHP date format; `''` uses the date format of the site.
     */
    #[TemplateFunction('wp_post_date')]
    public function postDate(?int $id, string $format = ''): string
    {
        $post = $this->post($id);
        $date = $post === null ? false : get_the_date($format, $post);

        return $date === false ? '' : (string) $date;
    }

    /**
     * The featured image of a post as an `img` element, as returned by
     * `get_the_post_thumbnail()`. The output is HTML and is printed unescaped.
     * Returns `''` if the ID is null, the post has no featured image, does
     * not exist or is not publicly viewable (except when the block editor
     * renders the block).
     *
     * ```twig
     * {{ wp_post_thumbnail(attrs.target, 'medium', { class: 'teaser-image' }) }}
     * ```
     *
     * @param int|null              $id         ID of the post, e.g. from a reference attribute.
     * @param string                $size       Registered image size, e.g. `thumbnail`, `medium`, `large` or `full`.
     * @param array<string, string> $attributes Additional attributes of the img element.
     */
    #[TemplateFunction('wp_post_thumbnail', html: true)]
    public function postThumbnail(?int $id, string $size = 'post-thumbnail', array $attributes = []): string
    {
        $post = $this->post($id);

        return $post === null ? '' : get_the_post_thumbnail($post, $size, $attributes);
    }

    /**
     * The URL of the archive of a term (category, tag or term of another
     * taxonomy), as returned by `get_term_link()`. Returns `''` if the ID is
     * null or the term does not exist.
     *
     * ```twig
     * <a href="{{ wp_term_url(attrs.category) }}">{{ wp_term_name(attrs.category) }}</a>
     * ```
     *
     * @param int|null $id ID of the term.
     */
    #[TemplateFunction('wp_term_url')]
    public function termUrl(?int $id): string
    {
        $term = self::term($id);
        $link = $term === null ? '' : get_term_link($term);

        return is_string($link) ? $link : '';
    }

    /**
     * The name of a term (category, tag or term of another taxonomy), as
     * text. Returns `''` if the ID is null or the term does not exist.
     *
     * ```twig
     * <span class="category">{{ wp_term_name(attrs.category) }}</span>
     * ```
     *
     * @param int|null $id ID of the term.
     */
    #[TemplateFunction('wp_term_name')]
    public function termName(?int $id): string
    {
        // WordPress stores term names with HTML special characters escaped (pre_term_name).
        return wp_specialchars_decode(self::term($id)->name ?? '', ENT_QUOTES);
    }

    /**
     * The display name of a user, as text. Returns `''` if the ID is null or
     * the user does not exist.
     *
     * ```twig
     * <span class="author">{{ wp_user_name(attrs.author) }}</span>
     * ```
     *
     * @param int|null $id ID of the user.
     */
    #[TemplateFunction('wp_user_name')]
    public function userName(?int $id): string
    {
        // Stored escaped like term names (pre_user_display_name).
        return wp_specialchars_decode(self::user($id)->display_name ?? '', ENT_QUOTES);
    }

    /**
     * The URL of the author archive of a user, as returned by
     * `get_author_posts_url()`. Returns `''` if the ID is null or the user
     * does not exist.
     *
     * ```twig
     * <a href="{{ wp_user_url(attrs.author) }}">{{ wp_user_name(attrs.author) }}</a>
     * ```
     *
     * @param int|null $id ID of the user.
     */
    #[TemplateFunction('wp_user_url')]
    public function userUrl(?int $id): string
    {
        $user = self::user($id);

        return $user === null ? '' : get_author_posts_url($user->ID);
    }

    /**
     * The avatar of a user as an `img` element, as returned by `get_avatar()`.
     * The output is HTML and is printed unescaped. Returns `''` if the ID is
     * null, the user does not exist or avatars are turned off.
     *
     * ```twig
     * {{ wp_avatar(attrs.author, 48) }}
     * ```
     *
     * @param int|null $id   ID of the user.
     * @param int      $size Width and height of the avatar in pixels.
     */
    #[TemplateFunction('wp_avatar', html: true)]
    public function avatar(?int $id, int $size = 96): string
    {
        $user = self::user($id);

        return $user === null ? '' : (string) get_avatar($user->ID, $size);
    }

    /**
     * The URL of the front page of the site, or of a path below it, as
     * returned by `home_url()`.
     *
     * ```twig
     * <a href="{{ wp_home_url() }}">{{ wp_site_name() }}</a>
     * <a href="{{ wp_home_url('/contact/') }}">Contact</a>
     * ```
     *
     * @param string $path Path relative to the home URL, e.g. `/contact/`; `''` for the home URL itself.
     */
    #[TemplateFunction('wp_home_url')]
    public function homeUrl(string $path = ''): string
    {
        return home_url($path);
    }

    /**
     * The name of the site (`get_bloginfo('name')`), as text.
     *
     * ```twig
     * <span class="logo">{{ wp_site_name() }}</span>
     * ```
     */
    #[TemplateFunction('wp_site_name')]
    public function siteName(): string
    {
        // Stored with esc_html() (sanitize_option).
        return wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
    }

    /**
     * The tagline of the site (`get_bloginfo('description')`), as text.
     *
     * ```twig
     * <p class="tagline">{{ wp_site_description() }}</p>
     * ```
     */
    #[TemplateFunction('wp_site_description')]
    public function siteDescription(): string
    {
        return wp_specialchars_decode(get_bloginfo('description'), ENT_QUOTES);
    }

    /**
     * The URL of the login page, as returned by `wp_login_url()`.
     *
     * ```twig
     * <a href="{{ wp_login_url(wp_post_url(attrs.target)) }}">Log in</a>
     * ```
     *
     * @param string $redirect URL to go to after logging in; `''` for the default of WordPress.
     */
    #[TemplateFunction('wp_login_url')]
    public function loginUrl(string $redirect = ''): string
    {
        return wp_login_url($redirect);
    }

    /**
     * The URL that logs the current user out, as returned by
     * `wp_logout_url()`. It contains a nonce of the user's session, so a
     * block with `#[Cache]` cannot use it: the call throws an error there.
     *
     * ```twig
     * <a href="{{ wp_logout_url(wp_home_url()) }}">Log out</a>
     * ```
     *
     * @param string $redirect URL to go to after logging out; `''` for the default of WordPress.
     */
    #[TemplateFunction('wp_logout_url')]
    public function logoutUrl(string $redirect = ''): string
    {
        $this->context->checkCached('wp_logout_url', [], 'the session of the user (its nonce)');

        // wp_nonce_url() escapes the URL with esc_html().
        return wp_specialchars_decode(wp_logout_url($redirect), ENT_QUOTES);
    }

    /**
     * Whether the visitor is logged in, as returned by `is_user_logged_in()`.
     * In a block with `#[Cache]`, the cache must vary by `LoggedInVary`,
     * `RoleVary` or `UserVary`; otherwise the call throws an error.
     *
     * ```twig
     * {% if wp_is_user_logged_in() %}
     *     <a href="{{ wp_logout_url() }}">Log out</a>
     * {% endif %}
     * ```
     */
    #[TemplateFunction('wp_is_user_logged_in')]
    public function isUserLoggedIn(): bool
    {
        $this->context->checkCached('wp_is_user_logged_in', ['logged_in', 'role', 'user'], 'whether the visitor is logged in');

        return is_user_logged_in();
    }

    /**
     * A Unix timestamp formatted in the site's time zone and language, as
     * returned by `wp_date()`, as text. Without a timestamp it formats the
     * current time; a block with `#[Cache]` cannot do that: the call throws
     * an error there.
     *
     * ```twig
     * <time>{{ wp_date('j. F Y', attrs.timestamp) }}</time>
     * ```
     *
     * @param string   $format    PHP date format, e.g. `j. F Y`.
     * @param int|null $timestamp Unix timestamp; null for now.
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
     * The difference between two Unix timestamps in words, e.g. "5 minutes",
     * as returned by `human_time_diff()`, as text. Without a second timestamp
     * it measures to the current time; a block with `#[Cache]` cannot do
     * that: the call throws an error there.
     *
     * ```twig
     * {{ wp_human_time_diff(attrs.start, attrs.end) }}
     * ```
     *
     * @param int $from Unix timestamp to start from.
     * @param int $to   Unix timestamp to end at; 0 for now.
     */
    #[TemplateFunction('wp_human_time_diff')]
    public function humanTimeDiff(int $from, int $to = 0): string
    {
        if ($to === 0) {
            $this->context->checkCached('wp_human_time_diff', [], 'the current time without a second timestamp');
        }

        return human_time_diff($from, $to);
    }

    /**
     * A number formatted with the thousands separator and decimal point of
     * the site's language, as returned by `number_format_i18n()`, as text.
     *
     * ```twig
     * {{ wp_number_format(attrs.price, 2) }}
     * ```
     *
     * @param float $number   Number to format.
     * @param int   $decimals Number of decimal places.
     */
    #[TemplateFunction('wp_number_format')]
    public function numberFormat(float $number, int $decimals = 0): string
    {
        return number_format_i18n($number, $decimals);
    }

    /**
     * A size in bytes as a readable size with unit, e.g. "2 KB", as returned
     * by `size_format()`, as text. Returns `''` if WordPress cannot format
     * the size, e.g. a negative one.
     *
     * ```twig
     * {{ wp_size_format(attrs.fileSize, 1) }}
     * ```
     *
     * @param int $bytes    Size in bytes.
     * @param int $decimals Number of decimal places.
     */
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
