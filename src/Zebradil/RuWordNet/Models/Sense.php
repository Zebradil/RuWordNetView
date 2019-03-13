<?php

namespace Zebradil\RuWordNet\Models;

use Doctrine\DBAL\Types\Type;
use Zebradil\RuWordNet\Views\SenseTemplateTrait;
use Zebradil\SilexDoctrineDbalModelRepository\AbstractModel;

/**
 * Class Sense.
 *
 * @property string id
 * @property string synset_id
 * @property string name
 * @property string lemma
 * @property string synt_type
 * @property int meaning
 */
class Sense extends AbstractModel
{
    use SenseTemplateTrait,
        ModelComparisonTrait;
    const FIELDS_CONFIG = [
        'id' => ['type' => Type::GUID],
        'synset_id' => ['type' => Type::GUID],
        'name' => ['type' => Type::TEXT],
        'lemma' => ['type' => Type::TEXT],
        'synt_type' => ['type' => Type::TEXT],
        'meaning' => ['type' => Type::SMALLINT],
    ];

    private $_relations;

    public function getSynset(): Synset
    {
        return $this->_repositoryFactory->getFor(Synset::class)->find(['id' => $this->synset_id]);
    }

    /**
     * @return SenseRelation[]
     */
    public function getRelations(): array
    {
        if (null === $this->_relations) {
            $this->_relations = $this->_repositoryFactory->getFor(SenseRelation::class)->findAllForSense($this);
        }

        return $this->_relations;
    }

    /**
     * @param string $relationName
     * @return SenseRelation[]
     */
    public function getRelationsByType(string $relationName)
    {
        return array_filter(
            $this->getRelations(),
            function (SenseRelation $relation) use ($relationName) {
                return $relation->name === $relationName;
            }
        );
    }
}
