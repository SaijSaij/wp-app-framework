<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Rest\Permission;

/**
 * Who may call a `#[RestRoute]`.
 *
 * Only the framework's implementations are supported: PublicAccess, Capability
 * and Callback. The build reports any other implementation as an error, because
 * the manifest cannot represent it.
 */
interface Permission
{
}
