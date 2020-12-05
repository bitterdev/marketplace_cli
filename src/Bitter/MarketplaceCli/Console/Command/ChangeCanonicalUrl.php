<?php

/**
 * @project:   Marketplace CLI
 *
 * @author     Fabian Bitter (fabian@bitter.de)
 * @copyright  (C) 2018 Fabian Bitter
 * @version    1.0
 */

namespace Bitter\MarketplaceCli\Console\Command;

use Bitter\MarketplaceCli\MarketplaceDownloader;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Concrete\Core\Support\Facade\Application;

class ChangeCanonicalUrl extends Command
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('marketplace:change-canonical-url')
            ->setDescription(t("Change the canoncial url"))
            ->addArgument('canoncialUrl', InputArgument::REQUIRED, t('The canonical url'));
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $app = Application::getFacadeApplication();

        /** @var $marketplaceDownloader MarketplaceDownloader */
        $marketplaceDownloader = $app->make(MarketplaceDownloader::class);

        $exitCode = $marketplaceDownloader->changeCanonicalUrl($input->getArgument("canoncialUrl"));

        return $exitCode;
    }
}
