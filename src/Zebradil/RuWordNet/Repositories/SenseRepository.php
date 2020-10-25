<?php

namespace Zebradil\RuWordNet\Repositories;

use Zebradil\RuWordNet\Models\Sense;
use Zebradil\RuWordNet\Models\SenseRelation;
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
    public function getSimilarByName(string $name): array
    {
        $builder = $this->db->createQueryBuilder()
            ->select(Sense::getFields())
            ->from(static::TABLE_NAME)
            ->where('name LIKE :likeName')
            ->orderBy('similarity(name, :name)', 'DESC')
            ->addOrderBy('meaning')
            ->setMaxResults(10)
        ;

        $name = mb_strtoupper($name);
        $params = [
            'likeName' => "%{$name}%",
            'name' => $name,
        ];

        return $this->instantiateCollection($this->db->fetchAll($builder, $params));
    }

    public function getByName(string $name): array
    {
        $builder = $this->db->createQueryBuilder()
            ->select(Sense::getFields())
            ->from(static::TABLE_NAME)
            ->where('name = :name')
            ->setMaxResults(10)
        ;

        $params = [
            'name' => mb_strtoupper($name),
        ];

        return $this->instantiateCollection($this->db->fetchAll($builder, $params));
    }

    /**
     * @param string $name Parent sense name
     *
     * @return string[][] list of derived lexemes names
     */
    public function getDerivedLexemesByLexemeName($name): array
    {
        $sql = '
          SELECT
            DISTINCT cs.name
          FROM senses ps
            JOIN sense_relations sr ON sr.parent_id = ps.id
            JOIN senses cs ON cs.id = sr.child_id
          WHERE ps.name = :senseName
            AND sr.name = :relationName
          ORDER BY 1';

        $params = [
            'senseName' => mb_strtoupper($name),
            'relationName' => SenseRelation::TYPE_DERIVED_FROM,
        ];

        return $this->db->fetchAll($sql, $params);
    }
}
