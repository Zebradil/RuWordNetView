<?php

namespace Zebradil\RuWordNet\Models;

use Doctrine\DBAL\Types\Type;
use Zebradil\SilexDoctrineDbalModelRepository\AbstractModel;

/**
 * Class SenseRelation.
 *
 * @property string parent_id
 * @property string child_id
 * @property string name
 */
class SenseRelation extends AbstractModel
{
    const FIELDS_CONFIG = [
        'parent_id' => ['type' => Type::GUID],
        'child_id' => ['type' => Type::GUID],
        'name' => ['type' => Type::TEXT],
    ];

    const TYPE_COMPOSED_OF = 'composed_of';
    const TYPE_DERIVED_FROM = 'derived_from';

    private $_parentSense;
    private $_childSense;

    /**
     * @return Sense
     */
    public function getParentSense(): Sense
    {
        if (null === $this->_parentSense) {
            $this->_parentSense = $this->_repositoryFactory->getFor(Sense::class)->find(['id' => $this->parent_id]);
        }

        return $this->_parentSense;
    }

    /**
     * @return Sense
     */
    public function getChildSense(): Sense
    {
        if (null === $this->_childSense) {
            $this->_childSense = $this->_repositoryFactory->getFor(Sense::class)->find(['id' => $this->child_id]);
        }

        return $this->_childSense;
    }
}
