<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Shared\Search\Provider;

use Elastica\Client;
use Spryker\Shared\Application\ApplicationConstants;
use Spryker\Shared\Config\Config;
use Spryker\Shared\Kernel\AbstractClientProvider;

/**
 * Class ClientStorageProvider
 *
 * @method \Elastica\Client getInstance()
 */
abstract class AbstractSearchClientProvider extends AbstractClientProvider
{

    /**
     * @throws \Exception
     *
     * @return \Elastica\Client
     */
    protected function createZedClient()
    {
        return (new Client([
            'protocol' => Config::get(ApplicationConstants::ELASTICA_PARAMETER__TRANSPORT),
            'port' => Config::get(ApplicationConstants::ELASTICA_PARAMETER__PORT),
            'host' => Config::get(ApplicationConstants::ELASTICA_PARAMETER__HOST),
        ]));
    }

}