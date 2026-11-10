<?php

declare(strict_types=1);

$operations = &$GLOBALS['TL_DCA']['tl_newsletter_recipients']['list']['global_operations'];
$export = [
    'label' => &$GLOBALS['TL_LANG']['tl_newsletter_recipients']['bccExport'],
    'href' => 'key=bccExport',
    'icon' => 'bundles/contaonewsletterexport/export.svg',
];

// Preserve other extensions' operations, inserting immediately after CSV import.
$position = array_search('import', array_keys($operations), true);
$position = false === $position ? count($operations) : $position + 1;
$operations = array_slice($operations, 0, $position, true)
    + ['bccExport' => $export]
    + array_slice($operations, $position, null, true);
unset($operations, $export, $position);
