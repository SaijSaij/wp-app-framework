<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Release;

use JsonException;
use WebCodingCat\WpAppFramework\DependencyInjection\NoService;

/**
 * The Strauss settings of a consumer (`extra.strauss` in its composer.json).
 *
 * The consumer declares them itself – `extra` is part of the lock file's
 * content hash, so the build cannot add them to its copy. The release build
 * relies on a few values and checks them instead of changing anything.
 */
#[NoService]
final class StraussConfig
{
    public const string TARGET_DIRECTORY = 'vendor-prefixed';

    /**
     * @param non-empty-string $namespacePrefix With a trailing backslash.
     */
    private function __construct(
        private readonly string $namespacePrefix,
    ) {}

    /**
     * @param string $bootFile File that boots the framework (functions.php, {slug}.php); its call must be rewritten.
     */
    public static function fromComposerFile(string $composerFile, string $bootFile): self
    {
        if (!is_file($composerFile)) {
            throw new ReleaseException(sprintf('%s not found.', $composerFile));
        }
        try {
            $composer = json_decode((string) file_get_contents($composerFile), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new ReleaseException(sprintf('%s is not valid JSON: %s', $composerFile, $exception->getMessage()));
        }
        if (!is_array($composer)) {
            throw new ReleaseException(sprintf('%s does not contain a JSON object.', $composerFile));
        }

        $extra = is_array($composer['extra'] ?? null) ? $composer['extra'] : [];
        $strauss = $extra['strauss'] ?? null;
        if (!is_array($strauss)) {
            throw new ReleaseException(sprintf('%s has no extra.strauss settings; the release build prefixes all dependencies with Strauss.', $composerFile));
        }

        $errors = [];
        $expect = static function (string $key, mixed $expected) use ($strauss, &$errors): void {
            if (($strauss[$key] ?? null) !== $expected) {
                $errors[] = sprintf('extra.strauss.%s must be %s, found %s.', $key, json_encode($expected), json_encode($strauss[$key] ?? null));
            }
        };
        $expect('target_directory', self::TARGET_DIRECTORY);
        // The theme's own classes are loaded through the prefixed autoloader as well.
        $expect('include_root_autoload', true);
        // The build removes vendor/ itself, after the editor scripts are built from it.
        foreach (['delete_vendor_packages', 'delete_vendor_files'] as $key) {
            if (($strauss[$key] ?? false) !== false) {
                $errors[] = sprintf('extra.strauss.%s must be false or absent, found %s.', $key, json_encode($strauss[$key]));
            }
        }

        $callSites = $strauss['update_call_sites'] ?? null;
        if (!is_array($callSites) || $callSites === [] || !array_is_list($callSites) || array_filter($callSites, 'is_string') !== $callSites) {
            $errors[] = sprintf(
                'extra.strauss.update_call_sites must list the directories and files whose references to dependencies are rewritten (e.g. ["src", "%s"]), found %s.',
                $bootFile,
                json_encode($callSites),
            );
        } elseif (!in_array($bootFile, $callSites, true)) {
            $errors[] = sprintf(
                'extra.strauss.update_call_sites must contain "%s", which boots the framework, found %s.',
                $bootFile,
                json_encode($callSites),
            );
        }

        $prefix = $strauss['namespace_prefix'] ?? null;
        if (!is_string($prefix) || preg_match('/^(?:[A-Za-z_][A-Za-z0-9_]*\\\\)+$/', $prefix) !== 1) {
            $errors[] = sprintf('extra.strauss.namespace_prefix must be a namespace with a trailing backslash (e.g. "Vendor\\\\Theme\\\\Vendor\\\\"), found %s.', json_encode($prefix));
        } else {
            $autoload = is_array($composer['autoload'] ?? null) ? $composer['autoload'] : [];
            $psr4 = is_array($autoload['psr-4'] ?? null) ? $autoload['psr-4'] : [];
            foreach (array_keys($psr4) as $namespace) {
                if (rtrim((string) $namespace, '\\') . '\\' === $prefix) {
                    $errors[] = sprintf('extra.strauss.namespace_prefix "%s" is the namespace of the project itself; use a sub-namespace such as "%sVendor\\".', $prefix, $prefix);
                }
            }
        }

        if ($errors !== [] || !is_string($prefix) || $prefix === '') {
            throw new ReleaseException(sprintf("Strauss settings in %s:\n- %s", $composerFile, implode("\n- ", $errors)));
        }

        return new self($prefix);
    }

    /**
     * @return non-empty-string With a trailing backslash.
     */
    public function getNamespacePrefix(): string
    {
        return $this->namespacePrefix;
    }
}
