<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\SupportBroken;

use Saij\WpAppFramework\Block\Support\BlockSupport;
use Saij\WpAppFramework\DependencyInjection\NoService;

#[BlockSupport('notAService', label: 'X')]
#[NoService]
final class NotAService
{
    public function className(bool $value = false): ?string
    {
        return $value ? 'x' : null;
    }
}
