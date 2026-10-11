<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\SupportUseBroken;

use Saij\WpAppFramework\Block\Support\UseSupport;

#[UseSupport(Surface::class)]
final class WithoutBlock {}
