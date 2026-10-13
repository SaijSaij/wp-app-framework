<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\CacheBroken;

use Saij\WpAppFramework\Block\Block;
use Saij\WpAppFramework\Block\BlockCategory;
use Saij\WpAppFramework\Cache\Cache;
use Saij\WpAppFramework\Cache\Tag\PostTypeTag;
use Saij\WpAppFramework\Cache\Tag\SettingsTag;
use Saij\WpAppFramework\Cache\Tag\TaxonomyTag;
use Saij\WpAppFramework\Cache\Vary\CookieVary;
use Saij\WpAppFramework\Cache\Vary\HeaderVary;
use Saij\WpAppFramework\Cache\Vary\QueryParamVary;
use Saij\WpAppFramework\Cache\Vary\ServiceVary;
use Saij\WpAppFramework\Cache\Vary\UserVary;

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
