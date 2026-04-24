<?php

namespace Zebradil\RuWordNet\Repositories;

use Zebradil\RuWordNet\Models\Sense;
use Zebradil\RuWordNet\Models\SenseRelation;
use Zebradil\SilexDoctrineDbalModelRepository\AbstractRepository;

class SenseRelationRepository extends AbstractRepository
{
    const TABLE_NAME = 'sense_relations';
    const MODEL_CLASS = SenseRelation::class;
    const PRIMARY_KEY = ['parent_id', 'child_id', 'name'];

    /**
     * @return SenseRelation[]
     */
    public function findAllForSense(Sense $sense): array
    {
        $builder = $this->db->createQueryBuilder()
            ->select(...SenseRelation::getFields())
            ->from(static::TABLE_NAME, 't')
            ->where("parent_id = :id AND info <> 'deleted'")
            ->setParameter('id', $sense->id)
        ;

        return $this->instantiateCollection($builder->executeQuery()->fetchAllAssociative());
    }
}
