<?php

/**
 * @project:   Marketplace CLI
 *
 * @author     Fabian Bitter (fabian@bitter.de)
 * @copyright  (C) 2018 Fabian Bitter
 * @version    1.0
 */

namespace Bitter\MarketplaceCli\Enumerations;

abstract class ExitCodes
{
    const SUCCESS = 0;
    const ERR_INVALID_CREDENTIALS = 1;
    const ERR_CONNECTING_TO_MARKETPLACE = 2;
    const ERR_NO_VALID_LICENSE = 3;
    const ERR_DOWNLOAD_ERROR = 4;
    const ERR_NO_PERMISSIONS = 5;
    const ERR_NO_CANONICAL_URL = 6;
}
