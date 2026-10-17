<?php

declare(strict_types=1);

namespace WebCodingCat\WpAppFramework\Tests\Fixtures\TwigValid;

use WebCodingCat\WpAppFramework\Hook\Hook;
use WebCodingCat\WpAppFramework\Twig\TemplateRenderer;

final class Mailer
{
    public function __construct(private readonly TemplateRenderer $renderer) {}

    #[Hook('fixture_mail')]
    public function body(string $body): string
    {
        return $this->renderer->render('Mail.twig', ['body' => $body]);
    }
}
