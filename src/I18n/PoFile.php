<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\I18n;

use InvalidArgumentException;
use WebCodingCat\WpAppFramework\DependencyInjection\NoService;

/**
 * Reads a PO file (gettext) into the messages of a translation file of WordPress: the key is the original, with its
 * context in front (`context\4original`) and the plural after it (`singular\0plural`); the value is the translation,
 * plural forms joined with `\0`. Fuzzy and untranslated entries are left out, as gettext's msgfmt does.
 *
 * @internal
 *
 * @phpstan-type Entry array{msgctxt: string|null, msgid: string|null, msgid_plural: string|null, msgstr: array<int, string>, fuzzy: bool}
 */
#[NoService]
final readonly class PoFile
{
    /**
     * @param array<string, string> $headers  Headers of the file, lowercase names (`plural-forms`, `language`).
     * @param array<string, string> $messages
     */
    private function __construct(
        private array $headers,
        private array $messages,
    ) {}

    /**
     * @throws InvalidArgumentException With file and line of a line the parser does not understand.
     */
    public static function parse(string $content, string $file): self
    {
        $headers = [];
        $messages = [];
        $entry = self::emptyEntry();
        $field = null;
        foreach (preg_split('/\R/', $content) ?: [] as $index => $raw) {
            $line = trim($raw);
            $number = $index + 1;
            if ($line === '') {
                self::add($entry, $headers, $messages);
                $entry = self::emptyEntry();
                $field = null;
                continue;
            }
            if (str_starts_with($line, '#')) {
                if (str_starts_with($line, '#,') && str_contains($line, 'fuzzy')) {
                    $entry['fuzzy'] = true;
                }
                continue;
            }
            if (preg_match('/^(msgctxt|msgid_plural|msgid|msgstr(?:\[(\d+)\])?)\s+(".*")$/', $line, $match) === 1) {
                $keyword = $match[1];
                if ($keyword === 'msgctxt' || ($keyword === 'msgid' && $entry['msgid'] !== null)) {
                    // A new entry without blank line in between.
                    if ($entry['msgid'] !== null) {
                        self::add($entry, $headers, $messages);
                        $entry = self::emptyEntry();
                    }
                }
                $value = self::string($match[3], $file, $number);
                $field = match (true) {
                    str_starts_with($keyword, 'msgstr') => ['msgstr', $match[2] === '' ? 0 : (int) $match[2]],
                    default => [$keyword, null],
                };
                $entry = self::append($entry, $field, $value);
                continue;
            }
            if (str_starts_with($line, '"') && $field !== null) {
                $entry = self::append($entry, $field, self::string($line, $file, $number));
                continue;
            }
            throw new InvalidArgumentException(sprintf('%s:%d: not a line of a PO file: %s', $file, $number, $line));
        }
        self::add($entry, $headers, $messages);

        return new self($headers, $messages);
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * @return array<string, string>
     */
    public function getMessages(): array
    {
        return $this->messages;
    }

    /**
     * The content of a translation file of WordPress (`.l10n.php`, read by WP_Translation_File_PHP).
     */
    public function toPhp(): string
    {
        $data = [];
        foreach (['plural-forms', 'language', 'project-id-version'] as $name) {
            if (isset($this->headers[$name])) {
                $data[$name] = $this->headers[$name];
            }
        }
        $data['messages'] = $this->messages;

        return '<?php' . "\n" . 'return ' . var_export($data, true) . ';' . "\n";
    }

    /**
     * Adds a parsed entry: the header entry (empty msgid) to the headers, a translated entry to the messages.
     *
     * @param Entry                 $entry
     * @param array<string, string> $headers
     * @param array<string, string> $messages
     */
    private static function add(array $entry, array &$headers, array &$messages): void
    {
        if ($entry['msgid'] === null) {
            return;
        }
        if ($entry['msgid'] === '' && $entry['msgctxt'] === null) {
            foreach (explode("\n", $entry['msgstr'][0] ?? '') as $line) {
                if (str_contains($line, ':')) {
                    [$name, $value] = explode(':', $line, 2);
                    $headers[strtolower(trim($name))] = trim($value);
                }
            }

            return;
        }
        $translations = $entry['msgstr'];
        ksort($translations);
        if ($entry['fuzzy'] || $translations === [] || in_array('', $translations, true)) {
            return;
        }
        $key = ($entry['msgctxt'] === null ? '' : $entry['msgctxt'] . "\4")
            . $entry['msgid']
            . ($entry['msgid_plural'] === null ? '' : "\0" . $entry['msgid_plural']);
        $messages[$key] = implode("\0", $translations);
    }

    /**
     * @return Entry
     */
    private static function emptyEntry(): array
    {
        return ['msgctxt' => null, 'msgid' => null, 'msgid_plural' => null, 'msgstr' => [], 'fuzzy' => false];
    }

    /**
     * @param Entry                         $entry
     * @param array{0: string, 1: int|null} $field
     * @return Entry
     */
    private static function append(array $entry, array $field, string $value): array
    {
        [$name, $index] = $field;
        if ($name === 'msgstr') {
            $entry['msgstr'][(int) $index] = ($entry['msgstr'][(int) $index] ?? '') . $value;
        } elseif ($name === 'msgctxt' || $name === 'msgid' || $name === 'msgid_plural') {
            $entry[$name] = ($entry[$name] ?? '') . $value;
        }

        return $entry;
    }

    /**
     * A quoted string of a PO file with its escapes (`\n`, `\t`, `\"`, `\\`).
     */
    private static function string(string $quoted, string $file, int $line): string
    {
        if (preg_match('/^"((?:[^"\\\\]|\\\\.)*)"$/', $quoted, $match) !== 1) {
            throw new InvalidArgumentException(sprintf('%s:%d: not a quoted string: %s', $file, $line, $quoted));
        }

        return strtr($match[1], ['\\n' => "\n", '\\t' => "\t", '\\"' => '"', '\\\\' => '\\', '\\r' => "\r"]);
    }
}
