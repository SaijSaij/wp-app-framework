<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\I18n;

use ReflectionClass;
use Saij\WpAppFramework\DependencyInjection\NoService;
use Saij\WpAppFramework\Manifest\BuildResult;

/**
 * Finds the texts of attributes in the result of the build – the texts the framework translates at run time (see
 * Translator): titles, descriptions and keywords of blocks and variations, block categories, labels of post types and
 * taxonomies, titles of meta classes and settings groups, and the labels of all controls. Each refers to the file of
 * the class that declares it.
 *
 * @internal
 */
#[NoService]
final readonly class AttributeTexts
{
    /** Keys of control descriptions whose strings are labels (as Translator::translateLabels()). */
    private const array LABEL_KEYS = ['label', 'itemLabel', 'title', 'description'];

    public function __construct(private string $projectDirectory) {}

    public function collect(BuildResult $result, PotFile $pot): void
    {
        foreach ($result->getBlocks() as $block) {
            $file = $this->file($block->getClass());
            $args = $block->getArgs();
            $this->entry($args, $file, $pot);
            foreach (is_array($args['variations'] ?? null) ? $args['variations'] : [] as $variation) {
                if (is_array($variation)) {
                    $this->entry($variation, $file, $pot);
                }
            }
            $this->labels($block->getEditor()['fields'], $file, $pot);
            $this->labels($block->getEditor()['controls'], $file, $pot);
        }
        foreach ($result->getBlockCategories() as $category) {
            $pot->add($category['title'], comment: 'Block category');
        }
        $posts = $result->getPosts();
        foreach ([...$posts['posts'], ...$posts['taxonomies']] as $class => $entry) {
            $args = $entry['args'] ?? null;
            if (!is_array($args)) {
                continue;
            }
            $file = $this->file($class);
            foreach (is_array($args['labels'] ?? null) ? $args['labels'] : [] as $label) {
                if (is_string($label)) {
                    $pot->add($label, reference: $file);
                }
            }
            if (is_string($args['description'] ?? null)) {
                $pot->add($args['description'], reference: $file);
            }
        }
        foreach ($posts['meta'] as $class => $meta) {
            $file = $this->file($class);
            $pot->add($meta['title'], reference: $file);
            $this->labels($meta['controls'], $file, $pot);
        }
        foreach ($result->getSettings() as $group) {
            $file = $this->file($group['class']);
            $pot->add($group['title'], reference: $file);
            $pot->add($group['description'], reference: $file);
            $this->labels($group['controls'], $file, $pot);
        }
    }

    /**
     * Title, description and keywords of a block or variation.
     *
     * @param array<mixed> $entry
     */
    private function entry(array $entry, ?string $file, PotFile $pot): void
    {
        foreach (['title', 'description'] as $key) {
            if (is_string($entry[$key] ?? null)) {
                $pot->add($entry[$key], reference: $file);
            }
        }
        foreach (is_array($entry['keywords'] ?? null) ? $entry['keywords'] : [] as $keyword) {
            if (is_string($keyword)) {
                $pot->add($keyword, reference: $file, comment: 'Search keyword of a block');
            }
        }
    }

    /**
     * Every label in a description of controls or fields, at any depth.
     *
     * @param array<mixed> $description
     */
    private function labels(array $description, ?string $file, PotFile $pot): void
    {
        foreach ($description as $key => $value) {
            if (is_array($value)) {
                $this->labels($value, $file, $pot);
            } elseif (is_string($value) && in_array($key, self::LABEL_KEYS, true)) {
                $pot->add($value, reference: $file);
            }
        }
    }

    /**
     * The file of a class relative to the project; null for a class outside it.
     *
     * @param class-string $class
     */
    private function file(string $class): ?string
    {
        $file = (string) new ReflectionClass($class)->getFileName();

        return str_starts_with($file, $this->projectDirectory . '/') ? substr($file, strlen($this->projectDirectory) + 1) : null;
    }
}
