<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Unit\Twig;

use PHPUnit\Framework\TestCase;
use Saij\WpAppFramework\Api\PluginTemplateFunctions;
use Saij\WpAppFramework\Block\BlockTemplateFunctions;
use Saij\WpAppFramework\Editor\EditorTemplateFunctions;
use Saij\WpAppFramework\Manifest\BuildException;
use Saij\WpAppFramework\Manifest\BuildResult;
use Saij\WpAppFramework\Manifest\ManifestBuilder;
use Saij\WpAppFramework\Post\PostTemplateFunctions;
use Saij\WpAppFramework\Tests\Fixtures\TwigValid;
use Saij\WpAppFramework\Tests\Unit\Manifest\ManifestBuilderTest;
use Saij\WpAppFramework\Theme;
use Saij\WpAppFramework\Twig\TemplateCallableKind;
use Saij\WpAppFramework\Twig\TemplateRenderer;
use Saij\WpAppFramework\Twig\WordPressTemplateFunctions;

final class TemplateCallableCollectorTest extends TestCase
{
    public static function buildValid(): BuildResult
    {
        return new ManifestBuilder(
            [ManifestBuilderTest::fixture('TwigValid'), ManifestBuilder::frameworkSource()],
            [],
            [Theme::class, TemplateRenderer::class],
        )->build();
    }

    public function testCollectsFunctionsAndFilters(): void
    {
        $collected = [];
        foreach (self::buildValid()->getTemplateCallables() as $callable) {
            $collected[] = [$callable->getKind(), $callable->getName(), $callable->getClass(), $callable->getMethod(), $callable->isHtml()];
        }

        self::assertSame(
            [
                [TemplateCallableKind::Function, 'greet', TwigValid\TextHelpers::class, 'greet', false],
                [TemplateCallableKind::Function, 'badge', TwigValid\TextHelpers::class, 'badge', true],
                [TemplateCallableKind::Filter, 'wrap', TwigValid\TextHelpers::class, 'wrap', false],
                [TemplateCallableKind::Filter, 'wrap_html', TwigValid\TextHelpers::class, 'wrapHtml', true],
                [TemplateCallableKind::Function, 'count_items', TwigValid\TextHelpers::class, 'countItems', false],
                [TemplateCallableKind::Function, 'has_plugin', PluginTemplateFunctions::class, 'hasPlugin', false],
                [TemplateCallableKind::Function, 'block_wrapper_attributes', BlockTemplateFunctions::class, 'blockWrapperAttributes', true],
                [TemplateCallableKind::Function, 'field', EditorTemplateFunctions::class, 'field', true],
                [TemplateCallableKind::Function, 'inner_blocks', EditorTemplateFunctions::class, 'innerBlocks', true],
                [TemplateCallableKind::Function, 'is_editor', EditorTemplateFunctions::class, 'isEditor', false],
                [TemplateCallableKind::Function, 'post', PostTemplateFunctions::class, 'post', false],
                [TemplateCallableKind::Function, 'wp_attachment_image', WordPressTemplateFunctions::class, 'attachmentImage', true],
                [TemplateCallableKind::Function, 'wp_attachment_image_url', WordPressTemplateFunctions::class, 'attachmentImageUrl', false],
                [TemplateCallableKind::Function, 'wp_attachment_url', WordPressTemplateFunctions::class, 'attachmentUrl', false],
                [TemplateCallableKind::Function, 'wp_attachment_caption', WordPressTemplateFunctions::class, 'attachmentCaption', true],
                [TemplateCallableKind::Function, 'wp_attachment_alt', WordPressTemplateFunctions::class, 'attachmentAlt', false],
                [TemplateCallableKind::Function, 'wp_post_url', WordPressTemplateFunctions::class, 'postUrl', false],
                [TemplateCallableKind::Function, 'wp_post_title', WordPressTemplateFunctions::class, 'postTitle', false],
                [TemplateCallableKind::Function, 'wp_post_excerpt', WordPressTemplateFunctions::class, 'postExcerpt', false],
                [TemplateCallableKind::Function, 'wp_post_date', WordPressTemplateFunctions::class, 'postDate', false],
                [TemplateCallableKind::Function, 'wp_post_thumbnail', WordPressTemplateFunctions::class, 'postThumbnail', true],
                [TemplateCallableKind::Function, 'wp_term_url', WordPressTemplateFunctions::class, 'termUrl', false],
                [TemplateCallableKind::Function, 'wp_term_name', WordPressTemplateFunctions::class, 'termName', false],
                [TemplateCallableKind::Function, 'wp_user_name', WordPressTemplateFunctions::class, 'userName', false],
                [TemplateCallableKind::Function, 'wp_user_url', WordPressTemplateFunctions::class, 'userUrl', false],
                [TemplateCallableKind::Function, 'wp_avatar', WordPressTemplateFunctions::class, 'avatar', true],
                [TemplateCallableKind::Function, 'wp_home_url', WordPressTemplateFunctions::class, 'homeUrl', false],
                [TemplateCallableKind::Function, 'wp_site_name', WordPressTemplateFunctions::class, 'siteName', false],
                [TemplateCallableKind::Function, 'wp_site_description', WordPressTemplateFunctions::class, 'siteDescription', false],
                [TemplateCallableKind::Function, 'wp_login_url', WordPressTemplateFunctions::class, 'loginUrl', false],
                [TemplateCallableKind::Function, 'wp_logout_url', WordPressTemplateFunctions::class, 'logoutUrl', false],
                [TemplateCallableKind::Function, 'wp_is_user_logged_in', WordPressTemplateFunctions::class, 'isUserLoggedIn', false],
                [TemplateCallableKind::Function, 'wp_date', WordPressTemplateFunctions::class, 'date', false],
                [TemplateCallableKind::Function, 'wp_human_time_diff', WordPressTemplateFunctions::class, 'humanTimeDiff', false],
                [TemplateCallableKind::Function, 'wp_number_format', WordPressTemplateFunctions::class, 'numberFormat', false],
                [TemplateCallableKind::Function, 'wp_size_format', WordPressTemplateFunctions::class, 'sizeFormat', false],
            ],
            $collected,
        );
    }

    public function testProvidersAreReachableAndTheRendererIsInjectable(): void
    {
        $services = self::buildValid()->getServices();

        self::assertSame(['prefix' => TwigValid\Prefix::class], $services[TwigValid\TextHelpers::class]);
        self::assertSame([], $services[TwigValid\Prefix::class]);
        self::assertSame(['renderer' => TemplateRenderer::class], $services[TwigValid\Mailer::class]);
        self::assertArrayNotHasKey(TemplateRenderer::class, $services);
    }

    public function testReportsAllErrorsWithClassMethodAndParameterNames(): void
    {
        try {
            new ManifestBuilder([ManifestBuilderTest::fixture('TwigBroken')])->build();
            self::fail('BuildException expected.');
        } catch (BuildException $exception) {
            $c = 'Saij\\WpAppFramework\\Tests\\Fixtures\\TwigBroken\\BrokenCallables';
            $ns = 'Saij\\WpAppFramework\\Tests\\Fixtures\\TwigBroken\\';
            $expected = [
                "#[TemplateFunction] on {$c}::invalidName() has the invalid name \"bad-name\"; use letters, digits and underscores, not starting with a digit.",
                "#[TemplateFunction] on {$c}::builtInFunction() uses the name \"range\", which Twig already provides as a function.",
                "#[TemplateFilter] on {$c}::builtInFilter() uses the name \"upper\", which Twig already provides as a filter.",
                "#[TemplateFunction] on {$c}::second() uses the name \"twice\", which {$c}::first() already declares.",
                "{$c}::staticFunction() has #[TemplateFunction] and must be public and non-static.",
                "{$c}::noReturnType() has #[TemplateFunction] and needs a declared return type: the type of the value printed in the template.",
                "#[TemplateFunction] on {$c}::voidFunction() must return a value, not void.",
                "#[TemplateFunction] on {$c}::nullableHtml() has html: true and must return string.",
                "#[TemplateFilter] on {$c}::filterWithoutValue() needs a first parameter that receives the filtered value.",
                "parameter \$values of {$c}::variadic(): variadic and by-reference parameters are not supported in template functions.",
                "parameter \$helper of {$c}::withService(): services are not injected into template functions; inject {$ns}Helper through the constructor of {$c}.",
                "{$ns}NotAService::excluded() has #[TemplateFunction], but {$ns}NotAService is marked #[NoService] and therefore not a service.",
            ];

            foreach ($expected as $message) {
                self::assertContains($message, $exception->getErrors());
            }
            self::assertCount(count($expected), $exception->getErrors());
        }
    }

    public function testBuiltInNamesIncludeDevelopmentOnlyFunctions(): void
    {
        $names = TemplateRenderer::getBuiltInNames();

        self::assertContains('range', $names['function']);
        self::assertContains('dump', $names['function']);
        self::assertContains('upper', $names['filter']);
        self::assertContains('escape', $names['filter']);
    }
}
