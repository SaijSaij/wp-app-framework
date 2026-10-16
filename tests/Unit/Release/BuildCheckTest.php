<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Unit\Release;

use Saij\WpAppFramework\Release\BuildCheck;
use Saij\WpAppFramework\Release\ReleaseException;

final class BuildCheckTest extends ReleaseTestCase
{
    /**
     * The parts of a manifest the check reads.
     *
     * @param list<array<string, mixed>> $blocks
     * @param array<string, mixed>       $meta
     * @param list<array<string, mixed>> $groups
     * @param array<string, mixed>       $scripts
     * @param array<string, mixed>       $assets
     * @return array<string, mixed>
     */
    private static function manifest(array $blocks = [], array $meta = [], array $groups = [], bool $styles = false, array $scripts = [], array $assets = []): array
    {
        return [
            'blocks' => $blocks,
            'meta' => $meta,
            'settings' => ['title' => null, 'groups' => $groups],
            'styles' => $styles,
            'stylePreload' => false,
            'scripts' => [...['documentViews' => [], 'editorScripts' => [], 'shared' => []], ...$scripts],
            'assets' => $assets,
        ];
    }

    /**
     * @param list<array<string, mixed>> $supports
     * @return array<string, mixed>
     */
    private static function block(string $name, ?string $editorScript = null, ?string $viewScript = null, array $supports = []): array
    {
        return ['name' => $name, 'editor' => ['editorScript' => $editorScript], 'viewScript' => $viewScript, 'supports' => $supports];
    }

    /**
     * @param array<string, mixed> $manifest
     * @return list<string>
     */
    private function missing(array $manifest): array
    {
        // @phpstan-ignore argument.type (only the parts of the manifest the check reads)
        return BuildCheck::missing($manifest, $this->root);
    }

    private function build(string ...$files): void
    {
        foreach ($files as $file) {
            $path = $this->root . '/build/' . $file;
            if (!is_dir(dirname($path))) {
                mkdir(dirname($path), 0777, true);
            }
            file_put_contents($path, '');
        }
    }

    public function testAProjectWithoutBlocksMetaSettingsAndStylesNeedsNoBuild(): void
    {
        self::assertSame([], $this->missing(self::manifest()));
    }

    public function testScriptsInTheAssetDirectoryNeedTheirBuildAndRuntimes(): void
    {
        $manifest = self::manifest(scripts: [
            'documentViews' => ['assets/menu.view'],
            'editorScripts' => ['assets/format.editor'],
            'shared' => ['ui/modal' => 'assets/shared/ui/modal'],
        ]);
        self::assertSame([
            'build/assets/menu.view.asset.php (document view)',
            'build/assets/format.editor.asset.php (editor script without a block)',
            'build/vendor/wp-app-framework/editor.asset.php (editor runtime (blocks, post meta or editor scripts))',
            'build/assets/shared/ui/modal.asset.php (shared module #shared/ui/modal)',
            'build/vendor/wp-app-framework/view.asset.php (view runtime (view scripts))',
        ], $this->missing($manifest));

        $this->build('assets/menu.view.asset.php', 'assets/format.editor.asset.php', 'assets/shared/ui/modal.asset.php', 'vendor/wp-app-framework/editor.asset.php', 'vendor/wp-app-framework/view.asset.php');
        self::assertSame([], $this->missing($manifest));
    }

    public function testStaticAssetsMustBeInTheRelease(): void
    {
        $manifest = self::manifest(assets: ['images/logo.svg' => ['hash' => 'a', 'width' => 1, 'height' => 1, 'variants' => null]]);
        self::assertSame(['assets/images/logo.svg (static asset, e.g. removed by export-ignore)'], $this->missing($manifest));

        mkdir($this->root . '/assets/images', 0777, true);
        touch($this->root . '/assets/images/logo.svg');
        self::assertSame([], $this->missing($manifest));
    }

    public function testBlocksNeedTheEditorRuntimeTheCanvasAndTheirScripts(): void
    {
        $support = ['class' => 'Acme\\Reveal', 'editorScript' => 'Support/Reveal.editor', 'viewScript' => 'Support/Reveal.view'];
        $manifest = self::manifest([self::block('acme/faq', 'Block/Faq/Faq.editor', 'Block/Faq/Faq.view', [$support])]);

        self::assertSame(
            [
                'build/vendor/wp-app-framework/editor.asset.php (editor runtime (blocks, post meta or editor scripts))',
                'build/vendor/wp-app-framework/canvas.asset.php (canvas styles of the editor (blocks))',
                'build/Block/Faq/Faq.editor.asset.php (editor script of block acme/faq)',
                'build/Block/Faq/Faq.view.asset.php (view script of block acme/faq)',
                'build/Support/Reveal.editor.asset.php (editor script of block support Acme\\Reveal)',
                'build/Support/Reveal.view.asset.php (view script of block support Acme\\Reveal)',
                'build/vendor/wp-app-framework/view.asset.php (view runtime (view scripts))',
            ],
            $this->missing($manifest),
        );

        $this->build(
            'vendor/wp-app-framework/editor.asset.php',
            'vendor/wp-app-framework/canvas.asset.php',
            'Block/Faq/Faq.editor.asset.php',
            'Block/Faq/Faq.view.asset.php',
            'Support/Reveal.editor.asset.php',
            'Support/Reveal.view.asset.php',
            'vendor/wp-app-framework/view.asset.php',
        );
        self::assertSame([], $this->missing($manifest));
    }

    public function testMetaNeedsTheEditorRuntimeAndTheMetaBoxes(): void
    {
        self::assertSame(
            [
                'build/vendor/wp-app-framework/editor.asset.php (editor runtime (blocks, post meta or editor scripts))',
                'build/vendor/wp-app-framework/meta-box.asset.php (meta boxes (post meta))',
            ],
            $this->missing(self::manifest(meta: ['Acme\\EventDetails' => []])),
        );
    }

    public function testSettingsNeedTheSettingsPageAndStylesTheStylesheet(): void
    {
        self::assertSame(
            [
                'build/vendor/wp-app-framework/settings.asset.php (settings page (settings groups))',
                'build/styles.css (stylesheet (assets/styles.css))',
            ],
            $this->missing(self::manifest(groups: [['name' => 'general', 'script' => null]], styles: true)),
        );
    }

    public function testSettingsScriptsAreNeeded(): void
    {
        self::assertSame(
            [
                'build/vendor/wp-app-framework/settings.asset.php (settings page (settings groups))',
                'build/Setting/Design.settings.asset.php (settings script of settings group design)',
            ],
            $this->missing(self::manifest(groups: [['name' => 'design', 'script' => 'Setting/Design.settings'], ['name' => 'plain', 'script' => null]])),
        );
    }

    public function testTheErrorNamesAMissingPackageJson(): void
    {
        $this->expectException(ReleaseException::class);
        $this->expectExceptionMessage("The release lacks built files the framework loads at run time:\n- build/vendor/wp-app-framework/settings.asset.php (settings page (settings groups))\nThe project has no package.json, so nothing was built");
        // @phpstan-ignore argument.type (only the parts of the manifest the check reads)
        BuildCheck::check(self::manifest(groups: [['name' => 'general', 'script' => null]]), $this->root);
    }

    public function testTheErrorPointsToTheBuildWithAPackageJson(): void
    {
        file_put_contents($this->root . '/package.json', '{}');

        $this->expectException(ReleaseException::class);
        $this->expectExceptionMessage('Check that "npm run build" builds them');
        // @phpstan-ignore argument.type (only the parts of the manifest the check reads)
        BuildCheck::check(self::manifest(groups: [['name' => 'general', 'script' => null]]), $this->root);
    }
}
