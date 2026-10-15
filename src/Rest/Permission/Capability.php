<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Rest\Permission;

use Saij\WpAppFramework\DependencyInjection\NoService;

/**
 * The current user needs the capability, checked with current_user_can().
 *
 * @api Values and exceptions
 */
#[NoService]
final readonly class Capability implements Permission
{
    /**
     * @param string $capability The capability the current user needs, e.g. `edit_posts`; checked with
     *                           `current_user_can()` without further arguments.
     */
    public function __construct(private string $capability) {}

    /**
     * The capability the current user needs.
     */
    public function getCapability(): string
    {
        return $this->capability;
    }
}
