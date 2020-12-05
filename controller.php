<?php

/**
 * @project:   Marketplace CLI
 *
 * @author     Fabian Bitter (fabian@bitter.de)
 * @copyright  (C) 2018 Fabian Bitter
 * @version    1.0
 */

namespace Concrete\Package\MarketplaceCli;

use Bitter\MarketplaceCli\Provider\ServiceProvider;
use Concrete\Core\Package\Package;

class Controller extends Package
{
    protected $pkgHandle = 'marketplace_cli';
    protected $pkgVersion = '1.0';
    protected $appVersionRequired = '8.0.0';
    protected $pkgAutoloaderRegistries = [
        'src/Bitter/MarketplaceCli' => 'Bitter\MarketplaceCli',
    ];

    public function getPackageDescription()
    {
        return t("Download packages from concrete5.org marketplace by CLI command.");
    }

    public function getPackageName()
    {
        return t('Marketplace CLI');
    }

    public function on_start()
    {
        if (file_exists($this->getPackagePath() . '/vendor/autoload.php')) {
            require $this->getPackagePath() . '/vendor/autoload.php';
        }

        /** @var ServiceProvider $provider */
        $provider = $this->app->make(ServiceProvider::class);
        $provider->register();
    }
}
