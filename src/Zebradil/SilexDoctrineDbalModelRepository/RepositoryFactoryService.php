<?php

namespace Zebradil\SilexDoctrineDbalModelRepository;

use Doctrine\DBAL\Connection;

/**
 * Class RepositoryFactoryService.
 */
class RepositoryFactoryService
{
    /**
     * @var Connection
     */
    private $connection;
    /**
     * @var array
     */
    private $repositories;

    /** @var AbstractRepository[] */
    private $repositoryInstances = [];

    /**
     * RepositoryFactoryService constructor.
     *
     * @param Connection $connection
     * @param array $repositories
     */
    public function __construct(Connection $connection, array $repositories)
    {
        $this->connection = $connection;
        $this->repositories = $repositories;
    }

    /**
     * @param string $modelClass
     *
     * @return AbstractRepository
     */
    public function getFor(string $modelClass): AbstractRepository
    {
        if (isset($this->repositoryInstances[$modelClass])) {
            return $this->repositoryInstances[$modelClass];
        }

        if (isset($this->repositories[$modelClass])) {
            return $this->repositoryInstances[$modelClass] = new $this->repositories[$modelClass]($this->connection, $this);
        } else {
            throw new \UnexpectedValueException('No repository registered for model ' . $modelClass);
        }
    }
}
