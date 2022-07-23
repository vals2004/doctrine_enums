<?php

declare(strict_types=1);

namespace EnumeumTests\Setup;

use Doctrine\Common\EventManager;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Logging\Middleware;
use Doctrine\DBAL\Types\Type;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\Persistence\Mapping\Driver\MappingDriver;
use Enumeum\DoctrineEnum\Definition\DatabaseDefinitionRegistry;
use Enumeum\DoctrineEnum\Definition\DefinitionRegistry;
use Enumeum\DoctrineEnum\EnumUsage\UsageRegistry;
use Enumeum\DoctrineEnum\EventListener\ColumnDefinitionSubscriber;
use Enumeum\DoctrineEnum\EventListener\SchemaChangedSubscriber;
use Enumeum\DoctrineEnum\Type\EnumeumType;
use Enumeum\DoctrineEnum\TypeQueriesStack;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

abstract class BaseTestCaseSchema extends TestCase
{
    protected readonly array $params;
    protected ?EntityManager $em = null;
    protected ?DefinitionRegistry $definitionRegistry = null;
    protected ?DatabaseDefinitionRegistry $databaseDefinitionRegistry = null;
    protected ?UsageRegistry $usageRegistry = null;
    protected QueryAnalyzer $queryAnalyzer;
    protected MockObject|LoggerInterface $queryLogger;

    protected function setUp(): void
    {
        $params = $this->getConnectionParams();

        $this->resetDatabase($params);
        $this->registerTypes();

        $this->queryLogger = $this->createMock(LoggerInterface::class);
        $em = $this->getDefaultMockEntityManager($params);
        $this->setupPrerequisites($em);

        TypeQueriesStack::reset();
    }

    protected function tearDown(): void
    {
        if (null === $this->em) {
            return;
        }

        $this->em->getConnection()->close();
        $this->dropDatabase($this->getConnectionParams());

        $this->em = null;
    }

    /**
     * @return string[]
     */
    abstract protected function getBaseSQL(): array;

    abstract protected function getConnectionParams(): array;

    /**
     * @throws ORMException
     */
    protected function getDefaultMockEntityManager(
        array $params,
        EventManager $evm = null,
        Configuration $config = null
    ): EntityManager {
        $config = null === $config ? $this->getDefaultConfiguration() : $config;
        $em = EntityManager::create($params, $config, $evm);
        $em->getEventManager()->addEventSubscriber(new ColumnDefinitionSubscriber(
            $this->getDefinitionRegistry(),
            $this->getDatabaseDefinitionRegistry($em->getConnection()),
        ));
        $em->getEventManager()->addEventSubscriber(new SchemaChangedSubscriber(
            $this->getDefinitionRegistry(),
            $this->getDatabaseDefinitionRegistry($em->getConnection()),
            $this->getUsageRegistry($em->getConnection()),
        ));

        return $this->em = $em;
    }

    /**
     * TODO: Remove this method when dropping support of doctrine/dbal 2.
     * @throws RuntimeException|Exception
     */
    protected function startQueryLog(): void
    {
        if (null === $this->em || null === $this->em->getConnection()->getDatabasePlatform()) {
            throw new RuntimeException('EntityManager and database platform must be initialized');
        }
        $this->queryAnalyzer = new QueryAnalyzer($this->em->getConnection()->getDatabasePlatform());
        $this->em->getConfiguration()->setSQLLogger($this->queryAnalyzer);
    }

    /**
     * @throws Exception
     */
    protected function resetDatabase(array $params): void
    {
        $this->dropDatabase($params);
        $this->createDatabase($params);
    }

    /**
     * @throws Exception
     */
    protected function createDatabase(array $params): void
    {
        $name = $params['dbname'];
        unset($params['dbname']);
        $connection = DriverManager::getConnection($params);
        $schemaManager = $connection->createSchemaManager();

        if (!in_array($name, $schemaManager->listDatabases())) {
            $schemaManager->createDatabase($name);
        }

        $connection->close();
    }

    /**
     * @throws Exception
     */
    protected function dropDatabase(array $params): void
    {
        $name = $params['dbname'];
        unset($params['dbname']);
        $connection = DriverManager::getConnection($params);
        $schemaManager = $connection->createSchemaManager();

        if (in_array($name, $schemaManager->listDatabases())) {
            $schemaManager->dropDatabase($name);
        }

        $connection->close();
    }

    protected function registerTypes(): void
    {
        if (!Type::hasType(EnumeumType::NAME)) {
            Type::addType(EnumeumType::NAME, EnumeumType::class);
        }
    }

    protected function setupPrerequisites(EntityManager $em): void
    {
        array_map(
            static function ($sql) use ($em) {
                return $em->getConnection()->executeQuery($sql);
            },
            $this->getBaseSQL(),
        );
    }

    protected function getMetadataDriverImplementation(): MappingDriver
    {
        return new AttributeDriver([]);
    }

    protected function getDefaultConfiguration(): Configuration
    {
        $config = new Configuration();
        $config->setProxyDir(TESTS_TEMP_DIR);
        $config->setProxyNamespace('Proxy');
        $config->setMetadataDriverImpl($this->getMetadataDriverImplementation());

        $config->setMiddlewares([
            new Middleware($this->queryLogger),
        ]);

        return $config;
    }

    /**
     * @return ClassMetadata[]
     */
    protected function composeSchema(array $classes): array
    {
        $em = $this->em;

        return array_map(
            static function ($class) use ($em) {
                return $em->getClassMetadata($class);
            },
            $classes,
        );
    }

    /**
     * @param iterable $queries
     *
     * @throws Exception
     */
    protected function applySQL(iterable $queries): void
    {
        foreach ($queries as $sql) {
            $this->em->getConnection()->executeQuery($sql);
        }
    }

    protected function getDefinitionRegistry(): DefinitionRegistry
    {
        if (null === $this->definitionRegistry) {
            $this->definitionRegistry = new DefinitionRegistry();
        }

        return $this->definitionRegistry;
    }

    protected function getDatabaseDefinitionRegistry(Connection $connection): DatabaseDefinitionRegistry
    {
        if (null === $this->databaseDefinitionRegistry) {
            $this->databaseDefinitionRegistry = new DatabaseDefinitionRegistry($connection);
        }

        return $this->databaseDefinitionRegistry;
    }

    protected function getUsageRegistry(Connection $connection): UsageRegistry
    {
        if (null === $this->usageRegistry) {
            $this->usageRegistry = new UsageRegistry($connection);
        }

        return $this->usageRegistry;
    }
}
