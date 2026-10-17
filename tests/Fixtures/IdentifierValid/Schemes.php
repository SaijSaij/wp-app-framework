<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\IdentifierValid;

use WebCodingCat\WpAppFramework\Setting\SettingsGroup;
use WebCodingCat\WpAppFramework\Value\ListOf;

#[SettingsGroup('schemes', title: 'Schemes')]
final readonly class Schemes
{
    /**
     * @param list<Scheme> $schemes
     */
    public function __construct(
        #[ListOf(Scheme::class, min: 1)]
        public array $schemes = [new Scheme('0a1b2c3d', 'Page'), new Scheme(name: 'Dark')],
    ) {}
}
