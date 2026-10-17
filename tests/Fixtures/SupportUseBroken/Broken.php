<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\SupportUseBroken;

use WebCodingCat\WpAppFramework\Block\Support\BlockSupport;

/** Invalid declaration; using it reports no second error. */
#[BlockSupport('broken', label: 'Broken')]
final class Broken {}
