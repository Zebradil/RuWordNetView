<?php

namespace Zebradil\RuWordNet\Repositories;

use Zebradil\RuWordNet\Models\Synset;
use Zebradil\RuWordNet\Models\SynsetRelation;
use Zebradil\SilexDoctrineDbalModelRepository\AbstractRepository;

/**
 * Class SenseRelationRepository.
 */
class SynsetRelationRepository extends AbstractRepository
{
    const TABLE_NAME = 'synset_relations';
    const MODEL_CLASS = SynsetRelation::class;
    const PRIMARY_KEY = ['parent_id', 'child_id', 'name'];

    /**
     * @param Synset $synset
     *
     * @return SynsetRelation[]
     */
    public function findAllForSynset(Synset $synset): array
    {
        $builder = $this->db->createQueryBuilder()
            ->select(SynsetRelation::getFields())
            ->from(static::TABLE_NAME, 't')
            ->where('parent_id = :id')
        ;

        return $this->instantiateCollection($this->db->fetchAll($builder, ['id' => $synset->id]));
    }
}
