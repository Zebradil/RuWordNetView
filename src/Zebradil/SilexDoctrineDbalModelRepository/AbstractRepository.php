<?php

namespace Zebradil\SilexDoctrineDbalModelRepository;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\InvalidArgumentException;
use UnexpectedValueException;

/**
 * Represents a base Repository.
 */
abstract class AbstractRepository
{
    /** @var string|null */
    const TABLE_NAME = null;
    const MODEL_CLASS = null;
    const PRIMARY_KEY = null;

    /**
     * @var Connection
     */
    public $db;
    /**
     * @var RepositoryFactoryService
     */
    private $repositoryFactory;

    /**
     * @param Connection $db
     * @param RepositoryFactoryService $repositoryFactory
     */
    public function __construct(Connection $db, RepositoryFactoryService $repositoryFactory)
    {
        if (null === static::TABLE_NAME) {
            throw new UnexpectedValueException('Table name not defined');
        }

        if (null === static::MODEL_CLASS) {
            throw new UnexpectedValueException('Model class not defined');
        }

        if (!class_exists(static::MODEL_CLASS)) {
            throw new UnexpectedValueException('Model class defined but not exists');
        }

        if (null === static::PRIMARY_KEY) {
            throw new UnexpectedValueException('Primary key not defined');
        }

        $this->db = $db;
        $this->repositoryFactory = $repositoryFactory;
    }

    /**
     * @param ModelInterface $object
     *
     * @return int The number of affected rows.
     */
    public function insert(ModelInterface $object): int
    {
        assert('$object instanceof ' . static::MODEL_CLASS);

        return $this->db->insert(static::TABLE_NAME, $object->getRawData());
    }

    /**
     * Executes an SQL UPDATE statement on a table.
     *
     * @param ModelInterface $object
     *
     * @return int The number of affected rows.
     */
    public function update(ModelInterface $object): int
    {
        assert('$object instanceof ' . static::MODEL_CLASS);

        return $this->db->update(static::TABLE_NAME, $object->getRawData(), $this->extractPrimaryKey($object));
    }

    /**
     * @param ModelInterface $object
     *
     * @return array
     */
    protected function extractPrimaryKey(ModelInterface $object): array
    {
        $pk = [];
        foreach (static::PRIMARY_KEY as $field) {
            $pk[$field] = $object->{$field};
        }

        return $pk;
    }

    /**
     * Executes an SQL DELETE statement on a table.
     *
     * @param ModelInterface $object
     *
     * @return int The number of affected rows.
     * @throws InvalidArgumentException
     */
    public function delete(ModelInterface $object):int
    {
        assert('$object instanceof ' . static::MODEL_CLASS);

        return $this->db->delete(static::TABLE_NAME, $this->extractPrimaryKey($object));
    }

    /**
     * Returns a record by supplied id.
     *
     * @param int|string|array $condition
     *
     * @return ModelInterface
     */
    public function find($condition): ?ModelInterface
    {
        $row = $this->db->fetchAssoc(...$this->generateQuery($condition));
        if (false === $row) {
            return null;
        }

        $class = static::MODEL_CLASS;
        /** @var ModelInterface $object */
        $object = new $class($this->repositoryFactory);

        return $object->assign($row)->setIsExists(true);
    }

    protected function generateQuery($condition, $singleRow = true)
    {
        list($where, $parameters) = $condition ? $this->buildWhereStatement($condition) : ['1=1', []];
        $select = $this->buildSelectFields();
        $table = static::TABLE_NAME;
        $sql = "SELECT $select FROM $table WHERE $where" . ($singleRow ? ' LIMIT 1' : '');

        return [$sql, $parameters];
    }

    /**
     * @param $condition
     *
     * @return array
     */
    protected function buildWhereStatement($condition):array
    {
        if (is_array($condition)) {
            return [implode(' AND ', array_map(function ($key) {
                return "$key = :$key";
            }, array_keys($condition))), $condition];
        } elseif (is_numeric($condition)) {
            $pk = static::PRIMARY_KEY;

            return ["$pk = :$pk", [$pk => $condition]];
        } else {
            return [$condition, []];
        }
    }

    /**
     * @return string
     */
    protected function buildSelectFields(): string
    {
        /** @var ModelInterface $class */
        $class = static::MODEL_CLASS;

        return implode(', ', $class::getFields());
    }

    /**
     * Returns all records from this repository's table.
     *
     * @param $condition
     *
     * @return ModelInterface[]
     */
    public function findAll($condition = null):array
    {
        return $this->instantiateCollection($this->db->fetchAll(...$this->generateQuery($condition, false)));
    }

    /**
     * @param array $rows
     * @return ModelInterface[]
     */
    protected function instantiateCollection(array $rows): array
    {
        $class = static::MODEL_CLASS;

        return array_map(function (array $row) use ($class): ModelInterface {
            /** @var ModelInterface $object */
            $object = new $class($this->repositoryFactory);

            return $object->assign($row)->setIsExists(true);
        }, $rows);
    }
}
