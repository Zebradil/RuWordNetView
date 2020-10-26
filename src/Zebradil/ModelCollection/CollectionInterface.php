<?php

namespace Zebradil\ModelCollection;

use ArrayAccess;
use Countable;
use Iterator;
use Serializable;

interface CollectionInterface extends Iterator, ArrayAccess, Serializable, Countable
{
}
