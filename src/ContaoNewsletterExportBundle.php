<?php

declare(strict_types=1);

namespace Hschottm\ContaoNewsletterExport;

use Symfony\Component\HttpKernel\Bundle\Bundle;

final class ContaoNewsletterExportBundle extends Bundle
{
    public function getPath(): string
    {
        return dirname(__DIR__);
    }
}
