<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace SprykerTest\Client\Search\Plugin\Elasticsearch\Fixtures;

use Elastica\Query;
use Elastica\Query\BoolQuery;
use Spryker\Client\Search\Dependency\Plugin\SearchStringGetterInterface;
use Spryker\Client\Search\Dependency\Plugin\SearchStringSetterInterface;
use Spryker\Client\SearchExtension\Dependency\Plugin\QueryInterface;

class BaseQueryPlugin implements QueryInterface, SearchStringSetterInterface, SearchStringGetterInterface
{
    protected const SOURCE_NAME = 'page';

    /**
     * @var \Elastica\Query
     */
    protected $query;

    /**
     * @var string
     */
    protected $searchString;

    public function __construct()
    {
        $this->query = (new Query())
            ->setQuery(new BoolQuery());
    }

    /**
     * {@inheritdoc}
     * - Returns a query object for base search.
     *
     * @api
     *
     * @return \Elastica\Query
     */
    public function getSearchQuery()
    {
        return $this->query;
    }

    /**
     * @param string $searchString
     *
     * @return void
     */
    public function setSearchString($searchString)
    {
        $this->searchString = $searchString;
    }

    /**
     * @return string
     */
    public function getSearchString()
    {
        return $this->searchString;
    }
}
