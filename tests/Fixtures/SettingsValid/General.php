<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\SettingsValid;

use WebCodingCat\WpAppFramework\Setting\SettingsGroup;
use WebCodingCat\WpAppFramework\Value\Color;
use WebCodingCat\WpAppFramework\Value\Hidden;
use WebCodingCat\WpAppFramework\Value\Image;
use WebCodingCat\WpAppFramework\Value\Label;
use WebCodingCat\WpAppFramework\Value\Link as ContentLink;
use WebCodingCat\WpAppFramework\Value\ListOf;
use WebCodingCat\WpAppFramework\Value\PlainText;
use WebCodingCat\WpAppFramework\Value\PostReference;
use WebCodingCat\WpAppFramework\Value\RichText;
use WebCodingCat\WpAppFramework\Value\Url;

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
        #[Color]
        public string $brand = '#1e4d8c',
    ) {}
}
