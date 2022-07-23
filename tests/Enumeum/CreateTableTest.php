<?php

declare(strict_types=1);

namespace EnumeumTests;

use Doctrine\ORM\Tools\SchemaTool;
use Enumeum\DoctrineEnum\Exception\InvalidArgumentException;
use EnumeumTests\Fixture\AddedValuesStatusType;
use EnumeumTests\Fixture\BaseStatusType;
use EnumeumTests\Fixture\Entity\Entity;
use EnumeumTests\Fixture\Entity\EntityEnumAddedValues;
use EnumeumTests\Fixture\Entity\EntityEnumRemovedValues;
use EnumeumTests\Fixture\RemovedValuesStatusType;
use EnumeumTests\Setup\BaseTestCaseSchemaPostgres13;

final class CreateTableTest extends BaseTestCaseSchemaPostgres13
{
    public function testEnumTypeNotExists(): void
    {
        $this->definitionRegistry->loadType(BaseStatusType::class);

        $schemaTool = new SchemaTool($this->em);
        $schema = $this->composeSchema([
            Entity::class,
        ]);

        $updateSchemaSql = $schemaTool->getUpdateSchemaSql($schema);

        self::assertEquals(
            [
                "CREATE TABLE entity (id INT NOT NULL, PRIMARY KEY(id))",
                "CREATE TYPE status_type AS ENUM ('started', 'processing', 'finished')",
                "ALTER TABLE entity ADD status status_type NOT NULL",
                "COMMENT ON COLUMN entity.status IS 'SOME Comment'",
            ],
            $updateSchemaSql,
        );

        $this->applySQL($updateSchemaSql);
    }

    public function testEnumTypeAlreadyExists(): void
    {
        $this->applySQL([
            "CREATE TYPE status_type AS ENUM ('started', 'processing', 'finished')",
        ]);

        $this->definitionRegistry->loadType(BaseStatusType::class);

        $schemaTool = new SchemaTool($this->em);
        $schema = $this->composeSchema([
            Entity::class,
        ]);

        $updateSchemaSql = $schemaTool->getUpdateSchemaSql($schema);

        self::assertEquals(
            [
                "CREATE TABLE entity (id INT NOT NULL, PRIMARY KEY(id))",
                "ALTER TABLE entity ADD status status_type NOT NULL",
                "COMMENT ON COLUMN entity.status IS 'SOME Comment'",
            ],
            $updateSchemaSql,
        );

        $this->applySQL($updateSchemaSql);
    }

    public function testEnumTypeAlreadyExistsAndNeedsAddValues(): void
    {
        $this->applySQL([
            "CREATE TYPE status_type AS ENUM ('started', 'processing', 'finished')",
        ]);

        $this->definitionRegistry->loadType(AddedValuesStatusType::class);

        $schemaTool = new SchemaTool($this->em);
        $schema = $this->composeSchema([
            EntityEnumAddedValues::class,
        ]);

        $updateSchemaSql = $schemaTool->getUpdateSchemaSql($schema);

        self::assertEquals(
            [
                "CREATE TABLE entity (id INT NOT NULL, PRIMARY KEY(id))",
                "ALTER TYPE status_type ADD VALUE IF NOT EXISTS 'accepted'",
                "ALTER TYPE status_type ADD VALUE IF NOT EXISTS 'rejected'",
                "ALTER TABLE entity ADD status status_type NOT NULL",
                "COMMENT ON COLUMN entity.status IS 'SOME Comment'",
            ],
            $updateSchemaSql,
        );

        $this->applySQL($updateSchemaSql);
    }

    public function testEnumTypeAlreadyExistsAndNeedsRemoveValues(): void
    {
        $this->applySQL([
            "CREATE TYPE status_type AS ENUM ('started', 'processing', 'finished')",
        ]);

        $this->definitionRegistry->loadType(RemovedValuesStatusType::class);

        $schemaTool = new SchemaTool($this->em);
        $schema = $this->composeSchema([
            EntityEnumRemovedValues::class,
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Enum should not be reordered with common Doctrine SchemaTool. Use Enumeum\DoctrineEnum\EnumTool for that.',
        );

        $schemaTool->getUpdateSchemaSql($schema);

        $this->fail('Test should not achieve this point.');
    }

    protected function getBaseSQL(): array
    {
        return [];
    }
}
