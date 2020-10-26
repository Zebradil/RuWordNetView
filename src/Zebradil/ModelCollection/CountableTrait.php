<?php

namespace Zebradil\ModelCollection;

use function count;

trait CountableTrait
{
    protected array $_array = [];

    /**
     * Count elements of an object.
     *
     * @see http://php.net/manual/en/countable.count.php
     *
     * @return int The custom count as an integer.
     *             </p>
     *             <p>
     *             The return value is cast to an integer.
     *
     * @since 5.1.0
     */
    public function count(): int
    {
        return \count($this->_array);
    }
}
