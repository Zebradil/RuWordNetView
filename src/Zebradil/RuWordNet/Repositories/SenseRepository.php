<?php

namespace Zebradil\RuWordNet\Repositories;

use Zebradil\RuWordNet\Models\Sense;
use Zebradil\RuWordNet\Models\SenseRelation;
use Zebradil\SilexDoctrineDbalModelRepository\AbstractRepository;

class SenseRepository extends AbstractRepository
{
    const TABLE_NAME = 'senses';
    const MODEL_CLASS = Sense::class;
    const PRIMARY_KEY = ['id'];

    public function getSimilarByName(string $name): array
    {
        $name = mb_strtoupper($name);
        $builder = $this->db->createQueryBuilder()
            ->select(...Sense::getFields())
            ->from(static::TABLE_NAME)
            ->where('name LIKE :likeName')
            ->orderBy('similarity(name, :name)', 'DESC')
            ->addOrderBy('meaning')
            ->setMaxResults(10)
            ->setParameter('likeName', "%{$name}%")
            ->setParameter('name', $name)
        ;

        return $this->instantiateCollection($builder->executeQuery()->fetchAllAssociative());
    }

    public function getByName(string $name): array
    {
        $builder = $this->db->createQueryBuilder()
            ->select(...Sense::getFields())
            ->from(static::TABLE_NAME)
            ->where('name = :name')
            ->setMaxResults(10)
            ->setParameter('name', mb_strtoupper($name))
        ;

        return $this->instantiateCollection($builder->executeQuery()->fetchAllAssociative());
    }

    /**
     * @return string[][] list of derived lexemes names
     */
    public function getDerivedLexemesByLexemeName(string $name): array
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

        return $this->db->fetchAllAssociative($sql, [
            'senseName'    => mb_strtoupper($name),
            'relationName' => SenseRelation::TYPE_DERIVED_FROM,
        ]);
    }
}
