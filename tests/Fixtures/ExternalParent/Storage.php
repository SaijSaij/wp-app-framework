<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\ExternalParent;

/**
 * A parent of PHP: loaded, a service candidate.
 *
 * @extends \ArrayObject<string, string>
 */
final class Storage extends \ArrayObject implements \JsonSerializable
{
    public function jsonSerialize(): mixed
    {
        return $this->getArrayCopy();
    }
}
