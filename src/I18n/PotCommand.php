<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\I18n;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use WebCodingCat\WpAppFramework\DependencyInjection\NoService;
use WebCodingCat\WpAppFramework\Filesystem\AtomicFile;
use WebCodingCat\WpAppFramework\Manifest\BuildException;
use WebCodingCat\WpAppFramework\Manifest\ManifestBuilder;
use WebCodingCat\WpAppFramework\Project\ComposerProject;
use WebCodingCat\WpAppFramework\Release\FileHeader;

/**
 * `wp-app-framework pot`: writes `languages/{text domain}.pot`, the template of the translations of the project, with
 * the texts of its attributes, its templates and its PHP files. Translators copy it to `{domain}-{locale}.po` and
 * translate it with a PO editor; an existing PO file takes new texts from it with the editor's "update from POT".
 *
 * @internal
 */
#[NoService]
final readonly class PotCommand
{
    /**
     * @return array{file: string, count: int, notices: list<string>}
     * @throws BuildException
     */
    public static function run(string $projectDirectory): array
    {
        $project = ComposerProject::load($projectDirectory);
        $root = $project->getDirectory();
        $result = ManifestBuilder::forProject($root)->build();
        $domain = $result->getTextDomain();
        if ($domain === null) {
            throw new BuildException([sprintf('The %s has no Text Domain header.', $project->getKind()->getLabel())]);
        }

        $pot = new PotFile();
        $header = $project->getHeaderFile();
        $headerName = substr($header, strlen($root) + 1);
        $kind = $project->getKind()->getLabel();
        $name = $project->getName();
        if ($name !== null) {
            $pot->add($name, reference: $headerName, comment: sprintf('Name of the %s', $kind));
        }
        $description = is_file($header) ? FileHeader::value($header, 'Description') : null;
        if ($description !== null) {
            $pot->add($description, reference: $headerName, comment: sprintf('Description of the %s', $kind));
        }

        new AttributeTexts($root)->collect($result, $pot);

        $templates = new TemplateTexts();
        $scriptDirectory = $result->getScriptDirectory();
        if ($scriptDirectory !== null) {
            $templates->collect($scriptDirectory, substr($scriptDirectory, strlen($root) + 1), $pot);
        }

        $files = [];
        $framework = ManifestBuilder::frameworkSource()->getDirectory();
        foreach ($result->getSources() as $source) {
            if ($source->getDirectory() !== $framework && is_dir($source->getDirectory())) {
                array_push($files, ...self::phpFiles($source->getDirectory()));
            }
        }
        $boot = $root . '/' . $project->getKind()->getBootFile($project->getSlug());
        if (is_file($boot)) {
            $files[] = $boot;
        }
        sort($files, SORT_STRING);
        foreach (array_unique($files) as $file) {
            PhpTexts::collect($file, substr($file, strlen($root) + 1), $domain, $pot);
        }

        $file = $root . '/' . TranslationLoader::DIRECTORY . '/' . $domain . '.pot';
        AtomicFile::write($file, $pot->toString([
            'Project-Id-Version' => trim(($name ?? $project->getSlug()) . ' ' . ($project->getVersion() ?? '')),
            'X-Domain' => $domain,
        ]), createDirectory: true);

        return ['file' => $file, 'count' => $pot->count(), 'notices' => $templates->notices()];
    }

    /**
     * @return list<string>
     */
    private static function phpFiles(string $directory): array
    {
        $files = [];
        /** @var SplFileInfo $file */
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }
}
