<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Unit\Block;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Saij\WpAppFramework\Block\BlockDefinition;
use Saij\WpAppFramework\Block\Repeater;
use Saij\WpAppFramework\Editor\RenderContext;
use Saij\WpAppFramework\Manifest\BuildException;
use Saij\WpAppFramework\Manifest\BuildResult;
use Saij\WpAppFramework\Manifest\ManifestBuilder;
use Saij\WpAppFramework\Tests\Fixtures\RepeaterBroken;
use Saij\WpAppFramework\Tests\Unit\Manifest\ManifestBuilderTest;
use WP_Block;
use WP_Comment;
use WP_Post;
use WP_Term;
use WP_User;

/**
 * Blocks that repeat their inner blocks (render() with a Repeater) and the current item of a repeater (#[Item]):
 * registration, the items' context, build errors. Rendering the inner blocks per item needs WordPress; the test
 * theme checks it in the browser.
 */
final class RepeaterTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($GLOBALS['wp_app_framework_test_posts'], $GLOBALS['wp_app_framework_test_terms'], $GLOBALS['wp_app_framework_test_comments'], $GLOBALS['wp_app_framework_test_users']);
    }

    private static function build(): BuildResult
    {
        return new ManifestBuilder([ManifestBuilderTest::fixture('RepeaterValid'), ManifestBuilder::frameworkSource()], namespace: 'my-theme')->build();
    }

    /**
     * @return array<string, BlockDefinition>
     */
    private static function blocks(): array
    {
        $blocks = [];
        foreach (self::build()->getBlocks() as $block) {
            $blocks[$block->getName()] = $block;
        }

        return $blocks;
    }

    public function testARepeaterHasInnerBlocksThatWordPressDoesNotRenderOnce(): void
    {
        $loop = self::blocks()['my-theme/loop'];

        self::assertTrue($loop->getArgs()['skip_inner_blocks'] ?? null);
        self::assertSame(['my-theme/card'], $loop->getArgs()['allowed_blocks'] ?? null);
        self::assertTrue($loop->getEditor()['innerBlocks']);
        self::assertTrue($loop->getEditor()['repeater']);
        self::assertSame([['param' => 'attrs', 'kind' => 'attributes', 'class' => \Saij\WpAppFramework\Tests\Fixtures\RepeaterValid\LoopAttributes::class], ['param' => 'repeater', 'kind' => 'repeater']], $loop->getArguments());
    }

    public function testItemsReadTheContextOfTheirKind(): void
    {
        $card = self::blocks()['my-theme/card'];

        self::assertSame(['postId', 'postType', 'termId', 'taxonomy', 'commentId', 'userId', 'my-theme/visit'], $card->getArgs()['uses_context'] ?? null);
        self::assertSame(
            [
                ['post', null, ['postId', 'postType']],
                ['postClass', \Saij\WpAppFramework\Tests\Fixtures\RepeaterValid\Event::class, ['postId', 'postType']],
                ['term', null, ['termId', 'taxonomy']],
                ['comment', null, ['commentId']],
                ['user', null, ['userId']],
                ['row', \Saij\WpAppFramework\Tests\Fixtures\RepeaterValid\Visit::class, ['my-theme/visit']],
            ],
            array_map(static fn(array $argument): array => [$argument['source'] ?? null, $argument['class'] ?? null, $argument['context'] ?? null], $card->getArguments()),
        );
        self::assertFalse($card->getEditor()['repeater']);
    }

    public function testItemsInTheEditorCarryTheirContextWithoutContent(): void
    {
        $GLOBALS['wp_app_framework_test_posts'] = [7 => new WP_Post((object) ['ID' => 7, 'post_type' => 'page'])];
        $GLOBALS['wp_app_framework_test_terms'] = [3 => new WP_Term((object) ['term_id' => 3, 'taxonomy' => 'post_tag'])];
        $GLOBALS['wp_app_framework_test_comments'] = [5 => new WP_Comment((object) ['comment_ID' => '5'])];
        $GLOBALS['wp_app_framework_test_users'] = [2 => new WP_User((object) ['ID' => 2])];
        $context = new RenderContext();

        $items = $context->renderForEditor(static function () use ($context): array {
            $repeater = new Repeater(new WP_Block(['blockName' => 'my-theme/loop']), $context);

            return [$repeater->post(7), $repeater->term(3), $repeater->comment(5), $repeater->user(2)];
        });

        self::assertSame(
            [['postId' => 7, 'postType' => 'page'], ['termId' => 3, 'taxonomy' => 'post_tag'], ['commentId' => 5], ['userId' => 2]],
            array_map(static fn($item): array => $item->getContext(), $items),
        );
        self::assertSame(['', '', '', ''], array_map(static fn($item): string => $item->getContent(), $items));
    }

    public function testMissingItemsAreErrors(): void
    {
        $repeater = new Repeater(new WP_Block(['blockName' => 'my-theme/loop']), new RenderContext());
        $calls = [
            'Repeater::post(): there is no post with the ID 9.' => static fn() => $repeater->post(9),
            'Repeater::term(): there is no term with the ID 9.' => static fn() => $repeater->term(9),
            'Repeater::comment(): there is no comment with the ID 9.' => static fn() => $repeater->comment(9),
            'Repeater::user(): there is no user with the ID 9.' => static fn() => $repeater->user(9),
        ];
        foreach ($calls as $message => $call) {
            try {
                $call();
                self::fail('InvalidArgumentException expected: ' . $message);
            } catch (InvalidArgumentException $exception) {
                self::assertSame($message, $exception->getMessage());
            }
        }
    }

    public function testReportsAllErrors(): void
    {
        $ns = 'Saij\\WpAppFramework\\Tests\\Fixtures\\RepeaterBroken\\';
        $items = static fn(string $name): string => sprintf('parameter $%s of %sItems::render()', $name, $ns);
        $expected = [
            "{$ns}Both::render() accepts string \$content and Repeater; a block either renders its inner blocks once (string \$content) or once per item (Repeater).",
            "#[Block] on {$ns}Wrapper has excerptWrapper, but {$ns}Wrapper::render() accepts Repeater; WordPress includes the inner blocks of an excerpt wrapper once, not per item.",
            $items('required') . ' has #[Item], so it must be nullable (?WP_Post): outside an item of its kind it is null.',
            $items('text') . ' has #[Item], but its type ?string is no item of a repeater; use a post class of the theme or plugin, WP_Post, WP_Term, WP_Comment, WP_User or a row class (#[Table]) of the theme or plugin.',
            $items('both') . ' has #[Item] and #[Context]; a parameter receives either the current item of a repeater or a value of an ancestor.',
            $items('other') . ' has #[Item], but its type ?DateTimeImmutable is no item of a repeater; use a post class of the theme or plugin, WP_Post, WP_Term, WP_Comment, WP_User or a row class (#[Table]) of the theme or plugin.',
        ];

        try {
            new ManifestBuilder([ManifestBuilderTest::fixture('RepeaterBroken')])->build();
            self::fail('BuildException expected.');
        } catch (BuildException $exception) {
            foreach ($expected as $message) {
                self::assertContains($message, $exception->getErrors());
            }
            self::assertCount(count($expected), $exception->getErrors(), implode("\n", $exception->getErrors()));
        }
        self::assertTrue(class_exists(RepeaterBroken\Items::class));
    }
}
