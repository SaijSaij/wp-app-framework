<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\PostTypeValid;

final readonly class EventTeaserAttributes
{
    public function __construct(
        public ?Event $event = null,
    ) {}
}
