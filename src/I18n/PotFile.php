<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\I18n;

use Saij\WpAppFramework\DependencyInjection\NoService;

/**
 * The texts of a project to translate, as a POT file (gettext template): one entry per text, context and plural,
 * with the places it comes from and the comments for translators. Entries keep the order in which they were added.
 *
 * @internal
 *
 * @phpstan-type PotEntry array{context: string|null, text: string, plural: string|null, references: list<string>, comments: list<string>}
 */
#[NoService]
final class PotFile
{
    /** @var array<string, PotEntry> */
    private array $entries = [];

    /**
     * @param string|null $reference Where the text comes from, `src/Block/Map/Map.twig:3` or a file alone.
     * @param string|null $comment   A comment for translators.
     */
    public function add(string $text, ?string $context = null, ?string $plural = null, ?string $reference = null, ?string $comment = null): void
    {
        if ($text === '') {
            return;
        }
        $key = ($context ?? '') . "\4" . $text . "\0" . ($plural ?? '');
        $entry = $this->entries[$key] ?? ['context' => $context, 'text' => $text, 'plural' => $plural, 'references' => [], 'comments' => []];
        if ($reference !== null && !in_array($reference, $entry['references'], true)) {
            $entry['references'][] = $reference;
        }
        if ($comment !== null && $comment !== '' && !in_array($comment, $entry['comments'], true)) {
            $entry['comments'][] = $comment;
        }
        $this->entries[$key] = $entry;
    }

    public function count(): int
    {
        return count($this->entries);
    }

    /**
     * @return list<PotEntry>
     */
    public function getEntries(): array
    {
        return array_values($this->entries);
    }

    /**
     * The POT file, with the given headers (`Project-Id-Version` …) after the fixed ones.
     *
     * @param array<string, string> $headers
     */
    public function toString(array $headers): string
    {
        $header = [
            'MIME-Version' => '1.0',
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Content-Transfer-Encoding' => '8bit',
            ...$headers,
        ];
        $lines = ['msgid ""', 'msgstr ""'];
        foreach ($header as $name => $value) {
            $lines[] = self::quote($name . ': ' . $value . "\n");
        }
        $lines[] = '';
        foreach ($this->entries as $entry) {
            foreach ($entry['comments'] as $comment) {
                $lines[] = '#. ' . str_replace("\n", "\n#. ", $comment);
            }
            foreach ($entry['references'] as $reference) {
                $lines[] = '#: ' . $reference;
            }
            if ($entry['context'] !== null) {
                $lines[] = 'msgctxt ' . self::quote($entry['context']);
            }
            $lines[] = 'msgid ' . self::quote($entry['text']);
            if ($entry['plural'] === null) {
                $lines[] = 'msgstr ""';
            } else {
                $lines[] = 'msgid_plural ' . self::quote($entry['plural']);
                $lines[] = 'msgstr[0] ""';
                $lines[] = 'msgstr[1] ""';
            }
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    private static function quote(string $text): string
    {
        return '"' . strtr($text, ['\\' => '\\\\', '"' => '\\"', "\n" => '\\n', "\t" => '\\t', "\r" => '\\r']) . '"';
    }
}
