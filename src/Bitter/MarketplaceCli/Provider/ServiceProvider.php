<?php

/**
 * @project:   Marketplace CLI
 *
 * @author     Fabian Bitter (fabian@bitter.de)
 * @copyright  (C) 2018 Fabian Bitter
 * @version    1.0
 */

namespace Bitter\MarketplaceCli\Provider;

use Bitter\MarketplaceCli\Console\Command\ChangeCanonicalUrl;
use Bitter\MarketplaceCli\Console\Command\DownloadCommand;
use Concrete\Core\Application\ApplicationAwareInterface;
use Concrete\Core\Application\ApplicationAwareTrait;
use Concrete\Core\Console\Application as ConsoleApplication;

class ServiceProvider implements ApplicationAwareInterface
{
    use ApplicationAwareTrait;

    public function register()
    {
        if (PHP_SAPI == 'cli') {
            /** @var $console ConsoleApplication */
            $console = $this->app->make("console");

            $console->add(new DownloadCommand());
            $console->add(new ChangeCanonicalUrl());
        }
    }
}
