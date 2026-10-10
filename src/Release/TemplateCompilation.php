<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Release;

use Saij\WpAppFramework\DependencyInjection\Container;
use Saij\WpAppFramework\DependencyInjection\NoService;
use Saij\WpAppFramework\Manifest\Manifest;
use Saij\WpAppFramework\Manifest\ManifestBuilder;
use Saij\WpAppFramework\Twig\TemplateFiles;
use Saij\WpAppFramework\Twig\TemplateRenderer;
use Throwable;

/**
 * Compiles all templates of a release build copy as release mode would, so the
 * release build can check the generated code (`compile-templates`).
 *
 * Runs in a separate PHP process with the prefixed autoloader of the copy. It
 * also fails on templates that would fail at runtime, e.g. unknown functions.
 */
#[NoService]
final class TemplateCompilation
{
    /**
     * @param string $manifestFile    The release manifest of the copy (template functions and filters).
     * @param string $outputDirectory Must not exist; receives the compiled templates.
     * @return int Number of compiled templates.
     */
    public static function compile(string $projectDirectory, string $manifestFile, string $outputDirectory): int
    {
        if (file_exists($outputDirectory)) {
            throw new ReleaseException(sprintf('Output directory %s already exists.', $outputDirectory));
        }

        $manifest = Manifest::load($manifestFile);
        $frameworkTemplates = ManifestBuilder::frameworkSource()->getDirectory();
        $renderer = new TemplateRenderer(
            $projectDirectory,
            $projectDirectory,
            $frameworkTemplates,
            $outputDirectory,
            false,
            $manifest['templateCallables'],
            new Container($manifest['services']),
        );

        $names = [];
        $themeTemplates = $projectDirectory . '/' . TemplateRenderer::TEMPLATE_DIRECTORY;
        if (is_dir($themeTemplates)) {
            $names = TemplateFiles::names($themeTemplates);
        }
        // Framework templates by their exact name, also where the theme overrides them.
        foreach (TemplateFiles::names($frameworkTemplates) as $name) {
            $names[] = '@framework/' . $name;
        }

        foreach ($names as $name) {
            try {
                $renderer->compile($name);
            } catch (Throwable $exception) {
                throw new ReleaseException(sprintf('Template %s does not compile in release mode: %s', $name, $exception->getMessage()), 0, $exception);
            }
        }

        return count($names);
    }
}
