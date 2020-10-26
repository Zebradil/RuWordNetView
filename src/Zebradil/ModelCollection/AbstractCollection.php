<?php

namespace Zebradil\ModelCollection;

// $collection->getChildren() // returns array, so is applicable to all
// all/first, get/set
// $c->one()->not()->hasChildren()
use BadMethodCallException;
use DomainException;

class AbstractCollection implements CollectionInterface
{
    use IteratorTrait;
    use ArrayAccessTrait;
    use CountableTrait;
    use SerializableTrait;
    use BitwiseFlagTrait;

    const ELEMENT_CLASS = null;
    const FLAG_ONE = 0b1;
    const FLAG_ALL = ~self::FLAG_ONE;
    const FLAG_GET = 0b10;
    const FLAG_SET = ~self::FLAG_GET;
    const FLAG_NOT = 0b100;
    protected array $_array = [];
    protected int $_position = 0;

    public function __call($name, $args)
    {
        if (null === static::ELEMENT_CLASS || method_exists(static::ELEMENT_CLASS, $name)) {
            return $this->executeMethod($name, $args);
        }
        $class = static::ELEMENT_CLASS;

        throw new BadMethodCallException("Method {$class}::{$name} not exists");
    }

    public function one(): self
    {
        $this->setFlag(static::FLAG_ONE);

        return $this;
    }

    public function all(): self
    {
        $this->setFlag(static::FLAG_ALL);

        return $this;
    }

    public function not(): self
    {
        $this->setFlag(static::FLAG_NOT);

        return $this;
    }

    public function get(): self
    {
        $this->setFlag(static::FLAG_GET);

        return $this;
    }

    public function set(): self
    {
        $this->setFlag(static::FLAG_SET);

        return $this;
    }

    protected function executeMethod(string $name, $args)
    {
        if ($this->getFlag(static::FLAG_ONE)) {
            $not = $this->getFlag(static::FLAG_NOT);
            foreach ($this->_array as $element) {
                if ($element->{$name}(...$args) !== $not) {
                    return true;
                }
            }

            return false;
        }
        if ($this->getFlag(static::FLAG_SET)) {
            foreach ($this->_array as $element) {
                $element->{$name}(...$args);
            }

            return $this;
        }
        $result = [];
        foreach ($this->_array as $element) {
            $result[] = $element->{$name}(...$args);
        }

        return $result;
    }

    /**
     * @param $element
     */
    protected function ensureValidValue($element)
    {
        $class = static::ELEMENT_CLASS;
        if (null === $class || $element instanceof $class) {
            return;
        }

        throw new DomainException('Collection element must be an instance of '.static::ELEMENT_CLASS);
    }
}
