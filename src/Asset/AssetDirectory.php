<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Asset;

use Saij\WpAppFramework\DependencyInjection\NoService;

/**
 * The `assets/` directory of a consumer, next to `src/`: everything of the
 * front end and the editor that belongs to no PHP class – the stylesheet
 * (assets/styles.css), scripts without a block and static files. `src/` keeps
 * what belongs to a class (code, Twig, the scripts of blocks and supports).
 *
 * @internal
 */
#[NoService]
final class AssetDirectory
{
    /** Name of the directory below the root of the consumer. */
    public const string NAME = 'assets';
}
