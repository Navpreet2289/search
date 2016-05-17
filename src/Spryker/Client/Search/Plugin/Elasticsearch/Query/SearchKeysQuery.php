<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Client\Search\Plugin\Elasticsearch\Query;

use Elastica\Query;
use Elastica\Query\BoolQuery;
use Elastica\Query\MatchAll;
use Elastica\Query\MultiMatch;
use Generated\Shared\Search\PageIndexMap;
use Spryker\Client\Search\Dependency\Plugin\QueryInterface;

class SearchKeysQuery implements QueryInterface
{

    const FULL_TEXT_BOOSTED_BOOSTING = 3;

    /**
     * @var string
     */
    protected $searchString;

    /**
     * @var int
     */
    protected $limit;

    /**
     * @var int
     */
    protected $offset;

    /**
     * @param string $searchString
     * @param string $limit
     * @param string $offset
     */
    public function __construct($searchString, $limit = null, $offset = null)
    {
        $this->searchString = $searchString;
        $this->limit = $limit;
        $this->offset = $offset;
    }

    /**
     * @return \Elastica\Query\MatchAll
     */
    public function getSearchQuery()
    {
        $baseQuery = new Query();

        if (!empty($this->searchString)) {
            $query = $this->createFullTextSearchQuery($this->searchString);
        } else {
            $query = new MatchAll();
        }

        $baseQuery->setQuery($query);

        $this->setLimit($baseQuery);
        $this->setOffset($baseQuery);

        $baseQuery->setExplain(true);

        return $baseQuery;
    }

    /**
     * @param string $searchString
     *
     * @return \Elastica\Query\BoolQuery
     */
    protected function createFullTextSearchQuery($searchString)
    {
        $fields = [
            PageIndexMap::FULL_TEXT,
            PageIndexMap::FULL_TEXT_BOOSTED . '^' . self::FULL_TEXT_BOOSTED_BOOSTING,
        ];

        $multiMatch = (new MultiMatch())
            ->setFields($fields)
            ->setQuery($searchString)
            ->setType(MultiMatch::TYPE_CROSS_FIELDS);

        $boolQuery = (new BoolQuery())
            ->addMust($multiMatch);

        return $boolQuery;
    }

    /**
     * @param \Elastica\Query $baseQuery
     *
     * @return void
     */
    protected function setLimit($baseQuery)
    {
        if ($this->limit !== null) {
            $baseQuery->setSize($this->limit);
        }
    }

    /**
     * @param \Elastica\Query $baseQuery
     *
     * @return void
     */
    protected function setOffset($baseQuery)
    {
        if ($this->offset !== null) {
            $baseQuery->setFrom($this->offset);
        }
    }

}