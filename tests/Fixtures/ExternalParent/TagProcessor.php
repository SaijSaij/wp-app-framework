<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\ExternalParent;

// The parent is loaded in the unit tests (a test double), as WordPress classes are in development mode: the class is
// still not loaded by the build.
final class TagProcessor extends \WP_HTML_Tag_Processor {}
