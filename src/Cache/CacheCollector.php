<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Cache;

use ReflectionClass;
use ReflectionMethod;
use Saij\WpAppFramework\Block\Block;
use Saij\WpAppFramework\DependencyInjection\NoService;
use Saij\WpAppFramework\DependencyInjection\ServiceResolver;
use Saij\WpAppFramework\Rest\RestRoute;
use Saij\WpAppFramework\Setting\SettingsGroup;
use Throwable;

/**
 * Reads `#[Cache]` during the build: the block and REST route collectors ask
 * for the entry of their class or method; collect() reports `#[Cache]` on
 * anything else. The providers of ServiceVary are resolved as services.
 *
 * @phpstan-import-type CacheEntry from CachePolicy
 */
#[NoService]
final class CacheCollector
{
    /** @var list<string> */
    private array $errors = [];

    public function __construct(
        private readonly ServiceResolver $resolver,
        private readonly TagNames $names,
    ) {}

    /**
     * Names of the settings groups by class; invalid groups are reported by the settings collector.
     *
     * @param array<class-string, ReflectionClass<object>> $classes
     * @return array<class-string, string>
     */
    public static function settingsGroups(array $classes): array
    {
        $groups = [];
        foreach ($classes as $name => $class) {
            $attribute = $class->getAttributes(SettingsGroup::class)[0] ?? null;
            if ($attribute === null) {
                continue;
            }
            try {
                $groups[$name] = $attribute->newInstance()->name;
            } catch (Throwable) {
                continue;
            }
        }

        return $groups;
    }

    /**
     * Reports `#[Cache]` on classes without `#[Block]` and on methods without `#[RestRoute]`.
     *
     * @param array<class-string, ReflectionClass<object>> $classes All scanned classes.
     */
    public function collect(array $classes): void
    {
        foreach ($classes as $name => $class) {
            if ($class->getAttributes(Cache::class) !== [] && $class->getAttributes(Block::class) === []) {
                $this->errors[] = sprintf('%s has #[Cache], but no #[Block]; #[Cache] belongs on a block class or a #[RestRoute] method.', $name);
            }
            foreach ($class->getMethods() as $method) {
                if ($method->getDeclaringClass()->getName() !== $name) {
                    continue;
                }
                if ($method->getAttributes(Cache::class) !== [] && $method->getAttributes(RestRoute::class) === []) {
                    $this->errors[] = sprintf('%s::%s() has #[Cache], but no #[RestRoute]; #[Cache] belongs on a block class or a #[RestRoute] method.', $name, $method->getName());
                }
            }
        }
    }

    /**
     * The cache entry of a block class or route method.
     *
     * @param ReflectionClass<object>|ReflectionMethod $target
     * @return CacheEntry|false|null Null without #[Cache], false if it is invalid (reported).
     */
    public function entry(ReflectionClass|ReflectionMethod $target, string $location): array|false|null
    {
        $attribute = $target->getAttributes(Cache::class)[0] ?? null;
        if ($attribute === null) {
            return null;
        }

        try {
            $cache = $attribute->newInstance();
        } catch (Throwable $exception) {
            $this->errors[] = sprintf('Invalid #[Cache] on %s: %s', $location, $exception->getMessage());

            return false;
        }

        [$entry, $errors] = CachePolicy::entry(
            $cache->ttl,
            $cache->tags,
            $cache->vary,
            $this->names,
            fn(string $class): ?string => match (true) {
                !$this->resolver->isCandidate($class) => sprintf(
                    'the provider must be a service, but %s is %s',
                    $class,
                    $this->resolver->exclusion($class) ?? 'not in the scanned sources',
                ),
                !is_subclass_of($class, VaryProvider::class) => sprintf('%s does not implement VaryProvider', $class),
                default => null,
            },
        );
        if ($entry === null) {
            foreach ($errors as $error) {
                $this->errors[] = sprintf('#[Cache] on %s: %s.', $location, $error);
            }

            return false;
        }

        foreach ($entry['vary'] as $variant) {
            if ($variant['type'] === 'service') {
                $this->resolver->resolve($variant['class']);
            }
        }

        return $entry;
    }

    /**
     * @return list<string>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
