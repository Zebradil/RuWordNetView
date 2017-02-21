<?php

namespace Zebradil\SilexDoctrineDbalModelRepository;

use Doctrine\DBAL\Types\Type;
use Silex\Application;

/**
 * Class AbstractModel.
 */
abstract class AbstractModel implements ModelInterface
{
    const FIELDS_CONFIG = [];
    protected static $_fields_defaults = [];
    protected $_isExists = false;
    protected $_loadAfterSave = false;
    /**
     * @var string
     */
    protected $protoClassName = '';
    /**
     * @var RepositoryFactoryService
     */
    protected $_repositoryFactory;

    /**
     * {@inheritdoc}
     */
    public function __construct(RepositoryFactoryService $repositoryFactory)
    {
        if (empty(static::FIELDS_CONFIG)) {
            throw new \UnexpectedValueException('Field configuration not defined in class ' . static::class);
        }

        $this->_repositoryFactory = $repositoryFactory;
        $defaults = self::getDefaultValues();
        if ($defaults) {
            $this->assign($defaults);
        }
    }

    /**
     * @return array
     */
    protected static function getDefaultValues()
    {
        $defaults = [];
        foreach (static::FIELDS_CONFIG as $field => $cfg) {
            if (isset($cfg['default'])) {
                $defaults[$field] = $cfg['default'];
            }
        }

        return $defaults;
    }

    /** {@inheritdoc} */
    public function assign(array $data, $decode = false)
    {
        $data = $decode ? $this->decodeData($data) : $data;
        $cfg = static::FIELDS_CONFIG;
        foreach ($data as $k => $v) {
            if (isset($cfg[$k])) {
                $this->{$k} = $this->cast($k, $v, !$decode);
            }
        }

        return $this;
    }

    /** {@inheritdoc} */
    public function decodeData($data)
    {
        return $data;
    }

    /**
     * Преобразует значение к указанному типу поля модели.
     *
     * @param string $name Имя поля модели
     * @param mixed $value Преобразуемое значение
     * @param bool $encode Применять ли обработку значения с помощью указанного в конфиге метода
     *
     * @return bool|int|mixed|string
     */
    private function cast($name, $value, $encode = true)
    {
        $cfg = static::FIELDS_CONFIG[$name];
        if (is_array($cfg)) {
            if ($encode && isset($cfg['process_fn'])) {
                $value = $this->{$cfg['process_fn']}($value, $name, $cfg);
            }
            if (!empty($cfg['nullable'])) {
                if ($value === null) {
                    return;
                }
                if ($value === '' && $cfg['type'] !== Type::STRING) {
                    return;
                }
            }
            switch ($cfg['type']) {
                case Type::BOOLEAN:
                    return (bool)$value;
                case Type::INTEGER:
                    return (int)$value;
                case Type::STRING:
                    return (string)$value;
                default:
                    return $value;
            }
        } else {
            return $value;
        }
    }

    /**
     * @param $fields
     *
     * @return array
     */
    public static function filterPublicFields($fields)
    {
        $modelFields = static::FIELDS_CONFIG;

        return array_filter($fields, function ($field) use ($modelFields) {
            return isset($modelFields[$field]) && !empty($modelFields[$field]['public']);
        });
    }

    /**
     * @return array
     */
    public static function getFieldsConfig():array
    {
        return static::FIELDS_CONFIG;
    }

    public function __isset($name)
    {
        $cfg = static::FIELDS_CONFIG;

        return isset($cfg[$name]);
    }

    /**
     * Возвращает свойства объекта в виде ассоциативного массива.
     * Добавлен для совместимости с MyObj. Будет удалён в дальнейшем.
     *
     * @deprecated
     *
     * @param array $fields
     *
     * @return mixed[]
     */
    public function getAttributes(array $fields = [])
    {
        return $this->getRawData($fields);
    }

    /** {@inheritdoc} */
    public function getRawData(array $fields = [], $encode = false)
    {
        $data = [];
        $modelFields = static::getFields();
        $fields = $fields ? array_intersect($modelFields, $fields) : $modelFields;
        foreach ($fields as $field) {
            $data[$field] = $this->{$field};
        }

        return $encode ? $this->encodeData($data) : $data;
    }

    /** {@inheritdoc} */
    public static function getFields(): array
    {
        return array_keys(static::FIELDS_CONFIG);
    }

    /** {@inheritdoc} */
    public function encodeData($data)
    {
        return $data;
    }

    /** {@inheritdoc} */
    public function setIsExists($value)
    {
        $this->_isExists = (bool)$value;

        return $this;
    }

    /**
     * Проверяет, загружена ли модель. Выбрасывает исключение, если нет.
     */
    public function ensureLoaded()
    {
        if (!$this->isExists()) {
            throw new \LogicException('Объект не загружен.');
        }
    }

    /** {@inheritdoc} */
    public function isExists()
    {
        return $this->_isExists;
    }

    /** {@inheritdoc} */
    public function __set($name, $value)
    {
        $cfg = static::FIELDS_CONFIG;
        if (!isset($cfg[$name])) {
            throw new \InvalidArgumentException("Попытка присвоения значения непубличному свойству «{$name}»");
        }
        $this->{$name} = $this->cast($name, $value);
    }

    public function is(ModelInterface $another)
    {
        $static = static::class;
        if ($another instanceof $static) {
            if (!$this->isExists() || !$another->isExists()) {
                return null;
            }

            return false;
        } else {
            throw new \UnexpectedValueException('Instance of ' . static::class . ' class expected, got instance of ' . get_class($another));
        }
    }
}
