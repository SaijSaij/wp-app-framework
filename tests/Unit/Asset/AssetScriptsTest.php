<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Unit\Asset;

use PHPUnit\Framework\TestCase;
use Saij\WpAppFramework\Asset\AssetScripts;
use Saij\WpAppFramework\Asset\ScriptKind;
use Saij\WpAppFramework\Tests\Support\Files;

/**
 * Document views, editor scripts and shared modules in the asset directory during the build.
 */
final class AssetScriptsTest extends TestCase
{
    private string $assets;

    protected function setUp(): void
    {
        $this->assets = sys_get_temp_dir() . '/wpaf-asset-scripts-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        if (is_dir($this->assets)) {
            Files::remove($this->assets);
        }
    }

    private function write(string ...$files): void
    {
        foreach ($files as $file) {
            if (!is_dir(dirname($this->assets . '/' . $file))) {
                mkdir(dirname($this->assets . '/' . $file), 0777, true);
            }
            touch($this->assets . '/' . $file);
        }
    }

    public function testFindsDocumentViewsEditorScriptsAndSharedModules(): void
    {
        $this->write(
            'styles.css',
            'menu.view.js',
            'nav/sticky.view.js',
            'editor/highlight.editor.js',
            'shared/counter.js',
            'shared/ui/Modal.js',
            'shared/ui/panel.view.js',
            'helpers/format.js',
            'images/logo.svg',
        );

        $collector = new AssetScripts('my-theme');
        self::assertSame([
            'documentViews' => ['assets/menu.view', 'assets/nav/sticky.view', 'assets/shared/ui/panel.view'],
            'editorScripts' => ['assets/editor/highlight.editor'],
            'shared' => ['counter' => 'assets/shared/counter', 'ui/Modal' => 'assets/shared/ui/Modal'],
        ], $collector->collect($this->assets));
        self::assertSame([], $collector->errors());
    }

    public function testWithoutAssetDirectoryThereAreNoScripts(): void
    {
        $empty = ['documentViews' => [], 'editorScripts' => [], 'shared' => []];
        self::assertSame($empty, new AssetScripts('my-theme')->collect(null));
        self::assertSame($empty, new AssetScripts('my-theme')->collect($this->assets));
    }

    public function testScriptsWithTheSameHandleAreErrors(): void
    {
        $this->write('nav/menu.view.js', 'nav-menu.view.js', 'shared/ui/modal.js', 'shared/UiModal.js');

        $collector = new AssetScripts('my-theme');
        $collector->collect($this->assets);
        self::assertSame([
            'The scripts assets/nav-menu.view.js and assets/nav/menu.view.js get the same script handle my-theme-document-nav-menu-view; rename one of them.',
            'The scripts assets/shared/UiModal.js and assets/shared/ui/modal.js get the same script handle my-theme-shared-ui-modal; rename one of them.',
        ], $collector->errors());
    }

    public function testHandles(): void
    {
        self::assertSame('my-theme-document-nav-mobile-menu-view', ScriptKind::View->documentHandle('my-theme', 'assets/nav/mobileMenu.view'));
        self::assertSame('my-theme-document-format-editor', ScriptKind::Editor->documentHandle('my-theme', 'assets/format.editor'));
        self::assertSame('my-theme-shared-ui-modal-stack', ScriptKind::sharedHandle('my-theme', 'ui/ModalStack'));
    }
}
