<?php

namespace Zebradil\ModelCollection;

trait BitwiseFlagTrait
{
    protected $_flags = 0;

    protected function getFlag($flag)
    {
        return $flag === $flag >= 0
            ? ($this->_flags & $flag)
            : ($this->_flags | $flag);
    }

    protected function setFlag($flag)
    {
        if ($flag >= 0) {
            $this->_flags |= $flag;
        } else {
            $this->_flags &= $flag;
        }
    }
}
