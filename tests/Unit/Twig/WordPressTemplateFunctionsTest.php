<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Unit\Twig;

use PHPUnit\Framework\TestCase;
use WebCodingCat\WpAppFramework\Editor\RenderContext;
use WebCodingCat\WpAppFramework\Twig\WordPressTemplateFunctions;
use WP_Post;
use WP_Term;
use WP_User;

final class WordPressTemplateFunctionsTest extends TestCase
{
    private RenderContext $context;

    private WordPressTemplateFunctions $functions;

    protected function setUp(): void
    {
        $GLOBALS['wp_app_framework_test_posts'] = [
            1 => new WP_Post((object) ['ID' => 1, 'post_type' => 'page', 'post_title' => 'About & us', 'post_date' => '2026-10-11 09:30:00']),
            3 => new WP_Post((object) ['ID' => 3, 'post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'Draft', 'post_excerpt' => 'Hidden']),
            5 => new WP_Post((object) ['ID' => 5, 'post_type' => 'post', 'post_excerpt' => '<p>Fish &amp; <em>chips</em> [&hellip;]</p>']),
            7 => new WP_Post((object) ['ID' => 7, 'post_type' => 'attachment', 'post_status' => 'inherit', 'post_excerpt' => 'A <a href="https://example.test">link</a>']),
        ];
        $GLOBALS['wp_app_framework_test_terms'] = [10 => new WP_Term((object) ['term_id' => 10, 'name' => 'Food &amp; drink'])];
        $GLOBALS['wp_app_framework_test_users'] = [20 => new WP_User((object) ['ID' => 20, 'display_name' => 'Ann &amp; Bob'])];
        $GLOBALS['wp_app_framework_test_meta'] = [7 => ['_wp_attachment_image_alt' => 'Alt & text']];
        $GLOBALS['wp_app_framework_test_options'] = ['blogname' => 'Tom &amp; Jerry', 'blogdescription' => 'Cats &#039;n&#039; mice'];

        $this->context = new RenderContext();
        $this->functions = new WordPressTemplateFunctions($this->context);
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['wp_app_framework_test_posts'],
            $GLOBALS['wp_app_framework_test_terms'],
            $GLOBALS['wp_app_framework_test_users'],
            $GLOBALS['wp_app_framework_test_meta'],
            $GLOBALS['wp_app_framework_test_options'],
            $GLOBALS['wp_app_framework_test_logged_in'],
        );
    }

    public function testAttachments(): void
    {
        $functions = $this->functions;

        self::assertSame('<img data-id="7" data-size="full">', $functions->attachmentImage(7));
        self::assertSame('<img data-id="7" data-size="thumbnail">', $functions->attachmentImage(7, 'thumbnail'));
        self::assertSame('https://example.test/7-full.jpg', $functions->attachmentImageUrl(7));
        self::assertSame('https://example.test/7.jpg', $functions->attachmentUrl(7));
        // The caption keeps its HTML; the alternative text is plain text.
        self::assertSame('A <a href="https://example.test">link</a>', $functions->attachmentCaption(7));
        self::assertSame('Alt & text', $functions->attachmentAlt(7));

        foreach ([null, 99] as $id) {
            self::assertSame('', $functions->attachmentImageUrl($id));
            self::assertSame('', $functions->attachmentUrl($id));
            self::assertSame('', $functions->attachmentCaption($id));
            self::assertSame('', $functions->attachmentAlt($id));
        }
        self::assertSame('', $functions->attachmentImage(null));
        // Not an attachment: wp_get_attachment_caption() returns false.
        self::assertSame('', $functions->attachmentCaption(1));
    }

    public function testPosts(): void
    {
        $functions = $this->functions;

        self::assertSame('https://example.test/?p=1', $functions->postUrl(1));
        self::assertSame('About & us', $functions->postTitle(1));
        // Plain text: tags removed, entities decoded (Twig escapes it).
        self::assertSame('Fish & chips […]', $functions->postExcerpt(5));
        self::assertSame('2026-10-11', $functions->postDate(1));
        self::assertSame('11.10.2026 09:30', $functions->postDate(1, 'd.m.Y H:i'));
        self::assertSame('<img data-post="1" data-size="post-thumbnail">', $functions->postThumbnail(1));
        self::assertSame('<img data-post="1" data-size="large">', $functions->postThumbnail(1, 'large'));

        // Missing and not public posts.
        foreach ([null, 99, 3] as $id) {
            self::assertSame('', $functions->postUrl($id));
            self::assertSame('', $functions->postTitle($id));
            self::assertSame('', $functions->postExcerpt($id));
            self::assertSame('', $functions->postDate($id));
            self::assertSame('', $functions->postThumbnail($id));
        }

        // Renders for the block editor keep posts that are not public.
        self::assertSame('Draft|Hidden', $this->context->renderForEditor(static fn(): string => $functions->postTitle(3) . '|' . $functions->postExcerpt(3)));
    }

    public function testTermsAndUsers(): void
    {
        $functions = $this->functions;

        // Stored escaped by WordPress, decoded for Twig's escaping.
        self::assertSame('Food & drink', $functions->termName(10));
        self::assertSame('https://example.test/?cat=10', $functions->termUrl(10));
        self::assertSame('Ann & Bob', $functions->userName(20));
        self::assertSame('https://example.test/?author=20', $functions->userUrl(20));
        self::assertSame('<img class="avatar" data-user="20" width="48">', $functions->avatar(20, 48));

        foreach ([null, 99] as $id) {
            self::assertSame('', $functions->termName($id));
            self::assertSame('', $functions->termUrl($id));
            self::assertSame('', $functions->userName($id));
            self::assertSame('', $functions->userUrl($id));
            self::assertSame('', $functions->avatar($id));
        }

        $GLOBALS['wp_app_framework_test_options'] = ['show_avatars' => false];
        self::assertSame('', $functions->avatar(20));
    }

    public function testSiteAndUrls(): void
    {
        $functions = $this->functions;

        self::assertSame('https://example.test/', $functions->homeUrl());
        self::assertSame('https://example.test/shop', $functions->homeUrl('/shop'));
        // Stored with esc_html().
        self::assertSame('Tom & Jerry', $functions->siteName());
        self::assertSame("Cats 'n' mice", $functions->siteDescription());
        self::assertSame('https://example.test/wp-login.php?redirect_to=%2Fshop', $functions->loginUrl('/shop'));
        // wp_logout_url() returns the URL escaped.
        self::assertSame('https://example.test/wp-login.php?action=logout&_wpnonce=nonce', $functions->logoutUrl());

        self::assertFalse($functions->isUserLoggedIn());
        $GLOBALS['wp_app_framework_test_logged_in'] = true;
        self::assertTrue($functions->isUserLoggedIn());
    }

    public function testFormatting(): void
    {
        $functions = $this->functions;

        self::assertSame('2026-10-11', $functions->date('Y-m-d', 1_791_712_800));
        self::assertSame('', $functions->date('fail', 0));
        self::assertSame('5 minutes', $functions->humanTimeDiff(1000, 1300));
        self::assertSame('1.234,57', $functions->numberFormat(1234.567, 2));
        self::assertSame('2 KB', $functions->sizeFormat(2048));
        self::assertSame('', $functions->sizeFormat(-1));
    }
}
