<?php

namespace Zebradil\RuWordNet\Models;

use Doctrine\DBAL\Types\Type;
use Zebradil\SilexDoctrineDbalModelRepository\AbstractModel;

/**
 * @property string parent_id
 * @property string child_id
 * @property string name
 */
class SynsetRelation extends AbstractModel
{
    const FIELDS_CONFIG = [
        'parent_id' => ['type' => Type::GUID],
        'child_id' => ['type' => Type::GUID],
        'name' => ['type' => Type::TEXT],
    ];

    const TYPE_HYPERNYM = 'hypernym';
    const TYPE_HYPONYM = 'hyponym';
    const TYPE_INSTANCE_HYPERNYM = 'instance hypernym';
    const TYPE_INSTANCE_HYPONYM = 'instance hyponym';
    const TYPE_MERONYM = 'meronym';
    const TYPE_HOLONYM = 'holonym';
    const TYPE_MEMBER_MERONYM = 'member meronym';
    const TYPE_MEMBER_HOLONYM = 'member holonym';
    const TYPE_SUBSTANCE_MERONYM = 'substance meronym';
    const TYPE_SUBSTANCE_HOLONYM = 'substance holonym';
    const TYPE_PART_MERONYM = 'part meronym';
    const TYPE_PART_HOLONYM = 'part holonym';

    const TYPE_ANTONIM = 'antonym';
    const TYPE_DERIVATIONAL = 'POS-synonymy';

    const TYPE_CAUSE = 'cause';
    const TYPE_ENTAILMENT = 'entailment';

    const DOWNWARD_RELATIONS = [
        self::TYPE_HYPONYM,
        self::TYPE_INSTANCE_HYPONYM,
        self::TYPE_MERONYM,
        self::TYPE_MEMBER_MERONYM,
        self::TYPE_SUBSTANCE_MERONYM,
        self::TYPE_PART_MERONYM,
    ];

    const UPWARD_RELATIONS = [
        self::TYPE_HYPERNYM,
        self::TYPE_INSTANCE_HYPERNYM,
        self::TYPE_HOLONYM,
        self::TYPE_MEMBER_HOLONYM,
        self::TYPE_SUBSTANCE_HOLONYM,
        self::TYPE_PART_HOLONYM,
    ];

    const SYMMETRIC_RELATIONS = [
        self::TYPE_ANTONIM,
        self::TYPE_DERIVATIONAL,
    ];

    /** @var Synset */
    private $_childSynset;
    /** @var Synset */
    private $_parentSynset;

    /**
     * @param static[] $relations
     *
     * @return static[]
     */
    public static function filterUpwardRelations(array $relations): array
    {
        return static::filterRelationsByTypes($relations, static::UPWARD_RELATIONS);
    }

    /**
     * @param static[] $relations
     *
     * @return static[]
     */
    public static function filterDownwardRelations(array $relations): array
    {
        return static::filterRelationsByTypes($relations, static::DOWNWARD_RELATIONS);
    }

    /**
     * @param static[] $relations
     *
     * @return static[]
     */
    public static function filterSymmetricRelations($relations): array
    {
        return static::filterRelationsByTypes($relations, static::SYMMETRIC_RELATIONS);
    }

    public function getParentSynset()
    {
        if (null === $this->_parentSynset) {
            $this->_parentSynset = $this->_repositoryFactory->getFor(Synset::class)->find(['id' => $this->parent_id]);
        }

        return $this->_parentSynset;
    }

    public function getChildSynset()
    {
        if (null === $this->_childSynset) {
            $this->_childSynset = $this->_repositoryFactory->getFor(Synset::class)->find(['id' => $this->child_id]);
        }

        return $this->_childSynset;
    }

    /**
     * @return bool
     */
    public function isHypernym(): bool
    {
        return $this->name === static::TYPE_HYPERNYM;
    }

    /**
     * @param static[] $relations
     * @param string[] $types
     *
     * @return static[]
     */
    private static function filterRelationsByTypes(array $relations, array $types): array
    {
        return array_filter($relations, function ($relation) use ($types) {
            return \in_array($relation->name, $types, true);
        });
    }
}
