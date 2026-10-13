<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Unit\Block;

use PHPUnit\Framework\TestCase;
use Saij\WpAppFramework\Asset\BuildAssets;
use Saij\WpAppFramework\Block\BlockRegistrar;
use Saij\WpAppFramework\DependencyInjection\Container;
use Saij\WpAppFramework\Editor\RenderContext;
use Saij\WpAppFramework\Editor\RenderController;
use Saij\WpAppFramework\Manifest\Manifest;
use Saij\WpAppFramework\Manifest\ManifestGenerator;
use Saij\WpAppFramework\Tests\Support\Recorded;
use Saij\WpAppFramework\Tests\Support\Requests;
use Saij\WpAppFramework\Value\ValueFactory;
use Saij\WpAppFramework\View\ViewAssets;
use WP_Block;
use WP_Block_Type_Registry;

/**
 * Custom block categories and values of ancestors (#[Context]) at run time: in render() and in the render route of
 * the editor.
 *
 * @phpstan-import-type ManifestData from Manifest
 */
final class ContainerRenderTest extends TestCase
{
    private string $file;

    /** @var ManifestData */
    private array $manifest;

    private RenderContext $context;

    protected function setUp(): void
    {
        $this->file = (string) tempnam(sys_get_temp_dir(), 'manifest');
        file_put_contents($this->file, new ManifestGenerator()->generate(ContainerCollectorTest::buildValid()));
        $this->manifest = Manifest::load($this->file);
        $this->context = new RenderContext();
        $GLOBALS['wp_app_framework_test_blocks'] = [];
        $GLOBALS['wp_app_framework_test_caps'] = ['edit_posts'];

        new BlockRegistrar(
            new Container($this->manifest['services'], [RenderContext::class => $this->context]),
            new ValueFactory($this->manifest['values']),
            $this->context,
            true,
            'my-theme',
            new ViewAssets('my-theme', new BuildAssets('my-theme', '/nonexistent/build', 'https://example.test/build')),
        )->register($this->manifest['blocks'], $this->manifest['blockCategories']);
        foreach (Recorded::blocks() as $name => $args) {
            // @phpstan-ignore argument.type (the arguments the registrar passed to register_block_type())
            WP_Block_Type_Registry::get_instance()->register($name, $args);
        }
    }

    protected function tearDown(): void
    {
        unlink($this->file);
        foreach (array_keys(Recorded::blocks()) as $name) {
            WP_Block_Type_Registry::get_instance()->unregister($name);
        }
        unset(
            $GLOBALS['wp_app_framework_test_blocks'],
            $GLOBALS['wp_app_framework_test_caps'],
            $GLOBALS['wp_app_framework_test_filters'],
            $GLOBALS['wp_app_framework_test_filter_args'],
        );
    }

    public function testCustomCategoriesComeBeforeThoseOfWordPress(): void
    {
        self::assertSame(
            [
                ['slug' => 'fixture-sections', 'title' => 'Sections', 'icon' => 'layout'],
                ['slug' => 'fixture-extras', 'title' => 'Extras', 'icon' => null],
                ['slug' => 'text', 'title' => 'Text', 'icon' => null],
            ],
            apply_filters('block_categories_all', [['slug' => 'text', 'title' => 'Text', 'icon' => null]], null),
        );
    }

    public function testContextParametersReceiveTheValuesOfTheAncestorOrTheirDefault(): void
    {
        $render = Recorded::renderCallback('fixture/tab');

        self::assertSame(
            '[One|2|dark]',
            $render(['title' => 'One'], '', new WP_Block(['blockName' => 'fixture/tab'], ['fixture/tabs/active' => '2', 'fixture/tabs/tone' => 'dark'])),
            'The value is sanitized against the schema of the providing attribute and created like an attribute.',
        );
        self::assertSame('[One|7|none]', $render(['title' => 'One'], '', new WP_Block(['blockName' => 'fixture/tab'])));
    }

    public function testTheRenderRouteAddsTheContextTheEditorSends(): void
    {
        $contexts = [];
        foreach ($this->manifest['blocks'] as $block) {
            foreach ($block['arguments'] as $argument) {
                if ($argument['kind'] === 'context') {
                    $contexts[$block['name']][$argument['name']] = $argument['schema'];
                }
            }
        }
        $controller = new RenderController('my-theme', array_column($this->manifest['blocks'], 'name'), $this->context, true, $contexts);

        $response = $controller->handle(Requests::create(['blocks' => [
            ['name' => 'fixture/tab', 'attributes' => ['title' => 'One'], 'context' => ['fixture/tabs/active' => 3, 'fixture/tabs/tone' => 'dark']],
            ['name' => 'fixture/tab', 'attributes' => ['title' => 'One']],
            ['name' => 'fixture/tab', 'context' => ['postId' => 3]],
            ['name' => 'fixture/tab', 'context' => ['fixture/tabs/tone' => 'red']],
        ]]));
        /** @var array{results: list<array{html?: string, error?: array{code: string, message: string}}>} $data */
        $data = $response->get_data();

        self::assertSame(['html' => '[One|3|dark]'], $data['results'][0]);
        self::assertSame(['html' => '[One|7|none]'], $data['results'][1]);
        self::assertSame(['code' => 'wp_app_framework_invalid_context', 'message' => 'Block fixture/tab does not use the context "postId".'], $data['results'][2]['error'] ?? null);
        self::assertSame('wp_app_framework_invalid_context', $data['results'][3]['error']['code'] ?? null);
        self::assertSame([], Recorded::filters('render_block_context'), 'The context filter is removed after rendering.');
    }
}
