<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Cache\Tag;

use Saij\WpAppFramework\Cache\CacheTag;
use Saij\WpAppFramework\DependencyInjection\NoService;

/**
 * Invalidated when the settings group (a class with #[SettingsGroup] of the
 * same theme or plugin) is saved or deleted.
 */
#[NoService]
final readonly class SettingsTag implements CacheTag
{
    /**
     * @param class-string $group
     */
    public function __construct(private string $group) {}

    /**
     * @return class-string
     */
    public function getGroup(): string
    {
        return $this->group;
    }
}
