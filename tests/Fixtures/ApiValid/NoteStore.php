<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\ApiValid;

use WebCodingCat\WpAppFramework\Twig\TemplateRenderer;

final class NoteStore
{
    /**
     * A constructor is no part of the public signature: framework types are fine here.
     */
    public function __construct(private readonly TemplateRenderer $renderer) {}

    /**
     * @return list<Note>
     */
    public function all(): array
    {
        return [new Note('first'), new Note('<second>')];
    }

    public function getRenderer(): TemplateRenderer
    {
        return $this->renderer;
    }
}
