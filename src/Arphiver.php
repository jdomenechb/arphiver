<?php

/**
 * This file is part of the archiver package.
 *
 * (c) Jordi Domènech Bonilla
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Jdomenechb\Arphiver;

use Jdomenechb\Arphiver\Metadata;
use PDO;
use RuntimeException;

class Arphiver
{
    /** @var PDO */
    private $pdo;

    /** @var Metadata[] */
    private $metadata;

    /** @var array */
    private $config;

    /**
     * Arphiver constructor.
     *
     * @param PDO   $pdo
     * @param array $config
     */
    public function __construct(PDO $pdo, array $config = [])
    {
        $this->pdo = $pdo;
        $this->config = $config;
    }

    /**
     * @return array
     */
    public function archive(
        string $schema,
        string $tableName,
        string $whereCondition = null,
        array $whereValues = []
    ): array {
        return $this->archiveInternal($schema, $tableName, $whereCondition, $whereValues);
    }

    /**
     * @return mixed
     */
    private function archiveInternal(
        string $schema,
        string $tableName,
        ?string $whereCondition = null,
        array $whereValues = []
    ) {
        $fullTableName = $schema . '.' . $tableName;
        $escapedTableName = '`' . $schema . '`.`' . $tableName . '`';

        $query = 'SELECT * FROM ' . $escapedTableName;

        if ($whereCondition) {
            $query .= ' WHERE ' . $whereCondition;
        }

        $preparedStatement = $this->pdo->prepare($query);

        $i = 0;

        foreach ($whereValues as $whereValue) {
            $preparedStatement->bindParam(++$i, $whereValue);
        }

        if (!$preparedStatement->execute()) {
            throw new RuntimeException('Query failed');
        }

        $metadata = $this->getTableMetadata($schema, $tableName);

        $result = [];

        while ($row = $preparedStatement->fetch(PDO::FETCH_ASSOC)) {
            foreach ($metadata->fieldsThatNeedTreatment() as $fieldThatNeedsTreatment) {
                switch ($fieldThatNeedsTreatment['Type']) {
                    case 'point':
                        $row[$fieldThatNeedsTreatment['Field']] = unpack('x/x/x/x/corder/Ltype/dlat/dlon', $row[$fieldThatNeedsTreatment['Field']]);
                        break;
                    default:
                        throw new RuntimeException(
                            sprintf('No treatment defined for field of type "%s"', $fieldThatNeedsTreatment['Type'])
                        );
                }
            }

            foreach ($metadata->foreignKeys() as $field => $foreignKey) {
                if (!isset($this->config[$fullTableName]['mappedEntities'][$field])) {
                    if (!isset($this->config['defaultMapToEntity'])) {
                        throw new RuntimeException(
                            sprintf('Mapped entity for "%s" does not exist in table "%s"', $field, $fullTableName)
                        );
                    }

                    $mappedEntity = $this->config['defaultMapToEntity']($field);

                    if ($mappedEntity === $field) {
                        throw new RuntimeException(
                            sprintf(
                                'Cannot map "%s": the map has the same name as the origin in table "%s"',
                                $field,
                                $fullTableName
                            )
                        );
                    }
                } else {
                    $mappedEntity = $this->config[$fullTableName]['mappedEntities'][$field];
                }

                if (isset($row[$mappedEntity])) {
                    throw new RuntimeException(
                        sprintf('Mapped entity "%s" already exists as a field in table "%s"', $mappedEntity, $fullTableName)
                    );
                }

                if ($row[$field] !== null) {
                    $row[$mappedEntity] = $this->archiveInternal(
                        $foreignKey['REFERENCED_TABLE_SCHEMA'],
                        $foreignKey['REFERENCED_TABLE_NAME'],
                        $foreignKey['REFERENCED_COLUMN_NAME'] . ' = ?',
                        [$row[$field]]
                    );
                } else {
                    $row[$mappedEntity] = [];
                }
            }

            $result[] = $row;
        }

        return $result;
    }

    private function getTableMetadata(string $schema, string $tableName): Metadata
    {
        $fullTableName = $schema . '.' . $tableName;

        if (!isset($this->metadata[$fullTableName])) {
            $this->metadata[$fullTableName] = new Metadata(
                $this->getForeignKeys($schema, $tableName),
                $this->getDescription($schema, $tableName)
            );
        }

        return $this->metadata[$fullTableName];
    }

    /**
     * @param string $schema
     * @param string $tableName
     *
     * @return array
     */
    private function getForeignKeys(string $schema, string $tableName) :array
    {
        $fullTableName = $schema . '.' . $tableName;

        $preparedStatement = $this->pdo->prepare("SELECT
COLUMN_NAME,
REFERENCED_TABLE_SCHEMA,
REFERENCED_TABLE_NAME,
REFERENCED_COLUMN_NAME
FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
WHERE
TABLE_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL AND TABLE_SCHEMA = ?");

        $preparedStatement->bindParam(1, $tableName);
        $preparedStatement->bindParam(2, $schema);

        if (!$preparedStatement->execute()) {
            throw new RuntimeException('Could not obtain foreign keys of table "' . $fullTableName . '"');
        }

        $raw = $preparedStatement->fetchAll(PDO::FETCH_ASSOC);
        $digested = [];

        foreach ($raw as $item) {
            $digested[$item['COLUMN_NAME']] = $item;
        }

        if (isset($this->config[$fullTableName]['additionalForeignKeys'])) {
            foreach ($this->config[$fullTableName]['additionalForeignKeys'] as $field => $foreignField) {
                [$foreignSchema, $foreignTable, $foreignField] = explode('.', $foreignField);

                $digested[$field] = [
                    'COLUMN_NAME' => $field,
                    'REFERENCED_TABLE_SCHEMA' => $foreignSchema,
                    'REFERENCED_TABLE_NAME' => $foreignTable,
                    'REFERENCED_COLUMN_NAME' => $foreignField,
                ];
            }
        }

        return $digested;
    }

    /**
     * @param string $schema
     * @param string $tableName
     *
     * @return array
     */
    private function getDescription(string $schema, string $tableName) :array
    {
        $fullTableName = $schema . '.' . $tableName;

        $preparedStatement = $this->pdo->prepare(sprintf('DESCRIBE `%s`.`%s`', $schema, $tableName));

        if (!$preparedStatement->execute()) {
            throw new RuntimeException('Could not obtain description of table "' . $fullTableName . '"');
        }

        $raw = $preparedStatement->fetchAll(PDO::FETCH_ASSOC);
        $digested = [];

        foreach ($raw as $item) {
            $item['Type'] = strtolower($item['Type']);

            if ($item['Type'] === 'point') {
                $item['needsTreatment'] = true;
            }

            $digested[$item['Field']] = $item;
        }

        return $digested;
    }
}