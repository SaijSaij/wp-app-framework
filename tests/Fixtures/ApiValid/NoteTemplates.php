<?php

declare(strict_types=1);

namespace Saij\WpAppFramework\Tests\Fixtures\ApiValid;

use Saij\WpAppFramework\Twig\TemplateFilter;
use Saij\WpAppFramework\Twig\TemplateFunction;

final class NoteTemplates
{
    public function __construct(private readonly NoteApi $notes) {}

    #[TemplateFunction('my_plugin_note_count', public: true)]
    public function count(string $suffix = ''): string
    {
        return count($this->notes->getNotes()) . $suffix;
    }

    #[TemplateFunction('my_plugin_note_list', html: true, public: true)]
    public function list(): string
    {
        return '<ul><li>' . implode('</li><li>', array_map(static fn(Note $note): string => htmlspecialchars($note->text), $this->notes->getNotes())) . '</li></ul>';
    }

    #[TemplateFilter('my_plugin_highlight', html: true, public: true)]
    public function highlight(string $text, string $tag = 'mark'): string
    {
        return '<' . $tag . '>' . $text . '</' . $tag . '>';
    }

    #[TemplateFunction('internal_only')]
    public function internal(): string
    {
        return 'internal';
    }
}
