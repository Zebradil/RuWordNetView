<?php


namespace Zebradil\RuWordNet\Models;


use Doctrine\DBAL\Types\Type;
use Zebradil\SilexDoctrineDbalModelRepository\AbstractModel;

/**
 * Class SenseRelation
 * @package Zebradil\RuWordNet\Models
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

}