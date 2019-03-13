<?php

namespace Zebradil\RuWordNet\Models;

use Doctrine\DBAL\Types\Type;
use Zebradil\RuWordNet\Views\SynsetTemplateTrait;
use Zebradil\SilexDoctrineDbalModelRepository\AbstractModel;

/**
 * Class Synset.
 *
 * @property string id
 * @property string name
 * @property string description
 * @property string part_of_speech
 */
class Synset extends AbstractModel
{
    use SynsetTemplateTrait,
        ModelComparisonTrait;

    const FIELDS_CONFIG = [
        'id' => ['type' => Type::GUID],
        'name' => ['type' => Type::TEXT],
        'definition' => ['type' => Type::TEXT],
        'part_of_speech' => ['type' => Type::TEXT],
    ];

    /** @var Sense[] */
    private $_senses;
    /** @var SynsetRelation[] */
    private $_relations;

    /**
     * @return SynsetRelation|null
     */
    public function getHypernym()
    {
        foreach ($this->getRelations() as $relation) {
            if ($relation->isHypernym()) {
                return $relation;
            }
        }

        return null;
    }

    /**
     * @return SynsetRelation[]
     */
    public function getRelations():array
    {
        if (null === $this->_relations) {
            $this->_relations = $this->_repositoryFactory->getFor(SynsetRelation::class)->findAllForSynset($this);
        }

        return $this->_relations;
    }

    /**
     * @return SynsetRelation[]
     */
    public function getDownwardRelations():array
    {
        return SynsetRelation::filterDownwardRelations($this->getRelations());
    }

    /**
     * @return SynsetRelation[]
     */
    public function getUpwardRelations():array
    {
        return SynsetRelation::filterUpwardRelations($this->getRelations());
    }

    /**
     * @return SynsetRelation[]
     */
    public function getSymmetricRelations():array
    {
        return SynsetRelation::filterSymmetricRelations($this->getRelations());
    }

    public function getFirstSense()
    {
        return $this->getSenses()[0];
    }

    /**
     * @return Sense[]
     */
    public function getSenses():array
    {
        if (!isset($this->_senses)) {
            $this->_senses = $this
                ->_repositoryFactory
                ->getFor(Sense::class)
                ->findAll(['synset_id' => $this->id]);
        }

        return $this->_senses;
    }
}
