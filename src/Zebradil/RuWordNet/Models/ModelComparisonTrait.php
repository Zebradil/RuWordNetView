<?php
/**
 * Created by PhpStorm.
 * User: german
 * Date: 18.05.16
 * Time: 23:19.
 */

namespace Zebradil\RuWordNet\Models;

use Zebradil\SilexDoctrineDbalModelRepository\ModelInterface;

trait ModelComparisonTrait
{
    public function is(ModelInterface $another): ?bool
    {
        $result = parent::is($another);
        if (null === $result) {
            return null;
        }

        return $this->id === $another->id;
    }
}
