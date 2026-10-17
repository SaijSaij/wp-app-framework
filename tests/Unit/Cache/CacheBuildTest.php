<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Unit\Cache;

use PHPUnit\Framework\TestCase;
use WebCodingCat\WpAppFramework\Cache\CacheStore;
use WebCodingCat\WpAppFramework\Editor\RenderContext;
use WebCodingCat\WpAppFramework\Manifest\BuildException;
use WebCodingCat\WpAppFramework\Manifest\BuildResult;
use WebCodingCat\WpAppFramework\Manifest\ManifestBuilder;
use WebCodingCat\WpAppFramework\Tests\Fixtures\CacheBroken;
use WebCodingCat\WpAppFramework\Tests\Fixtures\CacheValid;
use WebCodingCat\WpAppFramework\Tests\Unit\Manifest\ManifestBuilderTest;
use WebCodingCat\WpAppFramework\Theme;
use WebCodingCat\WpAppFramework\Twig\TemplateRenderer;

/**
 * #[Cache] in the build: the entries of blocks and routes, and every error.
 */
final class CacheBuildTest extends TestCase
{
    public static function buildValid(): BuildResult
    {
        $fixture = ManifestBuilderTest::fixture('CacheValid');

        return new ManifestBuilder(
            [$fixture, ManifestBuilder::frameworkSource()],
            [],
            [Theme::class, TemplateRenderer::class, RenderContext::class, CacheStore::class],
            $fixture->getDirectory(),
            slug: 'my-theme',
            name: 'My Theme',
        )->build();
    }

    public function testBlocksAndRoutesCarryTheirEntry(): void
    {
        $result = self::buildValid();
        $blocks = [];
        foreach ($result->getBlocks() as $block) {
            $blocks[$block->getName()] = $block->getCache();
        }

        self::assertSame(
            [
                'fixture/accordion' => ['ttl' => 60, 'tags' => [], 'vary' => []],
                'fixture/latest' => [
                    'ttl' => 60,
                    'tags' => ['post_type:post', 'settings:site', 'custom:' . CacheValid\Feed::class . ':weather'],
                    'vary' => [['type' => 'query', 'name' => 'page'], ['type' => 'service', 'class' => CacheValid\Region::class]],
                ],
            ],
            $blocks,
        );
        self::assertSame(
            ['ttl' => 30, 'tags' => ['custom:' . CacheValid\Feed::class . ':weather'], 'vary' => [['type' => 'user']]],
            $result->getRoutes()[0]->getCache(),
        );
        // The provider of a ServiceVary is built as a service.
        self::assertArrayHasKey(CacheValid\Region::class, $result->getServices());
    }

    public function testReportsEveryErrorWithItsLocation(): void
    {
        $block = '#[Cache] on ' . CacheBroken\BrokenBlock::class . ': ';
        $routes = '#[Cache] on ' . CacheBroken\BrokenRoutes::class . '::';
        $expected = [
            $routes . 'write(): a cached route must answer only GET; other methods change data.',
            $routes . 'none(): a cached route must return plain data (array, string, int, float, bool or null) or a WP_REST_Response, void given.',
            CacheBroken\NotABlock::class . ' has #[Cache], but no #[Block]; #[Cache] belongs on a block class or a #[RestRoute] method.',
            CacheBroken\NotABlock::class . '::data() has #[Cache], but no #[RestRoute]; #[Cache] belongs on a block class or a #[RestRoute] method.',
            $block . 'the ttl must be at least 1 second, 0 given.',
            $block . 'PostTypeTag needs a post type name of 1 to 20 characters, "" given.',
            $block . 'TaxonomyTag needs a taxonomy name of 1 to 32 characters, "a_taxonomy_name_longer_than_32_chars" given.',
            $block . 'SettingsTag needs a class with #[SettingsGroup] of this theme or plugin, ' . CacheBroken\NotAGroup::class . ' given.',
            $block . 'the tag post_type:post is listed twice.',
            $block . CacheBroken\ClassTag::class . ' implements CacheTag, but tags of your own must be string-backed enums.',
            $block . CacheBroken\IntTag::class . ' implements CacheTag, but tags of your own must be string-backed enums.',
            $block . 'tags must implement CacheTag (PostTypeTag, TaxonomyTag, SettingsTag or an enum of your own), string given.',
            $block . 'QueryParamVary needs a name.',
            $block . 'HeaderVary needs a name.',
            $block . 'CookieVary needs a name.',
            $block . 'the variant UserVary is listed twice.',
            $block . 'ServiceVary(' . CacheBroken\NotAProvider::class . '::class): ' . CacheBroken\NotAProvider::class . ' does not implement VaryProvider.',
            $block . 'ServiceVary(' . CacheBroken\ExcludedProvider::class . '::class): the provider must be a service, but ' . CacheBroken\ExcludedProvider::class . ' is marked #[NoService].',
            $block . 'variants must be UserVary, LoggedInVary, RoleVary, UrlVary, QueryParamVary, HeaderVary, CookieVary or ServiceVary, string given.',
        ];

        try {
            new ManifestBuilder([ManifestBuilderTest::fixture('CacheBroken')])->build();
            self::fail('BuildException expected.');
        } catch (BuildException $exception) {
            self::assertSame($expected, $exception->getErrors());
        }
    }
}
