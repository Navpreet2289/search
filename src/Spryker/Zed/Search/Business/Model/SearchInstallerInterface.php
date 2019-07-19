<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Search\Business\Model;

/**
 * @deprecated Use `\Spryker\Zed\SearchExtension\Dependency\Plugin\InstallPluginInterface` instead.
 */
interface SearchInstallerInterface
{
    /**
     * @return void
     */
    public function install();
}
