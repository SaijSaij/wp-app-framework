<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Twig;

/**
 * Whether a template callable is a Twig function or a Twig filter.
 */
enum TemplateCallableKind: string
{
    case Function = 'function';
    case Filter = 'filter';

    /**
     * The attribute that declares this kind, for error messages.
     */
    public function getAttributeName(): string
    {
        return match ($this) {
            self::Function => '#[TemplateFunction]',
            self::Filter => '#[TemplateFilter]',
        };
    }
}
