<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Unit\Project;

use PHPUnit\Framework\TestCase;
use Saij\WpAppFramework\Project\NamePrefix;

final class NamePrefixTest extends TestCase
{
    public function testTemplatePrefixReplacesHyphens(): void
    {
        self::assertSame('plugin_dev_test_', NamePrefix::template('plugin-dev-test'));
    }

    public function testDataPrefixReplacesHyphens(): void
    {
        self::assertSame('dev_test_', NamePrefix::data('dev-test'));
    }
}
