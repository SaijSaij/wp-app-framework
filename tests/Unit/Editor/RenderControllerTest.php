<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Unit\Editor;

use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;
use Saij\WpAppFramework\Block\BlockRegistrar;
use Saij\WpAppFramework\DependencyInjection\Container;
use Saij\WpAppFramework\Editor\EditorTemplateFunctions;
use Saij\WpAppFramework\Editor\RenderContext;
use Saij\WpAppFramework\Editor\RenderController;
use Saij\WpAppFramework\Manifest\Manifest;
use Saij\WpAppFramework\Manifest\ManifestGenerator;
use Saij\WpAppFramework\Tests\Support\Recorded;
use Saij\WpAppFramework\Tests\Support\Requests;
use Saij\WpAppFramework\Value\ValueFactory;
use WP_Block_Type_Registry;

/**
 * @phpstan-import-type BlockEntry from Manifest
 * @phpstan-import-type ManifestData from Manifest
 * @phpstan-type RenderResult array{html?: string, error?: array{code: string, message: string, data?: array{exception: string, file: string, line: int}}}
 */
final class RenderControllerTest extends TestCase
{
    private string $file;

    private string $log;

    private string|false $previousLog;

    /** @var ManifestData */
    private array $manifest;

    private RenderContext $context;

    protected function setUp(): void
    {
        $this->file = (string) tempnam(sys_get_temp_dir(), 'manifest');
        $this->log = (string) tempnam(sys_get_temp_dir(), 'log');
        $this->previousLog = ini_set('error_log', $this->log);

        file_put_contents($this->file, new ManifestGenerator()->generate(EditorBuildTest::buildValid()));
        $this->manifest = Manifest::load($this->file);
        $this->context = new RenderContext();
        $GLOBALS['wp_app_framework_test_caps'] = ['edit_posts', 'edit_post:7'];
        $GLOBALS['wp_app_framework_test_posts'] = [7 => (object) ['ID' => 7], 8 => (object) ['ID' => 8]];
    }

    protected function tearDown(): void
    {
        ini_set('error_log', (string) $this->previousLog);
        unlink($this->log);
        unlink($this->file);
        self::unregisterBlockTypes();
        unset(
            $GLOBALS['wp_app_framework_test_blocks'],
            $GLOBALS['wp_app_framework_test_caps'],
            $GLOBALS['wp_app_framework_test_posts'],
            $GLOBALS['wp_app_framework_test_postdata'],
            $GLOBALS['post'],
        );
    }

    /**
     * Registers the fixture blocks like WordPress would and returns the route of instance "fixture".
     */
    private function controller(bool $debug): RenderController
    {
        $container = new Container($this->manifest['services'], [RenderContext::class => $this->context]);
        new BlockRegistrar($container, new ValueFactory($this->manifest['values']), $this->context, $debug, 'my-theme')
            ->register($this->manifest['blocks']);
        foreach (Recorded::blocks() as $name => $args) {
            // @phpstan-ignore argument.type (the arguments the registrar passed to register_block_type())
            WP_Block_Type_Registry::get_instance()->register($name, $args);
        }

        return new RenderController('fixture', array_column($this->manifest['blocks'], 'name'), $this->context, $debug);
    }

    /**
     * @param list<array<string, mixed>> $blocks
     * @return list<RenderResult>
     */
    private function render(array $blocks, bool $debug = true): array
    {
        $response = $this->controller($debug)->handle(Requests::create(['blocks' => $blocks]));
        /** @var array{results: list<RenderResult>} $data */
        $data = $response->get_data();

        return $data['results'];
    }

    public function testRendersEachEntryForTheEditorWithFieldMarks(): void
    {
        $results = $this->render([
            ['name' => 'fixture/teaser', 'attributes' => ['kicker' => 'Hi & bye', 'ctas' => [['label' => 'Go'], ['label' => 'Stop']]]],
            ['name' => 'fixture/container', 'attributes' => []],
        ]);

        self::assertSame(
            [
                ['html' => '<div><p data-field="kicker">Hi &amp; bye</p><i>editor</i><a data-field="ctas.0.label">Go</a><a data-field="ctas.1.label">Stop</a></div>'],
                ['html' => '<section data-inner-blocks></section>'],
            ],
            $results,
        );
        self::assertFalse($this->context->isEditor(), 'The editor flag is reset after the request.');
    }

    public function testFrontEndRenderingHasNoMarks(): void
    {
        $this->controller(true);

        self::assertSame(
            '<div><p>Hi</p></div>',
            render_block(['blockName' => 'fixture/teaser', 'attrs' => ['kicker' => 'Hi']]),
        );
    }

    public function testEntriesFailIndividuallyWithoutFailingTheBatch(): void
    {
        $results = $this->render([
            ['name' => 'fixture/unknown'],
            ['name' => 'core/paragraph'],
            ['name' => 'fixture/teaser', 'attributes' => ['nope' => 1]],
            ['name' => 'fixture/teaser', 'postId' => 99],
            ['name' => 'fixture/teaser', 'postId' => 8],
            ['name' => 'fixture/teaser', 'postId' => 7, 'attributes' => ['kicker' => 'Post']],
        ]);

        self::assertSame('wp_app_framework_unknown_block', $results[0]['error']['code'] ?? null);
        self::assertSame('Block core/paragraph is not a block of fixture.', $results[1]['error']['message'] ?? null);
        self::assertSame(['code' => 'wp_app_framework_invalid_attributes', 'message' => 'nope is not a valid property of Object.'], $results[2]['error'] ?? null);
        self::assertSame(['code' => 'wp_app_framework_post_not_found', 'message' => 'Post 99 does not exist.'], $results[3]['error'] ?? null);
        self::assertSame(['code' => 'wp_app_framework_forbidden', 'message' => 'You may not edit post 8.'], $results[4]['error'] ?? null);
        self::assertStringContainsString('>Post</p>', $results[5]['html'] ?? '');
        self::assertSame(7, $GLOBALS['wp_app_framework_test_postdata']);
        self::assertArrayNotHasKey('post', $GLOBALS, 'The previous global post is restored.');
    }

    public function testRenderErrorsAreLoggedWithDetailsOnlyInDebugMode(): void
    {
        $debug = $this->render([['name' => 'fixture/wrong-field']])[0]['error'] ?? null;
        self::assertSame('wp_app_framework_render_failed', $debug['code'] ?? null);
        self::assertSame('field() in block fixture/wrong-field: "missing" is not an inline field of the block (it has none).', $debug['message'] ?? null);
        self::assertSame(InvalidArgumentException::class, $debug['data']['exception'] ?? null);

        self::unregisterBlockTypes();
        // Without debugging, the block registrar logs the error and renders nothing, like on the front end.
        self::assertSame([['html' => '']], $this->render([['name' => 'fixture/wrong-field']], false));
        self::assertStringContainsString('"missing" is not an inline field', (string) file_get_contents($this->log));
        self::assertFalse($this->context->isEditor());
    }

    public function testTemplateFunctionsCheckTheCurrentBlock(): void
    {
        $functions = new EditorTemplateFunctions($this->context);
        $teaserFields = $this->block('fixture/teaser')['editor']['fields'];

        self::assertSame(' data-field="ctas.3.label"', $this->context->renderForEditor(
            fn(): string => $this->context->renderBlock('fixture/teaser', $teaserFields, false, static fn(): string => $functions->field(['ctas', 3, 'label'])),
        ));
        self::assertSame('', $this->context->renderBlock('fixture/teaser', $teaserFields, false, static fn(): string => $functions->field('caption.text')));

        try {
            $this->context->renderBlock('fixture/teaser', $teaserFields, false, static fn(): string => $functions->innerBlocks());
            self::fail('Expected an exception.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('inner_blocks() in block fixture/teaser: the block has no inner blocks; its render() must accept string $content.', $exception->getMessage());
        }

        try {
            $this->context->renderBlock('fixture/teaser', $teaserFields, false, static fn(): string => $functions->field('ctas.*.label'));
            self::fail('Expected an exception.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame("field() in block fixture/teaser: path segments must be attribute names or list indexes, '*' given.", $exception->getMessage());
        }

        try {
            $this->context->renderBlock('fixture/teaser', $teaserFields, false, static fn(): string => $functions->field('ctas.0'));
            self::fail('Expected an exception.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('field() in block fixture/teaser: "ctas.0" is not an inline field of the block (inline fields: kicker, title, imageId, ctas.*.label, ctas.*.iconId, caption.text, note).', $exception->getMessage());
        }

        try {
            $this->context->renderBlock('fixture/teaser', $teaserFields, false, static fn(): string => $functions->field(['ctas', 1, 'iconId']));
            self::fail('Expected an exception.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('field() in block fixture/teaser: "ctas.1.iconId" is edited in the inspector (inspector: true); do not mark it with field().', $exception->getMessage());
        }

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('field() is only available in templates of blocks rendered by the framework.');
        $functions->field('kicker');
    }

    /**
     * @return BlockEntry
     */
    private function block(string $name): array
    {
        foreach ($this->manifest['blocks'] as $block) {
            if ($block['name'] === $name) {
                return $block;
            }
        }

        throw new LogicException(sprintf('The manifest has no block %s.', $name));
    }

    private static function unregisterBlockTypes(): void
    {
        $registry = WP_Block_Type_Registry::get_instance();
        foreach (array_keys($registry->get_all_registered()) as $name) {
            $registry->unregister($name);
        }
    }
}
