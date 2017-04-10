<?php

namespace Zebradil\RuWordNet\Views;

use Zebradil\RuWordNet\Models\Sense;
use Zebradil\RuWordNet\Models\SenseRelation;

trait SenseTemplateTrait
{
    public function getFullName(): string
    {
        /* @type Sense $this */
        return $this->name . ($this->meaning ? ' ' . $this->meaning : '');
    }

    /**
     * @return SenseRelation[]
     */
    public function getGroupedRelatedSenses(): array
    {
        /** @type Sense $this */
        return $this->groupSensesFromSenseRelations($this->getRelationsByType(SenseRelation::TYPE_COMPOSED_OF));
    }

    /**
     * @param SenseRelation[] $relations
     * @return SenseRelation[]
     */
    protected function groupSensesFromSenseRelations(array $relations): array
    {
        $result = [];
        foreach ($relations as $relation) {
            if (isset($result[$relation->name])) {
                $result[$relation->name][] = $relation->getChildSense();
            } else {
                $result[$relation->name] = [$relation->getChildSense()];
            }
        }

        uksort($result, function ($a, $b) {
            $relationOrder = array_flip([
                SenseRelation::TYPE_COMPOSED_OF,
                SenseRelation::TYPE_DERIVED_FROM,
            ]);
            $a = $relationOrder[$a] ?? $a;
            $b = $relationOrder[$b] ?? $b;
            return $a <=> $b;
        });

        return $result;
    }
}
