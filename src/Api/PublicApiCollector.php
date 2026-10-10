<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Api;

use ReflectionClass;
use Saij\WpAppFramework\DependencyInjection\NoService;
use Saij\WpAppFramework\DependencyInjection\ServiceResolver;
use Saij\WpAppFramework\Manifest\EntryPointInspector;
use Saij\WpAppFramework\Project\ProjectKind;

/**
 * Collects the `#[PublicApi]` services of a plugin or theme during the build.
 */
#[NoService]
final class PublicApiCollector
{
    /** @var list<string> */
    private array $errors = [];

    public function __construct(
        private readonly ServiceResolver $resolver,
        private readonly PublicSignatureInspector $signatures,
        private readonly ProjectKind $kind,
    ) {}

    /**
     * @param array<class-string, ReflectionClass<object>> $classes All scanned classes.
     * @return list<class-string> Sorted.
     */
    public function collect(array $classes): array
    {
        $apis = [];
        $inspector = new EntryPointInspector($this->resolver);

        foreach ($classes as $name => $class) {
            if ($class->getAttributes(PublicApi::class) === []) {
                continue;
            }

            if (!$this->kind->isProvider()) {
                $this->errors[] = sprintf('%s has #[PublicApi], but only plugins and themes provide public APIs; this project is a %s.', $name, $this->kind->getLabel());
                continue;
            }

            $error = $inspector->serviceError($name, $name, '#[PublicApi]');
            if ($error !== null) {
                $this->errors[] = $error;
                continue;
            }

            $signatureErrors = $this->signatures->classErrors($class, sprintf('public API %s', $name));
            if ($signatureErrors !== []) {
                array_push($this->errors, ...$signatureErrors);
                continue;
            }

            $this->resolver->resolve($name);
            $apis[] = $name;
        }

        sort($apis, SORT_STRING);

        return $apis;
    }

    /**
     * @return list<string>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}
