<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

namespace Spryker\Zed\Search\Business;

use Elastica\Snapshot;
use Psr\Log\LoggerInterface;
use Spryker\Client\Search\Provider\IndexClientProvider;
use Spryker\Client\Search\Provider\SearchClientProvider;
use Spryker\Shared\Kernel\Store;
use Spryker\Zed\Kernel\Business\AbstractBusinessFactory;
use Spryker\Zed\Search\Business\DataMapper\SearchDataMapper;
use Spryker\Zed\Search\Business\DataMapper\SearchDataMapperAdapter;
use Spryker\Zed\Search\Business\DataMapper\SearchDataMapperInterface;
use Spryker\Zed\Search\Business\Model\Elasticsearch\Copier\IndexCopier;
use Spryker\Zed\Search\Business\Model\Elasticsearch\DataMapper\PageDataMapper;
use Spryker\Zed\Search\Business\Model\Elasticsearch\DataMapper\PageMapBuilder;
use Spryker\Zed\Search\Business\Model\Elasticsearch\Definition\JsonIndexDefinitionLoader;
use Spryker\Zed\Search\Business\Model\Elasticsearch\Definition\JsonIndexDefinitionMerger;
use Spryker\Zed\Search\Business\Model\Elasticsearch\Generator\IndexMapCleaner;
use Spryker\Zed\Search\Business\Model\Elasticsearch\Generator\IndexMapGenerator;
use Spryker\Zed\Search\Business\Model\Elasticsearch\IndexInstaller;
use Spryker\Zed\Search\Business\Model\Elasticsearch\IndexMapInstaller;
use Spryker\Zed\Search\Business\Model\Elasticsearch\SearchIndexManager;
use Spryker\Zed\Search\Business\Model\Elasticsearch\SnapshotHandler;
use Spryker\Zed\Search\Business\Model\SearchInstaller;
use Spryker\Zed\Search\Business\Model\SearchInstallerInterface;
use Spryker\Zed\Search\SearchDependencyProvider;

/**
 * @method \Spryker\Zed\Search\SearchConfig getConfig()
 */
class SearchBusinessFactory extends AbstractBusinessFactory
{
    /**
     * @param \Psr\Log\LoggerInterface $messenger
     *
     * @return \Spryker\Zed\Search\Business\Model\SearchInstallerInterface
     */
    public function createSearchInstaller(LoggerInterface $messenger)
    {
        return new SearchInstaller($messenger, $this->getSearchInstallerStack($messenger));
    }

    /**
     * @deprecated Use `\Spryker\Zed\SearchElasticsearch\Business\SearchElasticsearchBusinessFactory::createIndex()` instead.
     *
     * @param string|null $indexName
     *
     * @return \Spryker\Zed\Search\Business\Model\Elasticsearch\SearchIndexManagerInterface
     */
    public function createSearchIndexManager(?string $indexName = null)
    {
        return new SearchIndexManager($this->getElasticsearchIndex($indexName), $this->getSearchClient());
    }

    /**
     * @return \Spryker\Zed\Search\Business\Model\Elasticsearch\SearchIndexManagerInterface
     */
    public function createSearchIndicesManager()
    {
        return new SearchIndexManager($this->getElasticsearchIndex('_all'));
    }

    /**
     * @return \Spryker\Zed\Search\Business\Model\Elasticsearch\Definition\IndexDefinitionLoaderInterface
     */
    protected function createJsonIndexDefinitionLoader()
    {
        return new JsonIndexDefinitionLoader(
            $this->getConfig()->getJsonIndexDefinitionDirectories(),
            $this->createJsonIndexDefinitionMerger(),
            $this->getUtilEncodingService(),
            [Store::getInstance()->getStoreName()]
        );
    }

    /**
     * @deprecated Use `\Spryker\Zed\Search\Business\SearchBusinessFactory::getInstallerPlugins()` instead.
     *
     * @param \Psr\Log\LoggerInterface $messenger
     *
     * @return \Spryker\Zed\SearchExtension\Dependency\Plugin\InstallPluginInterface[]|\Spryker\Zed\Search\Business\Model\SearchInstallerInterface[]
     */
    protected function getSearchInstallerStack(LoggerInterface $messenger)
    {
        $installerPlugins = $this->getInstallerPlugins();

        /** @deprecated Will be removed in favor of direct return of the attached InstallPluginInterface's. */
        if (count($installerPlugins) > 0) {
            return $installerPlugins;
        }

        return [
            $this->createElasticsearchIndexInstaller($messenger),
            $this->createIndexMapInstaller($messenger),
        ];
    }

    /**
     * @return \Spryker\Zed\SearchExtension\Dependency\Plugin\InstallPluginInterface[]
     */
    public function getInstallerPlugins(): array
    {
        return array_merge(
            $this->getSchemaInstallerPlugins(),
            $this->getMapInstallerPlugins()
        );
    }

    /**
     * @return \Spryker\Zed\SearchExtension\Dependency\Plugin\InstallPluginInterface[]
     */
    public function getSchemaInstallerPlugins(): array
    {
        return $this->getProvidedDependency(SearchDependencyProvider::PLUGINS_SEARCH_SOURCE_INSTALLER);
    }

    /**
     * @return \Spryker\Zed\SearchExtension\Dependency\Plugin\InstallPluginInterface[]
     */
    public function getMapInstallerPlugins(): array
    {
        return $this->getProvidedDependency(SearchDependencyProvider::PLUGINS_SEARCH_MAP_INSTALLER);
    }

    /**
     * @param \Psr\Log\LoggerInterface $messenger
     *
     * @return \Spryker\Zed\Search\Business\Model\SearchInstallerInterface
     */
    protected function createElasticsearchIndexInstaller(LoggerInterface $messenger)
    {
        return new IndexInstaller(
            $this->createJsonIndexDefinitionLoader(),
            $this->getElasticsearchClient(),
            $messenger,
            $this->getConfig()
        );
    }

    /**
     * @param \Psr\Log\LoggerInterface $messenger
     *
     * @return \Spryker\Zed\Search\Business\Model\SearchInstallerInterface
     */
    public function createIndexMapInstaller(LoggerInterface $messenger)
    {
        return new IndexMapInstaller(
            $this->createJsonIndexDefinitionLoader(),
            $this->createElasticsearchIndexMapCleaner(),
            $this->createElasticsearchIndexMapGenerator(),
            $messenger
        );
    }

    /**
     * @param \Psr\Log\LoggerInterface $messenger
     *
     * @return \Spryker\Zed\Search\Business\Model\SearchInstallerInterface
     */
    public function createSourceMapInstaller(LoggerInterface $messenger): SearchInstallerInterface
    {
        return new SearchInstaller($messenger, $this->getMapInstallerPlugins());
    }

    /**
     * @return \Spryker\Zed\Search\Business\Model\Elasticsearch\Generator\IndexMapGeneratorInterface
     */
    protected function createElasticsearchIndexMapGenerator()
    {
        return new IndexMapGenerator(
            $this->getConfig()->getClassTargetDirectory(),
            $this->getConfig()->getPermissionMode()
        );
    }

    /**
     * @return \Spryker\Zed\Search\Business\Model\Elasticsearch\Generator\IndexMapCleanerInterface
     */
    protected function createElasticsearchIndexMapCleaner()
    {
        return new IndexMapCleaner($this->getConfig()->getClassTargetDirectory());
    }

    /**
     * @return \Elastica\Client
     */
    public function getElasticsearchClient()
    {
        /** @var \Elastica\Client $client */
        $client = $this
            ->createSearchClientProvider()
            ->getInstance();

        return $client;
    }

    /**
     * @return \Spryker\Client\Search\Provider\SearchClientProvider
     */
    protected function createSearchClientProvider()
    {
        return new SearchClientProvider();
    }

    /**
     * @param string|null $index
     *
     * @return \Elastica\Index
     */
    protected function getElasticsearchIndex($index = null)
    {
        return $this
            ->createIndexProvider()
            ->getClient($index);
    }

    /**
     * @return \Spryker\Client\Search\Provider\IndexClientProvider
     */
    protected function createIndexProvider()
    {
        return new IndexClientProvider();
    }

    /**
     * @return \Spryker\Zed\Search\Business\Model\Elasticsearch\Definition\IndexDefinitionMergerInterface
     */
    protected function createJsonIndexDefinitionMerger()
    {
        return new JsonIndexDefinitionMerger();
    }

    /**
     * @return \Spryker\Client\Search\SearchClientInterface
     */
    public function getSearchClient()
    {
        return $this->getProvidedDependency(SearchDependencyProvider::CLIENT_SEARCH);
    }

    /**
     * @return \Spryker\Zed\Search\Business\Model\Elasticsearch\DataMapper\PageDataMapperInterface
     */
    public function createPageDataMapper()
    {
        if (count($this->getSearchDataMapperPlugins()) > 0) {
            return new SearchDataMapperAdapter(
                $this->createSearchDataMapper()
            );
        }

        return new PageDataMapper(
            $this->createPageMapBuilder(),
            $this->getSearchPageMapPlugins()
        );
    }

    /**
     * @return \Spryker\Zed\Search\Business\Model\Elasticsearch\DataMapper\PageMapBuilderInterface
     */
    protected function createPageMapBuilder()
    {
        return new PageMapBuilder();
    }

    /**
     * @return \Spryker\Zed\Search\Dependency\Service\SearchToUtilEncodingInterface
     */
    protected function getUtilEncodingService()
    {
        return $this->getProvidedDependency(SearchDependencyProvider::SERVICE_UTIL_ENCODING);
    }

    /**
     * @return \Spryker\Zed\Search\Dependency\Plugin\PageMapInterface[]
     */
    public function getSearchPageMapPlugins()
    {
        return $this->getProvidedDependency(SearchDependencyProvider::PLUGIN_SEARCH_PAGE_MAPS);
    }

    /**
     * @return \Spryker\Zed\Search\Business\Model\Elasticsearch\SnapshotHandlerInterface
     */
    public function createSnapshotHandler()
    {
        return new SnapshotHandler($this->createElasticsearchSnapshot());
    }

    /**
     * @return \Elastica\Snapshot
     */
    protected function createElasticsearchSnapshot()
    {
        return new Snapshot($this->getElasticsearchClient());
    }

    /**
     * @deprecated Use `\Spryker\Zed\SearchElasticsearch\Business\SearchElasticsearchBusinessFactory::createIndexCopier()` instead.
     *
     * @return \Spryker\Zed\Search\Business\Model\Elasticsearch\Copier\IndexCopierInterface
     */
    public function createElasticsearchIndexCopier()
    {
        return new IndexCopier(
            $this->getGuzzleClient(),
            $this->getConfig()->getReindexUrl()
        );
    }

    /**
     * @return \GuzzleHttp\Client
     */
    protected function getGuzzleClient()
    {
        return $this->getProvidedDependency(SearchDependencyProvider::GUZZLE_CLIENT);
    }

    /**
     * @return \Spryker\Zed\Search\Business\DataMapper\SearchDataMapperInterface
     */
    public function createSearchDataMapper(): SearchDataMapperInterface
    {
        return new SearchDataMapper(
            $this->getSearchDataMapperPlugins()
        );
    }

    /**
     * @return \Spryker\Zed\SearchExtension\Dependency\Plugin\SearchDataMapperPluginInterface[]
     */
    protected function getSearchDataMapperPlugins(): array
    {
        return $this->getProvidedDependency(SearchDependencyProvider::PLUGINS_SEARCH_DATA_MAPPER);
    }
}
