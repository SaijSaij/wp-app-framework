<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Rest\Permission;

use Saij\WpAppFramework\DependencyInjection\NoService;

/**
 * The current user needs the capability, checked with current_user_can().
 */
#[NoService]
final readonly class Capability implements Permission
{
    public function __construct(private string $capability) {}

    public function getCapability(): string
    {
        return $this->capability;
    }
}
