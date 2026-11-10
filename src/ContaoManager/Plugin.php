<?php

declare(strict_types=1);

namespace Hschottm\ContaoNewsletterExport\ContaoManager;

use Contao\ManagerPlugin\Bundle\BundlePluginInterface;
use Contao\ManagerPlugin\Bundle\Config\BundleConfig;
use Contao\ManagerPlugin\Bundle\Parser\ParserInterface;
use Contao\ManagerPlugin\Config\ConfigPluginInterface;
use Contao\NewsletterBundle\ContaoNewsletterBundle;
use Hschottm\ContaoNewsletterExport\ContaoNewsletterExportBundle;
use Symfony\Component\Config\Loader\LoaderInterface;

final class Plugin implements BundlePluginInterface, ConfigPluginInterface
{
    public function getBundles(ParserInterface $parser): array
    {
        return [BundleConfig::create(ContaoNewsletterExportBundle::class)
            ->setLoadAfter([ContaoNewsletterBundle::class])];
    }

    public function registerContainerConfiguration(LoaderInterface $loader, array $managerConfig): void
    {
        $loader->load(__DIR__.'/../../config/services.yaml');
    }
}
