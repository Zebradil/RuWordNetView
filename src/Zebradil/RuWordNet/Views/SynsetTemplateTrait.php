<?php

namespace Zebradil\RuWordNet\Views;

use Zebradil\RuWordNet\Models\SynsetRelation;

trait SynsetTemplateTrait
{
    public function getGroupedUpwardRelations(): array
    {
        // @var Synset $this
        return $this->groupRelations($this->getUpwardRelations());
    }

    public function groupRelations($relations): array
    {
        $result = [];
        foreach ($relations as $relation) {
            if (isset($result[$relation->name])) {
                $result[$relation->name][] = $relation;
            } else {
                $result[$relation->name] = [$relation];
            }
        }

        uksort($result, function ($a, $b) {
            $relationOrder = array_flip([
                SynsetRelation::TYPE_HYPERNYM,
                SynsetRelation::TYPE_HYPONYM,
                SynsetRelation::TYPE_PART_MERONYM,
                SynsetRelation::TYPE_PART_HOLONYM,
                SynsetRelation::TYPE_MEMBER_MERONYM,
                SynsetRelation::TYPE_MEMBER_HOLONYM,
                SynsetRelation::TYPE_SUBSTANCE_MERONYM,
                SynsetRelation::TYPE_SUBSTANCE_HOLONYM,
                SynsetRelation::TYPE_MERONYM,
                SynsetRelation::TYPE_HOLONYM,
                SynsetRelation::TYPE_INSTANCE_HYPERNYM,
                SynsetRelation::TYPE_INSTANCE_HYPONYM,
                SynsetRelation::TYPE_ANTONIM,
                SynsetRelation::TYPE_DERIVATIONAL,
                SynsetRelation::TYPE_CAUSE,
                SynsetRelation::TYPE_ENTAILMENT,
            ]);
            $a = $relationOrder[$a] ?? $a;
            $b = $relationOrder[$b] ?? $b;

            return $a <=> $b;
        });

        return $result;
    }

    public function getGroupedRelations(): array
    {
        return $this->groupRelations($this->getRelations());
    }

    public function getGroupedDownwardRelations(): array
    {
        // @var Synset $this
        return $this->groupRelations($this->getDownwardRelations());
    }

    public function getGroupedSymmetricRelations(): array
    {
        // @var Synset $this
        return $this->groupRelations($this->getSymmetricRelations());
    }
}
