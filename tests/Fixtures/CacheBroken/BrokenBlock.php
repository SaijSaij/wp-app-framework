<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\CacheBroken;

use WebCodingCat\WpAppFramework\Block\Block;
use WebCodingCat\WpAppFramework\Block\BlockCategory;
use WebCodingCat\WpAppFramework\Cache\Cache;
use WebCodingCat\WpAppFramework\Cache\Tag\PostTypeTag;
use WebCodingCat\WpAppFramework\Cache\Tag\SettingsTag;
use WebCodingCat\WpAppFramework\Cache\Tag\TaxonomyTag;
use WebCodingCat\WpAppFramework\Cache\Vary\CookieVary;
use WebCodingCat\WpAppFramework\Cache\Vary\HeaderVary;
use WebCodingCat\WpAppFramework\Cache\Vary\QueryParamVary;
use WebCodingCat\WpAppFramework\Cache\Vary\ServiceVary;
use WebCodingCat\WpAppFramework\Cache\Vary\UserVary;

#[Block('broken-cache', title: 'Broken cache', category: BlockCategory::Design)]
#[Cache(
    ttl: 0,
    // @phpstan-ignore argument.type (wrong on purpose: a string is no tag)
    tags: [
        new PostTypeTag(''),
        new TaxonomyTag('a_taxonomy_name_longer_than_32_chars'),
        new SettingsTag(NotAGroup::class),
        new PostTypeTag('post'),
        new PostTypeTag('post'),
        new ClassTag(),
        IntTag::One,
        'post',
    ],
    // @phpstan-ignore argument.type (wrong on purpose: a string is no variant)
    vary: [
        new QueryParamVary(''),
        new HeaderVary(''),
        new CookieVary(''),
        new UserVary(),
        new UserVary(),
        // @phpstan-ignore argument.type (wrong on purpose: no VaryProvider)
        new ServiceVary(NotAProvider::class),
        new ServiceVary(ExcludedProvider::class),
        'user',
    ],
)]
final class BrokenBlock
{
    public function render(): string
    {
        return '';
    }
}
