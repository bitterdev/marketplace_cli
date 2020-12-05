<?php

/**
 * @project:   Marketplace CLI
 *
 * @author     Fabian Bitter (fabian@bitter.de)
 * @copyright  (C) 2018 Fabian Bitter
 * @version    1.0
 */

namespace Bitter\MarketplaceCli;

use Bitter\MarketplaceCli\Enumerations\ExitCodes;
use Concrete\Core\Marketplace\RemoteItem;
use Concrete\Core\Console\ConsoleAwareInterface;
use Concrete\Core\Console\ConsoleAwareTrait;
use Concrete\Core\Application\ApplicationAwareInterface;
use Concrete\Core\Application\ApplicationAwareTrait;
use Concrete\Core\Marketplace\Marketplace;
use Concrete\Core\Config\Repository\Repository;
use Concrete\Core\Url\Resolver\PathUrlResolver;
use Concrete\Core\Permission\Key\Key;
use Concrete\Core\Application\Application;
use Concrete\Core\Http\Client\Client;
use PhpQuery\PhpQuery as phpQuery;
use PhpQuery\PhpQueryObject;
use Zend\Http\Request;
use Zend\Http\Response;

class MarketplaceDownloader implements ApplicationAwareInterface, ConsoleAwareInterface
{
    use ConsoleAwareTrait;
    use ApplicationAwareTrait;

    /** @var string */
    protected $packageHandle = '';

    /** @var string */
    protected $packageName = '';

    /** @var string */
    protected $username;

    /** @var string */
    protected $password;

    /** @var Marketplace */
    protected $marketplace;

    /** @var Repository */
    protected $config;

    /** @var PathUrlResolver */
    protected $urlResolver;

    /** @var Client */
    protected $httpClient;

    public function __construct(
        Application $app,
        PathUrlResolver $urlResolver,
        Client $httpClient,
        Repository $config
    ) {
        $this->setApplication($app);

        $this->urlResolver = $urlResolver;
        $this->httpClient = $httpClient;
        $this->config = $config;
        $this->marketplace = Marketplace::getInstance();
    }

    /**
     * @return string
     */
    private function getPackageName()
    {
        return $this->packageName;
    }

    /**
     * @param string $packageName
     */
    private function setPackageName($packageName)
    {
        $this->packageName = $packageName;
    }

    /**
     * @return string
     */
    public function getUsername()
    {
        return $this->username;
    }

    /**
     * @param string $username
     */
    public function setUsername($username)
    {
        $this->username = $username;
    }

    /**
     * @return string
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * @param string $password
     */
    public function setPassword($password)
    {
        $this->password = $password;
    }

    /**
     * @return string
     */
    public function getPackageHandle()
    {
        return $this->packageHandle;
    }

    /**
     * @param string $packageHandle
     */
    public function setPackageHandle($packageHandle)
    {
        $this->packageHandle = $packageHandle;
    }

    /**
     * @return string
     */
    private function getPackageSlug()
    {
        return str_replace("_", "-", $this->getPackageHandle());
    }

    /**
     * @param $url
     * @param array|null $postData
     *
     * @return string
     */
    private function fetchUrl($url, $postData = null)
    {
        $html = "";

        $this->httpClient->setUri($url);

        if (!is_null($postData)) {
            $this->httpClient->setMethod(Request::METHOD_POST);
            $this->httpClient->setParameterPost($postData);
        }

        $response = $this->httpClient->send();

        if ($response instanceof Response) {
            $cookie = $response->getCookie();

            if ($cookie instanceof \ArrayIterator || is_array($cookie)) {
                $this->httpClient->addCookie($cookie);
            }

            $html = $response->getBody();
        }

        return $html;
    }

    /**
     *
     * @param string $url
     * @param array|null $postData
     *
     * @return PhpQueryObject
     */
    private function fetchDOM($url, $postData = null)
    {
        $html = $this->fetchUrl($url, $postData);
        $doc = phpQuery::newDocument($html);
        return $doc;
    }

    /**
     * @return bool
     */
    private function login()
    {
        $success = false;

        try {
            // login to concrete5
            $doc = $this->fetchDOM(
                "https://www.concrete5.org/login/-/do_login/",

                array(
                    "ccm_token" => $this->fetchDOM("https://www.concrete5.org/login/")->find("input[name=ccm_token]")->val(),
                    "uName" => $this->getUsername(),
                    "uPassword" => $this->getPassword(),
                    "rcID" => ""
                )
            );

            $success = $doc->find(".alert-error")->length === 0;
        } catch (\Exception $e) {
            // Do Nothing
        }

        return $success;
    }

    private function logout()
    {
        try {
            // logout from concrete5
            $this->fetchUrl("https://www.concrete5.org/logout");
        } catch (\Exception $e) {
            // Do Nothing
        }
    }

    /**
     * @return bool
     */
    private function isConnectedToCommunity()
    {
        return $this->marketplace->isConnected();
    }

    /**
     * @return bool
     */
    private function connectToCommunity()
    {
        $success = false;

        try {
            $doc = $this->fetchDOM(
                sprintf(
                    "%s%s/-/do_connect/",
                    $this->config->get('concrete.urls.concrete5'),
                    $this->config->get('concrete.urls.paths.marketplace.connect')
                ),

                [
                    "ts" => time(),
                    "csiURL" => str_replace("/index.php", "", (string)$this->urlResolver->resolve(['/'])),
                    "csiBaseURL" => str_replace("/index.php", "", (string)$this->urlResolver->resolve(['/'])),
                    "csToken" => $this->fetchUrl(
                        $this->config->get('concrete.urls.concrete5') .
                        $this->config->get('concrete.urls.paths.marketplace.connect_new_token')
                    ),
                    "csReferrer" => sprintf(
                        "%s?%s",
                        (string)$this->urlResolver->resolve(['/dashboard/extend/connect', 'connect_complete']),
                        http_build_query(
                            [
                                "ccm_token" => $this->app->make('token')->generate('marketplace/connect')
                            ]
                        )
                    ),
                    "siteAccount" => "login",
                    "csName" => $this->app->make('site')->getSite()->getSiteName(),
                    "uName" => $this->getUsername(),
                    "uPassword" => $this->getPassword(),
                    "agreedToTerms" => 1
                ]
            );

            if ($doc->find(".ccm-error")->length === 0) {
                $csToken = $doc->find("input[name=csToken]")->val();
                $csURLToken = $doc->find("input[name=csURLToken]")->val();

                /** @var $config Repository */
                $config = $this->app->make('config/database');

                $config->save('concrete.marketplace.token', $csToken);
                $config->save('concrete.marketplace.url_token', $csURLToken);

                $success = true;
            }
        } catch (\Exception $err) {
        }

        return $success;
    }

    /**
     * @return bool
     */
    private function licenseAssignedToProject()
    {
        $doc = $this->fetchDOM($this->marketplace->getSitePageURL());

        foreach ($doc->find("h3 > a") as $a) {
            if (strpos(\PhpQuery\pq($a)->attr("href"), $this->getPackageSlug()) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return bool|string
     */
    private function licenseExists()
    {
        $page = 1;

        while (true) {
            $doc = $this->fetchDOM(sprintf("https://www.concrete5.org/profile/orders/?ccm_paging_p=%s", $page));

            foreach ($doc->find("article a") as $a) {
                if (strpos(\PhpQuery\pq($a)->attr("href"), $this->getPackageSlug()) !== false) {
                    $this->setPackageName(trim(\PhpQuery\pq($a)->text()));

                    return true;
                }
            }

            if ($doc->find(".next > span")->hasClass("ltgray")) {
                break;
            } else {
                $page++;
            }
        }

        return false;
    }

    /**
     * @return bool
     */
    private function assignLicenseToProject()
    {
        $doc = $this->fetchDOM($this->marketplace->getSitePageURL());

        foreach ($doc->find("#mpLicenseID option") as $option) {
            if (strpos(\PhpQuery\pq($option)->text(), $this->getPackageName()) !== false) {
                $licenseId = \PhpQuery\pq($option)->val();

                $doc = $this->fetchDOM(
                    sprintf(
                        "%s/-/associatelicense",
                        $this->marketplace->getSitePageURL()
                    ),

                    [
                        "mpLicenseID" => $licenseId,
                        "submit" => "Associate >"
                    ]
                );

                return $doc->find(".alert-danger")->length === 0;
            }
        }

        return false;
    }

    private function downloadArchive()
    {
        foreach (Marketplace::getAvailableMarketplaceItems() as $item) {
            /** @var $item \Concrete\Core\Marketplace\RemoteItem */
            if ($item->getHandle() === $this->getPackageHandle()) {
                $mri = RemoteItem::getByID($item->getMarketplaceItemID());
                $mri->download();
                return true;
            }
        }

        return false;
    }

    /**
     * @param $url
     *
     * @return int
     */
    public function changeCanonicalUrl($url)
    {
        /** @var $siteConfig \Concrete\Core\Site\Config\Liaison */
        $siteConfig = $this->app->make('site')->getDefault()->getConfigRepository();

        $siteConfig->save('seo.canonical_url', $url);

        return ExitCodes::SUCCESS;
    }

    /**
     * @return string
     */
    private function getCanonicalUrl()
    {
        /** @var $siteConfig \Concrete\Core\Site\Config\Liaison */
        $siteConfig = $this->app->make('site')->getDefault()->getConfigRepository();

        return $siteConfig->get('seo.canonical_url');
    }

    /**
     * @return bool
     */
    private function hasCanonicalUrl()
    {
        return strlen($this->getCanonicalUrl()) > 0;
    }

    /**
     * @return int
     */
    public function download()
    {
        if (Key::getByHandle("install_packages")) {
            if ($this->hasCanonicalUrl()) {
                if ($this->login()) {
                    $isConnected = $this->isConnectedToCommunity();

                    if (!$isConnected) {
                        $isConnected = $this->connectToCommunity();
                    }

                    if ($isConnected) {
                        $licenseAssociated = $this->licenseAssignedToProject();

                        if (!$licenseAssociated) {
                            $licenseExists = $this->licenseExists();

                            if ($licenseExists) {
                                $licenseAssociated = $this->assignLicenseToProject();
                            }
                        }

                        if ($licenseAssociated) {
                            if ($this->downloadArchive()) {
                                return ExitCodes::SUCCESS;
                            } else {
                                $this->getOutput()->writeln(t("Error while downloading the archive."));

                                return ExitCodes::ERR_DOWNLOAD_ERROR;
                            }
                        } else {
                            $this->getOutput()->writeln(t("You don't have a free license for \"%s\".", $this->getPackageHandle()));

                            return ExitCodes::ERR_NO_VALID_LICENSE;
                        }
                    } else {
                        $this->getOutput()->writeln(t("Error while connecting this site with the marketplace."));

                        return ExitCodes::ERR_CONNECTING_TO_MARKETPLACE;
                    }

                    $this->logout();
                } else {
                    $this->getOutput()->writeln(t("Invalid credentials."));

                    return ExitCodes::ERR_INVALID_CREDENTIALS;
                }
            } else {
                $this->getOutput()->writeln(t("You have to set a canonical url."));

                return ExitCodes::ERR_NO_CANONICAL_URL;
            }
        } else {
            $this->getOutput()->writeln(t("You do not have permission to connect this site to the marketplace."));

            return ExitCodes::ERR_NO_PERMISSIONS;
        }
    }
}
