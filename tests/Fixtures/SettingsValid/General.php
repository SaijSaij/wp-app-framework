<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\SettingsValid;

use Saij\WpAppFramework\Block\Hidden;
use Saij\WpAppFramework\Block\Image;
use Saij\WpAppFramework\Block\Label;
use Saij\WpAppFramework\Block\PlainText;
use Saij\WpAppFramework\Block\RichText;
use Saij\WpAppFramework\Setting\SettingsGroup;
use Saij\WpAppFramework\Value\Link as ContentLink;
use Saij\WpAppFramework\Value\ListOf;
use Saij\WpAppFramework\Value\PostReference;
use Saij\WpAppFramework\Value\Url;

#[SettingsGroup('general', title: 'General', description: 'Main settings.', position: 20)]
final readonly class General
{
    /**
     * @param list<Link> $links
     * @param list<int>  $featured
     */
    public function __construct(
        #[Label('Tagline')]
        public string $tagline = '',
        public Density $density = Density::Normal,
        #[Url]
        public string $contactUrl = '',
        public int $count = 3,
        public ?float $ratio = null,
        #[ListOf(Link::class)]
        public array $links = [],
        #[Hidden]
        public string $secret = 'x',
        #[Image]
        public ?int $logo = null,
        #[PlainText]
        public string $footer = '',
        #[RichText(allowedFormats: ['core/bold', 'core/link'])]
        public string $notice = '',
        #[PostReference(postTypes: ['page'])]
        public ?int $privacyPage = null,
        #[PostReference]
        public array $featured = [],
        public ContentLink $imprint = new ContentLink(),
        #[PlainText(multiline: true)]
        public string $address = '',
    ) {}
}
