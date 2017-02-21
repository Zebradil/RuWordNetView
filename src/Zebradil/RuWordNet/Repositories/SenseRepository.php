<?php

namespace Zebradil\RuWordNet\Repositories;

use Zebradil\RuWordNet\Models\Sense;
use Zebradil\SilexDoctrineDbalModelRepository\AbstractRepository;

/**
 * Class SenseRepository.
 */
class SenseRepository extends AbstractRepository
{
    const TABLE_NAME = 'senses';
    const MODEL_CLASS = Sense::class;
    const PRIMARY_KEY = ['id'];

    /**
     * @param string $name
     *
     * @return array
     */
    public function getSimilarByName(string $name):array
    {
        $builder = $this->db->createQueryBuilder()
            ->select(Sense::getFields())
            ->from(static::TABLE_NAME)
            ->where('name LIKE :likeName')
            ->orderBy('similarity(name, :name)', 'DESC')
            ->addOrderBy('meaning')
            ->setMaxResults(10);

        $name = mb_strtoupper($name);
        $params = [
            'likeName' => "%$name%",
            'name' => $name,
        ];

        return $this->instantiateCollection($this->db->fetchAll($builder, $params));
    }

    public function getByName(string $name):array
    {
        $builder = $this->db->createQueryBuilder()
            ->select(Sense::getFields())
            ->from(static::TABLE_NAME)
            ->where('name = :name')
            ->setMaxResults(10);

        $params = [
            'name' => mb_strtoupper($name),
        ];

        return $this->instantiateCollection($this->db->fetchAll($builder, $params));
    }
}
