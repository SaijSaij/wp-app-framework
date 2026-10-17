<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\SettingsValid;

use WebCodingCat\WpAppFramework\Value\ListOf;

final readonly class LinksAttributes
{
    /**
     * @param list<Link> $links
     */
    public function __construct(
        #[ListOf(Link::class)]
        public array $links = [],
    ) {}
}
