<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Release;

use Throwable;
use WebCodingCat\WpAppFramework\DependencyInjection\Container;
use WebCodingCat\WpAppFramework\DependencyInjection\NoService;
use WebCodingCat\WpAppFramework\Manifest\Manifest;
use WebCodingCat\WpAppFramework\Manifest\ManifestBuilder;
use WebCodingCat\WpAppFramework\Project\ProjectKind;
use WebCodingCat\WpAppFramework\Twig\TemplateFiles;
use WebCodingCat\WpAppFramework\Twig\TemplateRenderer;

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
        // Created also without templates: the release checks the compiled templates in this directory.
        if (!mkdir($outputDirectory, 0777, true)) {
            throw new ReleaseException(sprintf('Cannot create the output directory %s.', $outputDirectory));
        }

        $manifest = Manifest::load($manifestFile);
        $kind = ProjectKind::from($manifest['kind']);
        $slug = $manifest['slug'] ?? throw new ReleaseException(sprintf('The manifest %s names no project slug.', $manifestFile));
        $frameworkTemplates = ManifestBuilder::frameworkSource()->getDirectory();
        $projectTemplates = $projectDirectory . '/' . TemplateRenderer::TEMPLATE_DIRECTORY;
        // Overrides of templates of other instances are compiled by those instances at runtime, with their functions.
        $renderer = new TemplateRenderer(
            $slug,
            $projectDirectory,
            [],
            $projectTemplates,
            $kind->getTemplateNamespace(),
            $frameworkTemplates,
            $outputDirectory,
            false,
            $manifest['templateCallables'],
            $manifest['templateProviders'],
            new Container($manifest['services']),
        );

        $names = [];
        if (is_dir($projectTemplates)) {
            $names = TemplateFiles::names($projectTemplates);
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
