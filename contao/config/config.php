<?php

declare(strict_types=1);

use Hschottm\ContaoNewsletterExport\Backend\BccExport;

$GLOBALS['BE_MOD']['content']['newsletter']['bccExport'] = [BccExport::class, 'export'];
