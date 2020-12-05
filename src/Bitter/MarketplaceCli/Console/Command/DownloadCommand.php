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

class DownloadCommand extends Command
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('marketplace:download')
            ->setDescription(t("Downloads a marketplace package from concrete5.org"))
            ->addArgument('packageHandle', InputArgument::REQUIRED, t('The requested package handle'))
            ->addArgument('username', InputArgument::REQUIRED, t('Username for concrete5.org'))
            ->addArgument('password', InputArgument::REQUIRED, t('Password for concrete5.org'));
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $app = Application::getFacadeApplication();

        /** @var $marketplaceDownloader MarketplaceDownloader */
        $marketplaceDownloader = $app->make(MarketplaceDownloader::class);

        $marketplaceDownloader->setInput($input);
        $marketplaceDownloader->setOutput($output);

        $marketplaceDownloader->setPackageHandle($input->getArgument("packageHandle"));
        $marketplaceDownloader->setUsername($input->getArgument("username"));
        $marketplaceDownloader->setPassword($input->getArgument("password"));

        $exitCode = $marketplaceDownloader->download();

        return $exitCode;
    }
}
