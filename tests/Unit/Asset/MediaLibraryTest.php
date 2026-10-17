<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Unit\Asset;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use WebCodingCat\WpAppFramework\Asset\AssetCollector;
use WebCodingCat\WpAppFramework\Asset\Assets;
use WebCodingCat\WpAppFramework\Asset\MediaLibrary;
use WebCodingCat\WpAppFramework\Tests\Support\Files;

final class MediaLibraryTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/wpaf-media-' . bin2hex(random_bytes(4));
        mkdir($this->root . '/assets/images', 0777, true);
        mkdir($this->root . '/uploads');
        file_put_contents($this->root . '/assets/images/hero.jpg', 'first');
        $GLOBALS['wp_app_framework_test_uploads'] = $this->root . '/uploads';
    }

    protected function tearDown(): void
    {
        Files::remove($this->root);
        unset($GLOBALS['wp_app_framework_test_uploads'], $GLOBALS['wp_app_framework_test_attachments'], $GLOBALS['wp_app_framework_test_meta']);
    }

    private function library(): MediaLibrary
    {
        $assets = new Assets('my-theme', $this->root . '/assets', 'https://example.test/assets', AssetCollector::collect($this->root . '/assets'), '@theme/');

        return new MediaLibrary('my-theme', $assets);
    }

    public function testImportsAnAssetOncePerContent(): void
    {
        $id = $this->library()->import('images/hero.jpg');
        $hash = AssetCollector::collect($this->root . '/assets')['images/hero.jpg']['hash'];

        self::assertSame(1000, $id);
        self::assertSame('first', file_get_contents($this->root . '/uploads/hero.jpg'));
        /** @var array<int, array{args: array<string, mixed>, file: string, metadata: array<string, mixed>}> $attachments */
        $attachments = $GLOBALS['wp_app_framework_test_attachments'];
        $attachment = $attachments[1000];
        self::assertSame(['post_mime_type' => 'image/jpeg', 'post_title' => 'hero', 'post_content' => '', 'post_status' => 'inherit'], $attachment['args']);
        self::assertSame(['file' => 'hero.jpg'], $attachment['metadata']);
        self::assertSame('my-theme:images/hero.jpg:' . $hash, get_post_meta(1000, MediaLibrary::META_KEY, true));

        self::assertSame(1000, $this->library()->import('images/hero.jpg'), 'The same content: the same attachment.');
        self::assertSame(1000, $this->library()->import('@theme/images/hero.jpg'), 'Also by its exact name.');

        file_put_contents($this->root . '/assets/images/hero.jpg', 'second');
        self::assertSame(1001, $this->library()->import('images/hero.jpg'), 'Changed content: a new attachment.');
    }

    public function testFilesOfTheChildThemeCannotBeImported(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Only assets of my-theme itself can be imported, not @child/team.jpg of the child theme.');

        $this->library()->import('@child/team.jpg');
    }
}
