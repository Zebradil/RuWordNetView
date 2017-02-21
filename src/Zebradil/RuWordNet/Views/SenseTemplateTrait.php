<?php

namespace Zebradil\RuWordNet\Views;

use Zebradil\RuWordNet\Models\Sense;

trait SenseTemplateTrait
{
    public function getFullName()
    {
        /* @type Sense $this */
        return $this->name.($this->meaning ? ' '.$this->meaning : '');
    }
}
