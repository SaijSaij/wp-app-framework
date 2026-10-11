<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\SupportUseBroken;

use Saij\WpAppFramework\Block\Support\BlockSupport;

/** Invalid declaration; using it reports no second error. */
#[BlockSupport('broken', label: 'Broken')]
final class Broken {}
