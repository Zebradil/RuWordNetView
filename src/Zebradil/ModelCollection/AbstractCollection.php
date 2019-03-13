<?php


namespace Zebradil\ModelCollection;

// $collection->getChildren() // returns array, so is applicable to all 
// all/first, get/set
// $c->one()->not()->hasChildren()
class AbstractCollection implements CollectionInterface
{
    use IteratorTrait,
        ArrayAccessTrait,
        CountableTrait,
        SerializableTrait,
        BitwiseFlagTrait;

    const ELEMENT_CLASS = null;
    const FLAG_ONE = 0b1;
    const FLAG_ALL = ~self::FLAG_ONE;
    const FLAG_GET = 0b10;
    const FLAG_SET = ~self::FLAG_GET;
    const FLAG_NOT = 0b100;
    protected $_array = [];
    protected $_position = 0;


    public function one()
    {
        $this->setFlag(static::FLAG_ONE);
        return $this;
    }

    public function all()
    {
        $this->setFlag(static::FLAG_ALL);
        return $this;
    }

    public function not()
    {
        $this->setFlag(static::FLAG_NOT);
        return $this;
    }

    public function get()
    {
        $this->setFlag(static::FLAG_GET);
        return $this;
    }

    public function set()
    {
        $this->setFlag(static::FLAG_SET);
        return $this;
    }

    public function __call($name, $args)
    {
        if (null === static::ELEMENT_CLASS || method_exists(static::ELEMENT_CLASS, $name)) {
            return $this->executeMethod($name, $args);
        }
        $class = static::ELEMENT_CLASS;
        throw new \BadMethodCallException("Method $class::$name not exists");
    }

    protected function executeMethod($name, $args)
    {
        if ($this->getFlag(static::FLAG_ONE)) {
            $not = $this->getFlag(static::FLAG_NOT);
            foreach ($this->_array as $element) {
                if ($element->{$name}(...$args) !== $not) {
                    return true;
                }
            }
            return false;
        } else {
            if ($this->getFlag(static::FLAG_SET)) {
                foreach ($this->_array as $element) {
                    $element->{$name}(...$args);
                }
                return $this;
            } else {
                $result = [];
                foreach ($this->_array as $element) {
                    $result[] = $element->{$name}(...$args);
                }
                return $result;
            }
        }
    }

    /**
     * @param $element
     */
    protected function ensureValidValue($element)
    {
        if (null === static::ELEMENT_CLASS || $element instanceof (static::ELEMENT_CLASS)) {
            return;
        }
        throw new \DomainException('Collection element must be an instance of ' . static::ELEMENT_CLASS);
    }
}