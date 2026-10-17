<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Setting;

use ArrayAccess;
use LogicException;
use WebCodingCat\WpAppFramework\DependencyInjection\NoService;

/**
 * The global `settings` of the templates of an instance: `settings.general`
 * is the group object of the group named "general", created on first use.
 *
 * @internal
 *
 * @implements ArrayAccess<string, object>
 */
#[NoService]
final readonly class SettingsAccessor implements ArrayAccess
{
    public function __construct(private SettingsStore $store) {}

    public function offsetExists(mixed $offset): bool
    {
        return is_string($offset) && isset($this->store->getClasses()[$offset]);
    }

    public function offsetGet(mixed $offset): object
    {
        $classes = $this->store->getClasses();
        if (!is_string($offset) || !isset($classes[$offset])) {
            throw new LogicException(sprintf(
                'There is no settings group "%s"; the groups are: %s.',
                is_scalar($offset) ? (string) $offset : get_debug_type($offset),
                implode(', ', array_keys($classes)),
            ));
        }

        return $this->store->get($classes[$offset]);
    }

    public function offsetSet(mixed $offset, mixed $value): never
    {
        throw new LogicException('Settings are read-only in templates.');
    }

    public function offsetUnset(mixed $offset): never
    {
        throw new LogicException('Settings are read-only in templates.');
    }
}
