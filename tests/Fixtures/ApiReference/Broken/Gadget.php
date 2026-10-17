<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\ApiReference\Broken;

/**
 * @see guides/missing.md
 *
 * @api Services
 */
final class Gadget
{
    public const int LIMIT = 1;

    /**
     * Uses an internal class.
     *
     * @param Part $part The part.
     */
    public function attach(Part $part, int $count): void {}

    public function undocumented(): void {}
}
