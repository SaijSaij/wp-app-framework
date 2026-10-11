<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\SettingsValid;

use Saij\WpAppFramework\Value\ListOf;

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
