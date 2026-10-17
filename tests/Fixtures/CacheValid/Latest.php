<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\CacheValid;

use RuntimeException;
use WebCodingCat\WpAppFramework\Block\Block;
use WebCodingCat\WpAppFramework\Block\BlockCategory;
use WebCodingCat\WpAppFramework\Cache\Cache;
use WebCodingCat\WpAppFramework\Cache\Tag\PostTypeTag;
use WebCodingCat\WpAppFramework\Cache\Tag\SettingsTag;
use WebCodingCat\WpAppFramework\Cache\Vary\QueryParamVary;
use WebCodingCat\WpAppFramework\Cache\Vary\ServiceVary;
use WebCodingCat\WpAppFramework\View\ViewData;
use WP_Block;

/** Counts its renders and shows the count; sets view data with the attribute data. */
#[Block('latest', title: 'Latest', category: BlockCategory::Design, usesContext: ['postId'])]
#[Cache(
    ttl: 60,
    tags: [new PostTypeTag('post'), new SettingsTag(SiteSettings::class), Feed::Weather],
    vary: [new QueryParamVary('page'), new ServiceVary(Region::class)],
)]
final class Latest
{
    public function __construct(
        private readonly ViewData $viewData,
    ) {}

    public function render(LatestAttributes $attributes, string $content, WP_Block $block): string
    {
        if ($attributes->fail) {
            throw new RuntimeException('fixture cache failure');
        }
        $count = Renders::count('fixture/latest');
        if ($attributes->enqueue) {
            wp_enqueue_style('fixture-latest');
        }
        if ($attributes->data !== '') {
            $this->viewData->set('latest', ['title' => $attributes->data]);
        }

        return sprintf('<div>%s|%s|%d</div>', esc_html($attributes->title), $content, $count);
    }
}
