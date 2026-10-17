<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\SupportUseBroken;

use WebCodingCat\WpAppFramework\Block\Support\UseSupport;

#[UseSupport(Surface::class)]
final class WithoutBlock {}
