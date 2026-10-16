<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Unit\Editor;

use Closure;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\TestCase;
use Saij\WpAppFramework\Asset\BuildAssets;
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
use Saij\WpAppFramework\View\ViewAssets;
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
        $GLOBALS['wp_app_framework_test_posts'] = [7 => new \WP_Post((object) ['ID' => 7]), 8 => new \WP_Post((object) ['ID' => 8, 'post_status' => 'draft']), 9 => new \WP_Post((object) ['ID' => 9])];
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
            $GLOBALS['wp_app_framework_test_parsed_blocks'],
            $GLOBALS['post'],
        );
    }

    /**
     * Registers the fixture blocks like WordPress would and returns the route of instance "fixture".
     */
    private function controller(bool $debug): RenderController
    {
        $container = new Container($this->manifest['services'], [RenderContext::class => $this->context]);
        new BlockRegistrar($container, new ValueFactory($this->manifest['values']), $this->context, $debug, 'my-theme', new ViewAssets('my-theme', new BuildAssets('my-theme', '/nonexistent/build', 'https://example.test/build')))
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
            ['name' => 'fixture/teaser', 'postId' => 9, 'attributes' => ['kicker' => 'Public']],
        ]);

        self::assertSame('wp_app_framework_unknown_block', $results[0]['error']['code'] ?? null);
        self::assertSame('Block core/paragraph is not a block of fixture.', $results[1]['error']['message'] ?? null);
        self::assertSame(['code' => 'wp_app_framework_invalid_attributes', 'message' => 'nope is not a valid property of Object.'], $results[2]['error'] ?? null);
        self::assertSame(['code' => 'wp_app_framework_post_not_found', 'message' => 'Post 99 does not exist.'], $results[3]['error'] ?? null);
        self::assertSame(['code' => 'wp_app_framework_forbidden', 'message' => 'You may not edit post 8, and it is not public.'], $results[4]['error'] ?? null);
        // A published post of others renders too: the previews of a repeater show them.
        self::assertStringContainsString('>Public</p>', $results[6]['html'] ?? '');
        self::assertStringContainsString('>Post</p>', $results[5]['html'] ?? '');
        // The post of the last entry was set up for its render.
        self::assertSame(9, $GLOBALS['wp_app_framework_test_postdata']);
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

    /**
     * @param list<array<string, mixed>> $blocks Parsed blocks of the content.
     */
    private function preview(array $blocks, ?int $postId = null, bool $debug = true): \WP_REST_Response|\WP_Error
    {
        $GLOBALS['wp_app_framework_test_parsed_blocks'] = ['<!-- content -->' => $blocks];

        return $this->controller($debug)->preview(Requests::create(['content' => '<!-- content -->', 'postId' => $postId]));
    }

    /**
     * @param array<string, mixed> $attrs
     * @return array<string, mixed>
     */
    private static function parsed(string $name, array $attrs = []): array
    {
        return ['blockName' => $name, 'attrs' => $attrs, 'innerBlocks' => [], 'innerHTML' => '', 'innerContent' => []];
    }

    public function testPreviewRendersTheBlockAsOnTheFrontEnd(): void
    {
        $whitespace = ['blockName' => null, 'attrs' => [], 'innerBlocks' => [], 'innerHTML' => "\n", 'innerContent' => ["\n"]];
        $response = $this->preview([$whitespace, self::parsed('fixture/teaser', ['kicker' => 'Hi & bye']), $whitespace], 7);

        self::assertInstanceOf(\WP_REST_Response::class, $response);
        self::assertSame(['html' => '<div><p>Hi &amp; bye</p></div>'], $response->get_data());
        self::assertSame(7, $GLOBALS['wp_app_framework_test_postdata']);
        self::assertArrayNotHasKey('post', $GLOBALS, 'The previous global post is restored.');
    }

    public function testPreviewRendersExactlyOneBlockOfTheInstance(): void
    {
        foreach ([[], [self::parsed('fixture/teaser'), self::parsed('fixture/teaser')], [self::parsed('core/paragraph')]] as $blocks) {
            self::unregisterBlockTypes();
            $response = $this->preview($blocks);
            self::assertInstanceOf(\WP_Error::class, $response);
            self::assertSame('wp_app_framework_invalid_preview', $response->get_error_code());
            self::assertSame('The preview renders exactly one block of fixture.', $response->get_error_message());
            self::assertSame(['status' => 400], $response->get_error_data());
        }
    }

    public function testPreviewChecksThePost(): void
    {
        $missing = $this->preview([self::parsed('fixture/teaser')], 99);
        self::assertInstanceOf(\WP_Error::class, $missing);
        self::assertSame(['wp_app_framework_post_not_found', ['status' => 404]], [$missing->get_error_code(), $missing->get_error_data()]);

        self::unregisterBlockTypes();
        $forbidden = $this->preview([self::parsed('fixture/teaser')], 8);
        self::assertInstanceOf(\WP_Error::class, $forbidden);
        self::assertSame(['wp_app_framework_forbidden', ['status' => 403]], [$forbidden->get_error_code(), $forbidden->get_error_data()]);
    }

    public function testPreviewErrorsAreLogged(): void
    {
        // The preview renders like the front end, where field() checks no path.
        self::assertInstanceOf(\WP_REST_Response::class, $this->preview([self::parsed('fixture/wrong-field')]));
        self::unregisterBlockTypes();

        $error = $this->preview([self::parsed('fixture/teaser', ['ctas' => 'none'])]);

        self::assertInstanceOf(\WP_Error::class, $error);
        self::assertSame('wp_app_framework_render_failed', $error->get_error_code());
        self::assertStringContainsString('ctas', $error->get_error_message());
        $data = $error->get_error_data();
        self::assertIsArray($data);
        self::assertSame(500, $data['status'] ?? null);
        self::assertStringContainsString('editor preview of block fixture/teaser failed', (string) file_get_contents($this->log));
    }

    public function testTemplateFunctionsCheckTheCurrentBlock(): void
    {
        $functions = new EditorTemplateFunctions($this->context);
        $teaserFields = $this->block('fixture/teaser')['editor']['fields'];

        self::assertSame(' data-field="ctas.3.label"', $this->context->renderForEditor(
            fn(): string => $this->context->renderBlock('fixture/teaser', $teaserFields, false, static fn(): string => $functions->field(['ctas', 3, 'label'])),
        ));
        self::assertSame('', $this->context->renderBlock('fixture/teaser', $teaserFields, false, static fn(): string => $functions->field('caption.text')));
        // Outside of the editor field() returns '' without checking the path.
        self::assertSame('', $this->context->renderBlock('fixture/teaser', $teaserFields, false, static fn(): string => $functions->field('missing')));
        self::assertSame('', $functions->field('kicker'));
        /** @param Closure(): string $render */
        $editor = fn(Closure $render): string => $this->context->renderForEditor(fn(): string => $this->context->renderBlock('fixture/teaser', $teaserFields, false, $render));

        // list_item() marks items of lists with appender, in renders for the editor only.
        $appenders = $this->block('fixture/teaser')['editor']['appenders'];
        self::assertSame(['ctas'], $appenders);
        self::assertSame(' data-list-item="ctas.2"', $this->context->renderForEditor(
            fn(): string => $this->context->renderBlock('fixture/teaser', $teaserFields, false, static fn(): string => $functions->listItem(['ctas', 2]), appenders: $appenders),
        ));
        self::assertSame('', $this->context->renderBlock('fixture/teaser', $teaserFields, false, static fn(): string => $functions->listItem(['missing', 0]), appenders: $appenders));
        $shape = 'list_item() in block fixture/teaser needs the names to a list and the index of the item, such as ["buttons", loop.index0].';
        foreach ([
            ['list_item() in block fixture/teaser: "caption" is no list with appender of the block (lists with appender: ctas).', ['caption', 0]],
            [$shape, ['ctas', 'first']],
            [$shape, [2]],
        ] as [$message, $path]) {
            try {
                $this->context->renderForEditor(
                    fn(): string => $this->context->renderBlock('fixture/teaser', $teaserFields, false, static fn(): string => $functions->listItem($path), appenders: $appenders),
                );
                self::fail('Expected an exception: ' . $message);
            } catch (InvalidArgumentException $exception) {
                self::assertSame($message, $exception->getMessage());
            }
        }

        try {
            $this->context->renderBlock('fixture/teaser', $teaserFields, false, static fn(): string => $functions->innerBlocks());
            self::fail('Expected an exception.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('inner_blocks() in block fixture/teaser: the block has no inner blocks; its render() must accept string $content.', $exception->getMessage());
        }

        try {
            $editor(static fn(): string => $functions->field('ctas.*.label'));
            self::fail('Expected an exception.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame("field() in block fixture/teaser: path segments must be attribute names or list indexes, '*' given.", $exception->getMessage());
        }

        try {
            $editor(static fn(): string => $functions->field('ctas.0'));
            self::fail('Expected an exception.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('field() in block fixture/teaser: "ctas.0" is not an inline field of the block (inline fields: kicker, title, imageId, ctas.*.label, ctas.*.iconId, caption.text, note).', $exception->getMessage());
        }

        try {
            $editor(static fn(): string => $functions->field(['ctas', 1, 'iconId']));
            self::fail('Expected an exception.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('field() in block fixture/teaser: "ctas.1.iconId" is edited in the inspector (inspector: true); do not mark it with field().', $exception->getMessage());
        }

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('field() is only available in templates of blocks rendered by the framework.');
        $this->context->renderForEditor(static fn(): string => $functions->field('kicker'));
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
