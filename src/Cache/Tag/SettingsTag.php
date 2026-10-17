<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Cache\Tag;

use WebCodingCat\WpAppFramework\Cache\CacheTag;
use WebCodingCat\WpAppFramework\DependencyInjection\NoService;

/**
 * Invalidated when the settings group (a class with #[SettingsGroup] of the
 * same theme or plugin) is saved or deleted.
 *
 * @api Cache
 */
#[NoService]
final readonly class SettingsTag implements CacheTag
{
    /**
     * @param class-string $group A class with `#[SettingsGroup]` of the same theme or plugin.
     */
    public function __construct(private string $group) {}

    /**
     * The class of the settings group.
     *
     * @return class-string
     */
    public function getGroup(): string
    {
        return $this->group;
    }
}
