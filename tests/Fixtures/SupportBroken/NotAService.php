<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\SupportBroken;

use WebCodingCat\WpAppFramework\Block\Support\BlockSupport;
use WebCodingCat\WpAppFramework\DependencyInjection\NoService;

#[BlockSupport('notAService', label: 'X')]
#[NoService]
final class NotAService
{
    public function className(bool $value = false): ?string
    {
        return $value ? 'x' : null;
    }
}
